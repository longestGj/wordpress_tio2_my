import {test,expect} from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import {execFileSync} from 'node:child_process';
import {runtime} from './support/runtime.mjs';

const project=process.env.COMPOSE_PROJECT_NAME ?? '';
const isolatedProject=project==='d32-product-000' || project.startsWith('tio2-ci-');
const base=new URL(runtime.baseURL);
if(!isolatedProject || base.protocol!=='http:' || !['127.0.0.1','localhost','[::1]'].includes(base.hostname)) throw new Error('Products editor test refuses a non-isolated runtime');
const cli=(...args)=>execFileSync('docker',['compose','--env-file',runtime.envFile,'-f',runtime.composeFile,'run','--rm','wpcli',...args],{encoding:'utf8',stdio:['ignore','pipe','pipe']}).trim();
const option=name=>JSON.parse(cli('option','get',name,'--format=json'));
const hash=value=>crypto.createHash('sha256').update(JSON.stringify(value)).digest('hex');
const passwordLine=fs.readFileSync(runtime.envFile,'utf8').split(/\r?\n/).find(line=>line.startsWith('D32_ADMIN_PASSWORD='));
if(!passwordLine) throw new Error(`D32_ADMIN_PASSWORD is missing from ${runtime.envFile}`);
const password=passwordLine.split('=').slice(1).join('=');
const field=(page,key)=>page.locator(`[name="products_fields[${key}]"]`);
const openGroup=async(page,name)=>{const details=page.locator('details').filter({has:page.locator('summary',{hasText:new RegExp(`^${name}$`,'i')})});if(!(await details.getAttribute('open')))await details.locator('summary').click();};
const evidence=process.env.PRODUCT_EVIDENCE_DIR || path.join(runtime.outputDir,'products-editor');
fs.mkdirSync(evidence,{recursive:true});

async function login(page){
  await page.goto('/wp-login.php');await page.locator('#user_login').fill('d32editor');await page.locator('#user_pass').fill(password);await page.locator('#wp-submit').click();
  await page.goto('/wp-admin/admin.php?page=tio2-products');await expect(page.getByRole('heading',{name:'Products content',exact:true})).toBeVisible();
}

test('Products editor persists visible and Schema copy, rejects unsafe writes, and restores exactly',async({page,request})=>{
  test.setTimeout(120000);
  const original=option('tio2_products_content');
  fs.writeFileSync(`${evidence}/products-content-before.json`,JSON.stringify(original,null,2)+'\n');
  const homepageHash=hash(option('tio2_content'));
  let saved;
  try{
    await login(page);
    await expect(page.getByRole('link',{name:'Preview Products',exact:true})).toHaveAttribute('href','/products/');
    await openGroup(page,'Directory');await openGroup(page,'Seo');
    await field(page,'directory.grade.1.summary').fill('Temporary Products editor summary for exact parity.');
    await field(page,'seo.description').fill('Temporary Products editor metadata description.');
    await page.getByRole('button',{name:'Save Products content',exact:true}).click();
    await expect(page.getByText('Products content saved.',{exact:true})).toBeVisible();
    saved=option('tio2_products_content');
    expect(hash(saved)).not.toBe(hash(original));expect(hash(option('tio2_content'))).toBe(homepageHash);

    await page.goto('/products/');
    await expect(page.locator('.product-grade-row').first().locator('p')).toHaveText('Temporary Products editor summary for exact parity.');
    await expect(page.locator('meta[name="description"]')).toHaveAttribute('content','Temporary Products editor metadata description.');
    const graph=JSON.parse(await page.locator('script[type="application/ld+json"]').textContent())['@graph'];
    expect(graph.find(node=>node['@type']==='ItemList').itemListElement[0].item.description).toBe('Temporary Products editor summary for exact parity.');
    await page.screenshot({path:`${evidence}/editor-changed.png`,fullPage:false});

    await page.goto('/wp-admin/admin.php?page=tio2-products');
    await openGroup(page,'Directory');
    await field(page,'directory.grade.1.path').fill('javascript:alert(1)');
    let response=page.waitForResponse(result=>result.url().endsWith('/admin-post.php')&&result.request().method()==='POST');
    await page.getByRole('button',{name:'Save Products content',exact:true}).click();
    expect((await response).status()).toBe(422);expect(option('tio2_products_content')).toEqual(saved);

    await page.goto('/wp-admin/admin.php?page=tio2-products');
    await openGroup(page,'Directory');
    await field(page,'directory.grade.2.summary').evaluate(node=>node.remove());
    response=page.waitForResponse(result=>result.url().endsWith('/admin-post.php')&&result.request().method()==='POST');
    await page.getByRole('button',{name:'Save Products content',exact:true}).click();
    expect((await response).status()).toBe(422);expect(option('tio2_products_content')).toEqual(saved);

    await page.goto('/wp-admin/admin.php?page=tio2-products');
    await page.locator('[name=products_revision]').evaluate(node=>node.value='stale');
    response=page.waitForResponse(result=>result.url().endsWith('/admin-post.php')&&result.request().method()==='POST');
    await page.getByRole('button',{name:'Save Products content',exact:true}).click();
    expect((await response).status()).toBe(409);expect(option('tio2_products_content')).toEqual(saved);

    await page.goto('/wp-admin/admin.php?page=tio2-products');
    await page.locator('[name=_wpnonce]').evaluate(node=>node.remove());
    response=page.waitForResponse(result=>result.url().endsWith('/admin-post.php')&&result.request().method()==='POST');
    await page.getByRole('button',{name:'Save Products content',exact:true}).click();
    expect((await response).status()).toBe(403);expect(option('tio2_products_content')).toEqual(saved);

    const unauth=await request.post('/wp-admin/admin-post.php',{form:{action:'tio2_products_save'},maxRedirects:0});
    expect(unauth.status()).toBeGreaterThanOrEqual(400);expect(option('tio2_products_content')).toEqual(saved);
  }finally{
    const encoded=Buffer.from(JSON.stringify(original),'utf8').toString('base64');
    cli('eval',`update_option('tio2_products_content',json_decode(base64_decode('${encoded}'),true),false);`);
  }
  expect(option('tio2_products_content')).toEqual(original);expect(hash(option('tio2_content'))).toBe(homepageHash);
  fs.writeFileSync(`${evidence}/products-content-restored.json`,JSON.stringify(option('tio2_products_content'),null,2)+'\n');
  fs.writeFileSync(`${evidence}/editor-results.json`,JSON.stringify({beforeSha256:hash(original),changedSha256:hash(saved),restoredSha256:hash(option('tio2_products_content')),homepageUnchanged:true,invalidPathStatus:422,incompleteFieldsStatus:422,staleRevisionStatus:409,missingNonceStatus:403,unauthenticatedRejected:true,restored:true},null,2)+'\n');
  await page.goto('/products/');await expect(page.locator('.product-grade-row').first().locator('p')).toHaveText(original.fields['directory.grade.1.summary']);
});
