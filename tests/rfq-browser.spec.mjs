import {test, expect} from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import fs from 'node:fs';
import path from 'node:path';
import {runtime} from './support/runtime.mjs';

const output=name=>path.join(runtime.outputDir,`rfq-${name}`);
const valid={
  grade_id:'m-350',application_id:'coatings',quantity_mt:'12.5',destination_country:'Malaysia',
  destination_port_city:'Port Klang',company_name:'RFQ Browser Company',contact_name:'RFQ Browser Buyer',
  business_email:'browser-rfq@example.test',phone_whatsapp:'+60 12 345 6789',website:'https://example.test/procurement',
  additional_requirements:'Synthetic non-confidential browser fixture.',
};

async function fill(page,values=valid){
  for(const [name,value] of Object.entries(values)){
    const field=page.locator(`[name="${name}"]`);
    if(await field.evaluate(el=>el.tagName)==='SELECT') await field.selectOption(value);
    else await field.fill(value);
  }
}

for(const width of [1440,1280,1024,768,430,390,375,320]){
  test(`RFQ responsive ${width}`,async({page})=>{
    await page.setViewportSize({width,height:width>=1024?1000:1400});
    const errors=[];page.on('pageerror',error=>errors.push(error.message));
    await page.goto('/request-a-quote/');await page.evaluate(()=>document.fonts.ready);
    const metrics=await page.evaluate(()=>{
      const form=document.querySelector('.rfq-group-requirement .rfq-field-grid');
      const controls=[...document.querySelectorAll('#rfq-form :is(input:not([type=hidden]),select,textarea,button)')];
      const quantity=document.querySelector('.rfq-quantity');
      return {
        width:innerWidth,scrollWidth:document.documentElement.scrollWidth,
        sections:[...document.querySelectorAll('main>section')].map(el=>el.dataset.module),
        columns:getComputedStyle(form).gridTemplateColumns.split(' ').length,
        h1:getComputedStyle(document.querySelector('h1')).fontSize,
        minTarget:Math.min(...controls.map(el=>el.getBoundingClientRect().height)),
        quantityDisplay:getComputedStyle(quantity).display,
        quantityOverflow:quantity.scrollWidth>quantity.clientWidth,
        surfaceRadius:getComputedStyle(document.querySelector('.rfq-form-surface')).borderRadius,
        surfaceRule:getComputedStyle(document.querySelector('.rfq-form-surface')).borderLeftWidth,
      };
    });
    expect(metrics.scrollWidth).toBe(width);
    expect(metrics.sections).toEqual(['rfq-hero','rfq-form','rfq-other']);
    expect(metrics.columns).toBe(width>=1024?2:1);
    expect(metrics.h1).toBe(width>=1024?'56px':width>=768?'44px':'36px');
    expect(metrics.minTarget).toBeGreaterThanOrEqual(44);
    expect(metrics.quantityDisplay).toBe('flex');expect(metrics.quantityOverflow).toBe(false);
    expect(metrics.surfaceRadius).toBe('12px');expect(metrics.surfaceRule).toBe('4px');
    await expect(page.locator('.validation-summary')).toBeHidden();
    await expect(page.locator('.field-error:visible')).toHaveCount(0);
    expect(errors).toEqual([]);
    const axe=await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze();
    expect(axe.violations.filter(item=>['serious','critical'].includes(item.impact))).toEqual([]);
    if([1440,768,390].includes(width)) await page.screenshot({path:output(`initial-${width}.png`),fullPage:true,animations:'disabled'});
  });
}

test('RFQ client validation focuses summary and preserves entries',async({page})=>{
  await page.setViewportSize({width:1440,height:1000});await page.goto('/request-a-quote/');
  await page.locator('[name=destination_port_city]').fill('Port Klang');
  await page.getByRole('button',{name:'REQUEST QUOTE'}).click();
  const summary=page.locator('.validation-summary');
  await expect(summary).toBeVisible();await expect(summary).toBeFocused();
  await expect(summary.locator('a')).toHaveCount(7);
  await expect(page.locator('[aria-invalid=true]')).toHaveCount(7);
  await expect(page.locator('[name=destination_port_city]')).toHaveValue('Port Klang');
  await summary.screenshot({path:output('validation.png')});
});

test('RFQ pending prevents duplicate and explicit receipt alone clears values',async({page})=>{
  await page.route('**/wp-admin/admin-ajax.php',async route=>{
    await new Promise(resolve=>setTimeout(resolve,500));
    await route.fulfill({status:200,contentType:'application/json',body:JSON.stringify({state:'receipt_confirmed',fieldErrors:[]})});
  });
  let posts=0;page.on('request',request=>{if(request.method()==='POST'&&request.url().includes('admin-ajax.php'))posts++;});
  await page.goto('/request-a-quote/');await fill(page);
  const submit=page.getByRole('button',{name:'REQUEST QUOTE'});await submit.click();
  await expect(page.getByRole('button',{name:'SUBMITTING…'})).toBeDisabled();
  await page.getByRole('button',{name:'SUBMITTING…'}).screenshot({path:output('pending.png')});
  await page.getByRole('button',{name:'SUBMITTING…'}).click({force:true});
  await expect(page.locator('.rfq-state')).toContainText('Thank you. We’ve received your quotation request.');
  await expect(page.locator('.rfq-state')).toBeFocused();
  expect(posts).toBe(1);
  await expect(page.locator('[name=company_name]')).toHaveValue('');
  await expect(page.locator('#rfq-form')).toBeHidden();
  await expect(page.locator('.header-rfq')).toBeEnabled();
  await page.locator('.rfq-state').screenshot({path:output('success.png')});
});

