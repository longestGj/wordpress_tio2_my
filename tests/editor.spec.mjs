import {test,expect} from '@playwright/test';
import fs from 'node:fs';
import crypto from 'node:crypto';
import {execFileSync} from 'node:child_process';
const evidence=process.env.HOME_EVIDENCE_DIR || 'docs/verification/home';
fs.mkdirSync(evidence,{recursive:true});
const containerEvidence='/workspace/'+evidence.replaceAll('\\','/').replace(/^\.\//,'');
const cli=(...args)=>execFileSync('docker',['compose','run','--rm','wpcli',...args],{encoding:'utf8',stdio:['ignore','pipe','pipe']}).trim();
const snapshot=()=>JSON.parse(cli('eval-file','/workspace/scripts/snapshot.php','export'));
const hash=x=>crypto.createHash('sha256').update(JSON.stringify(x)).digest('hex');
const password=fs.readFileSync('.env','utf8').split(/\r?\n/).find(x=>x.startsWith('D32_ADMIN_PASSWORD=')).split('=').slice(1).join('=');
const field=(page,key)=>page.locator(`[name="fields[${key}]"]`);
async function login(page) {
  await page.goto('/wp-login.php');await page.locator('#user_login').fill('d32editor');await page.locator('#user_pass').fill(password);await page.locator('#wp-submit').click();
  await page.goto('/wp-admin/admin.php?page=tio2-content');await expect(page.getByRole('heading',{name:'Site content',exact:true})).toBeVisible();
}
test('editor: persisted content, media, SEO, invalid input, stale edit and exact restore',async({page})=>{
  test.setTimeout(90000);
  const before=snapshot();fs.writeFileSync(`${evidence}/content-before.json`,JSON.stringify(before,null,2)+'\n');
  const codeHash=hash(fs.readFileSync('wp-content/themes/tio2-malaysia/front-page.php','utf8'));
  let changed;
  let imageId;
  try {
    await login(page);
    await field(page,'hero.heading.1').fill('Editable homepage verification');
    await field(page,'hero.paragraph.1').fill('Temporary editor verification text.');
    await field(page,'hero.link.2').fill('/documents/');
    imageId=cli('media','import','/workspace/content/media/hero.png','--title=D32 editor test image','--porcelain').split(/\r?\n/).at(-1);
    await field(page,'hero.image').fill(imageId);
    await page.locator('details').filter({has:page.locator('summary',{hasText:/^Products$/})}).locator('summary').click();
    await field(page,'products.grade.1').fill('TEST-GRADE');
    await page.locator('details').filter({has:page.locator('summary',{hasText:/^Seo$/})}).locator('summary').click();
    await field(page,'seo.title').fill('Editor verification title');
    await field(page,'seo.description').fill('Editor verification description.');
    await page.getByRole('button',{name:'Save site content',exact:true}).click();
    await expect(page.getByText('Content saved.',{exact:true})).toBeVisible();
    changed=snapshot();
    await page.goto('/');await expect(page.locator('h1')).toHaveText('Editable homepage verification');
    await expect(page.locator('.hero-copy')).toContainText('Temporary editor verification text.');
    await expect(page.locator('.hero-actions .secondary')).toHaveAttribute('href','/documents/');
    await expect(page.locator('.grades span').first()).toHaveText('TEST-GRADE');
    await expect(page).toHaveTitle('Editor verification title');
    expect(await page.locator('meta[name=description]').getAttribute('content')).toBe('Editor verification description.');
    expect(await page.locator('.hero-media img').getAttribute('src')).toContain('hero');
    await page.screenshot({path:`${evidence}/editor-changed.png`,fullPage:false});
    await page.goto('/wp-admin/admin.php?page=tio2-content');
    await field(page,'hero.link.1').fill('javascript:alert(1)');
    const rejection=page.waitForResponse(r=>r.url().endsWith('/admin-post.php')&&r.request().method()==='POST');
    await page.getByRole('button',{name:'Save site content',exact:true}).click();
    expect((await rejection).status()).toBe(422);expect(snapshot().content).toEqual(changed.content);
    await page.goto('/wp-admin/admin.php?page=tio2-content');
    await page.locator('[name=revision]').evaluate(el=>el.value='stale');
    const conflict=page.waitForResponse(r=>r.url().endsWith('/admin-post.php')&&r.request().method()==='POST');
    await page.getByRole('button',{name:'Save site content',exact:true}).click();
    expect((await conflict).status()).toBe(409);expect(snapshot().content).toEqual(changed.content);
  } finally {
    cli('eval-file','/workspace/scripts/snapshot.php','restore',`${containerEvidence}/content-before.json`);
    if(imageId) cli('post','delete',imageId,'--force');
  }
  const restored=snapshot();expect(restored.content).toEqual(before.content);
  expect(hash(fs.readFileSync('wp-content/themes/tio2-malaysia/front-page.php','utf8'))).toBe(codeHash);
  await page.goto('/');await expect(page.locator('h1')).toHaveText(before.content.fields['hero.heading.1']);
  fs.writeFileSync(`${evidence}/content-restored.json`,JSON.stringify(restored,null,2)+'\n');
  fs.writeFileSync(`${evidence}/editor-results.json`,JSON.stringify({beforeSha256:hash(before.content),changedSha256:hash(changed.content),restoredSha256:hash(restored.content),themeEntryUnchanged:true,invalidSaveStatus:422,staleSaveStatus:409,restored:true},null,2)+'\n');
});

test('editor: media picker works and missing nonce cannot write content',async({page})=>{
  const before=snapshot();
  await login(page);
  await page.getByRole('button',{name:'Choose image',exact:true}).click();
  const media=page.getByRole('dialog');await expect(media).toBeVisible();
  await expect(media.getByRole('heading',{name:'Choose hero image',exact:true})).toBeVisible();
  await media.getByRole('button',{name:'Close dialog',exact:true}).click();
  await page.locator('[name=_wpnonce]').evaluate(el=>el.value='invalid');
  const rejection=page.waitForResponse(r=>r.url().endsWith('/admin-post.php')&&r.request().method()==='POST');
  await page.getByRole('button',{name:'Save site content',exact:true}).click();
  expect((await rejection).status()).toBe(403);
  expect(snapshot().content).toEqual(before.content);
});

test('editor: unauthenticated visitor cannot save content',async({request})=>{
  const before=snapshot();
  const result=await request.post('/wp-admin/admin-post.php',{form:{action:'tio2_save'},maxRedirects:0});
  expect(result.status()).toBeGreaterThanOrEqual(400);
  expect(snapshot().content).toEqual(before.content);
});
