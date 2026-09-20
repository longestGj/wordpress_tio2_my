import {test,expect} from '@playwright/test';
import fs from 'node:fs';
import crypto from 'node:crypto';
import path from 'node:path';
import {execFileSync} from 'node:child_process';
import {runtime,workspaceContainerPath} from './support/runtime.mjs';

const output=name=>path.join(runtime.outputDir,`rfq-${name}`);
const cli=(...args)=>execFileSync('docker',['compose','--env-file',runtime.envFile,'-f',runtime.composeFile,'run','--rm','wpcli',...args],{encoding:'utf8',stdio:['ignore','pipe','pipe']}).trim();
const snapshot=()=>JSON.parse(cli('eval-file','/workspace/scripts/snapshot.php','export'));
const hash=value=>crypto.createHash('sha256').update(JSON.stringify(value)).digest('hex');
const passwordLine=fs.readFileSync(runtime.envFile,'utf8').split(/\r?\n/).find(line=>line.startsWith('D32_ADMIN_PASSWORD='));
if(!passwordLine)throw new Error(`D32_ADMIN_PASSWORD is missing from ${runtime.envFile}`);
const password=passwordLine.split('=').slice(1).join('=');
const field=(page,key)=>page.locator(`[name="fields[${key}]"]`);
async function edit(page,key,value){const control=field(page,key);await control.evaluate(node=>node.closest('details').open=true);await control.fill(value);}

async function login(page){
  await page.goto('/wp-login.php');await page.locator('#user_login').fill('d32editor');await page.locator('#user_pass').fill(password);await page.locator('#wp-submit').click();
  await page.goto('/wp-admin/admin.php?page=tio2-rfq-content');await expect(page.getByRole('heading',{name:'RFQ content',exact:true})).toBeVisible();
}

test('RFQ editor persists visible/state/SEO content and restores exactly',async({page})=>{
  test.setTimeout(120000);
  const before=snapshot();expect(before.rfq_content).toBeTruthy();
  const beforePath=output('content-before.json');fs.writeFileSync(beforePath,JSON.stringify(before,null,2)+'\n');
  const homeHash=hash(before.content);let changed;
  try{
    await login(page);
    await edit(page,'hero.heading','Temporary RFQ editor heading');
    await edit(page,'form.heading','Temporary quotation fields');
    await edit(page,'success.heading','Temporary confirmed receipt.');
    await edit(page,'seo.title','Temporary RFQ editor title');
    await edit(page,'seo.description','Temporary RFQ editor description.');
    await page.getByRole('button',{name:'Save RFQ content',exact:true}).click();
    await expect(page.getByText('RFQ content saved.',{exact:true})).toBeVisible();
    changed=snapshot();expect(hash(changed.content)).toBe(homeHash);expect(hash(changed.rfq_content)).not.toBe(hash(before.rfq_content));
    await page.goto('/request-a-quote/');await expect(page.locator('h1')).toHaveText('Temporary RFQ editor heading');
    await expect(page.locator('#rfq-form-heading')).toHaveText('Temporary quotation fields');
    expect(await page.locator('#rfq-success').evaluate(node=>node.content.textContent)).toContain('Temporary confirmed receipt.');
    await expect(page).toHaveTitle('Temporary RFQ editor title');
    await expect(page.locator('meta[name=description]')).toHaveAttribute('content','Temporary RFQ editor description.');
    await page.screenshot({path:output('editor-changed.png'),fullPage:false});

    await page.goto('/wp-admin/admin.php?page=tio2-rfq-content');
    await edit(page,'privacy.path','javascript:alert(1)');
    const invalid=page.waitForResponse(response=>response.url().endsWith('/admin-post.php')&&response.request().method()==='POST');
    await page.getByRole('button',{name:'Save RFQ content',exact:true}).click();
    expect((await invalid).status()).toBe(422);expect(snapshot().rfq_content).toEqual(changed.rfq_content);

    await page.goto('/wp-admin/admin.php?page=tio2-rfq-content');
    await field(page,'hero.body').evaluate(node=>node.remove());
    const incomplete=page.waitForResponse(response=>response.url().endsWith('/admin-post.php')&&response.request().method()==='POST');
    await page.getByRole('button',{name:'Save RFQ content',exact:true}).click();
    expect((await incomplete).status()).toBe(422);expect(snapshot().rfq_content).toEqual(changed.rfq_content);

    await page.goto('/wp-admin/admin.php?page=tio2-rfq-content');
    await page.locator('[name=revision]').evaluate(node=>node.value='stale');
    const stale=page.waitForResponse(response=>response.url().endsWith('/admin-post.php')&&response.request().method()==='POST');
    await page.getByRole('button',{name:'Save RFQ content',exact:true}).click();
    expect((await stale).status()).toBe(409);expect(snapshot().rfq_content).toEqual(changed.rfq_content);
  }finally{
    cli('eval-file','/workspace/scripts/snapshot.php','restore',workspaceContainerPath(beforePath));
  }
  const restored=snapshot();expect(restored.content).toEqual(before.content);expect(restored.rfq_content).toEqual(before.rfq_content);
  await page.goto('/request-a-quote/');await expect(page.locator('h1')).toHaveText(before.rfq_content.fields['hero.heading']);
  fs.writeFileSync(output('editor-results.json'),JSON.stringify({beforeSha256:hash(before.rfq_content),changedSha256:hash(changed.rfq_content),restoredSha256:hash(restored.rfq_content),homeUnchanged:true,invalidSaveStatus:422,incompleteSaveStatus:422,staleSaveStatus:409,restored:true},null,2)+'\n');
});

test('RFQ editor rejects missing nonce without changing either record',async({page})=>{
  const before=snapshot();await login(page);
  await page.locator('[name=_wpnonce]').evaluate(node=>node.value='invalid');
  const response=page.waitForResponse(item=>item.url().endsWith('/admin-post.php')&&item.request().method()==='POST');
  await page.getByRole('button',{name:'Save RFQ content',exact:true}).click();
  expect((await response).status()).toBe(403);expect(snapshot().content).toEqual(before.content);expect(snapshot().rfq_content).toEqual(before.rfq_content);
});

test('RFQ editor rejects unauthenticated writes',async({request})=>{
  const before=snapshot();
  const response=await request.post('/wp-admin/admin-post.php',{form:{action:'tio2_rfq_save'},maxRedirects:0});
  expect(response.status()).toBeGreaterThanOrEqual(400);expect(snapshot().content).toEqual(before.content);expect(snapshot().rfq_content).toEqual(before.rfq_content);
});