test('RFQ receiver field error is registered, focused, and values remain',async({page})=>{
  await page.route('**/wp-admin/admin-ajax.php',route=>route.fulfill({status:422,contentType:'application/json',body:JSON.stringify({state:'validation_failed',fieldErrors:{business_email:'Enter a business email in the format name@company.com.',provider_only:'Private provider text'}})}));
  await page.goto('/request-a-quote/');await fill(page);await page.getByRole('button',{name:'REQUEST QUOTE'}).click();
  await expect(page.locator('.validation-summary')).toBeFocused();
  await expect(page.locator('#rfq-field-business_email-error')).toHaveText('Enter a business email in the format name@company.com.');
  await expect(page.locator('body')).not.toContainText('Private provider text');
  await expect(page.locator('[name=company_name]')).toHaveValue(valid.company_name);
});

for(const [name,status,state,heading] of [
  ['failure',502,'submission_unconfirmed','Something went wrong while submitting your request.'],
  ['unavailable',503,'service_unavailable','The quotation request form is temporarily unavailable.'],
]){
  test(`RFQ ${name} state retains values`,async({page})=>{
    let calls=0;
    await page.route('**/wp-admin/admin-ajax.php',route=>{calls++;return route.fulfill({status,contentType:'application/json',body:JSON.stringify({state,fieldErrors:[]})});});
    await page.goto('/request-a-quote/');await fill(page);await page.getByRole('button',{name:'REQUEST QUOTE'}).click();
    await expect(page.locator('.rfq-state')).toBeFocused();await expect(page.locator('.rfq-state')).toContainText(heading);
    await expect(page.locator('[name=company_name]')).toHaveValue(valid.company_name);expect(calls).toBe(1);
    if(name==='failure') await expect(page.getByRole('button',{name:'TRY AGAIN'})).toBeVisible();
    await page.locator('.rfq-state').screenshot({path:output(`${name}.png`)});
  });
}

test('RFQ 200 message-only response fails closed and never auto-retries',async({page})=>{
  let calls=0;
  await page.route('**/wp-admin/admin-ajax.php',route=>{calls++;return route.fulfill({status:200,contentType:'application/json',body:JSON.stringify({message:'Submitted successfully'})});});
  await page.goto('/request-a-quote/');await fill(page);await page.getByRole('button',{name:'REQUEST QUOTE'}).click();
  await expect(page.locator('.rfq-state')).toContainText('Something went wrong while submitting your request.');
  await page.waitForTimeout(300);expect(calls).toBe(1);
  await expect(page.locator('[name=business_email]')).toHaveValue(valid.business_email);
});

test('RFQ long values, 200 percent reflow proxy, reduced motion, and focus remain usable',async({page})=>{
  await page.setViewportSize({width:640,height:900});await page.goto('/request-a-quote/');
  await page.locator('[name=additional_requirements]').fill('Long non-confidential context '.repeat(45));
  expect(await page.evaluate(()=>document.documentElement.scrollWidth)).toBe(640);
  expect(await page.evaluate(()=>getComputedStyle(document.documentElement).scrollBehavior)).toBe('auto');
  const country=page.locator('[name=destination_country]');await country.focus();
  expect(await country.evaluate(el=>getComputedStyle(el).outlineStyle)).not.toBe('none');
  await page.setViewportSize({width:320,height:900});expect(await page.evaluate(()=>document.documentElement.scrollWidth)).toBe(320);
  await page.locator('[name=additional_requirements]').evaluate(node=>node.style.color='transparent');
  await page.screenshot({path:output('long-320.png'),fullPage:true,animations:'disabled'});
});

test('RFQ mobile menu keeps current task visible and returns focus',async({page})=>{
  await page.setViewportSize({width:390,height:900});await page.goto('/request-a-quote/');
  const trigger=page.getByRole('button',{name:'Open primary navigation'});await trigger.click();
  const dialog=page.getByRole('dialog',{name:'Primary navigation menu'});await expect(dialog).toBeVisible();
  const current=dialog.getByRole('navigation',{name:'Mobile navigation'}).getByRole('link',{name:'Request a Quote',exact:true});await expect(current).toHaveAttribute('aria-current','page');
  await page.screenshot({path:output('menu-390.png'),animations:'disabled'});
  expect((await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze()).violations.filter(item=>['serious','critical'].includes(item.impact))).toEqual([]);
  await page.keyboard.press('Escape');await expect(dialog).toBeHidden();await expect(trigger).toBeFocused();
});
