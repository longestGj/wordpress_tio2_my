import {test, expect} from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import fs from 'node:fs';
import path from 'node:path';
import {runtime} from './support/runtime.mjs';

const evidence=process.env.PRODUCT_EVIDENCE_DIR || path.join(runtime.outputDir,'products-browser');
fs.mkdirSync(evidence,{recursive:true});
const modules=['breadcrumb','hero','selector','process','directory','evaluation','faq','final-rfq'];

for (const width of [1440,1024,768,390]) {
  test(`products layout ${width}: responsive, complete and accessible`,async({page})=>{
    await page.setViewportSize({width,height:width===1440?1000:width===390?1200:1400});
    const errors=[];page.on('pageerror',error=>errors.push(error.message));
    await page.goto('/products/');await page.evaluate(()=>document.fonts.ready);
    expect(await page.locator('main > section').evaluateAll(nodes=>nodes.map(node=>node.dataset.module))).toEqual(modules);
    await expect(page.locator('h1')).toHaveText('Titanium Dioxide Pigment Grades for Industrial Applications');
    const metrics=await page.evaluate(()=>{
      const h=document.querySelector('h1'),style=getComputedStyle(h),range=document.createRange();range.selectNodeContents(h);
      const columns=selector=>getComputedStyle(document.querySelector(selector)).gridTemplateColumns.split(' ').length;
      const longCopy=[...document.querySelectorAll('.product-grade-row p,.product-evaluation-step p')].every(node=>node.scrollWidth<=node.clientWidth+1 && node.scrollHeight>=node.clientHeight);
      const smallTargets=[...document.querySelectorAll('a,button')].filter(node=>node.getClientRects().length).map(node=>({text:node.textContent.trim(),width:node.getBoundingClientRect().width,height:node.getBoundingClientRect().height})).filter(box=>box.width<43.5||box.height<43.5);
      const primaryStyle=getComputedStyle(document.querySelector('.products-page .primary'));
      return {scrollWidth:document.documentElement.scrollWidth,h1Size:style.fontSize,h1Weight:style.fontWeight,h1Spacing:style.letterSpacing,h1Lines:new Set([...range.getClientRects()].map(rect=>Math.round(rect.top))).size,headerHeight:document.querySelector('.global-header').getBoundingClientRect().height,directoryColumns:columns('.product-directory'),selectorColumns:columns('.product-selector'),stepColumns:columns('.product-evaluation-steps'),longCopy,smallTargets,primaryColor:primaryStyle.color,primaryBackground:primaryStyle.backgroundColor};
    });
    expect(metrics.scrollWidth).toBe(width);
    expect(metrics.headerHeight).toBe(width>1024?84:64);
    expect(metrics.directoryColumns).toBe(width>1100?2:1);
    expect(metrics.selectorColumns).toBe(width>1100?2:1);
    expect(metrics.stepColumns).toBe(width>1100?5:width>767?2:1);
    expect(metrics.longCopy).toBe(true);
    expect(metrics.primaryColor).not.toBe(metrics.primaryBackground);
    if(width===390){expect(metrics.h1Size).toBe('36px');expect(metrics.h1Weight).toBe('700');expect(['normal','0px']).toContain(metrics.h1Spacing);expect(metrics.h1Lines).toBe(4);expect(metrics.smallTargets).toEqual([]);}
    if(width>1024) await expect(page.locator('.desktop-nav a[aria-current="page"]')).toBeVisible();
    else await expect(page.locator('.menu-button')).toBeVisible();
    expect(errors).toEqual([]);
    const axe=await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze();
    fs.writeFileSync(`${evidence}/layout-${width}.json`,JSON.stringify({metrics,violations:axe.violations},null,2));
    expect(axe.violations).toEqual([]);
    await page.screenshot({path:`${evidence}/products-${width}.png`,fullPage:true,animations:'disabled'});
  });
}

test('selector: all approved sets, focus, neutral default and Not Sure',async({page})=>{
  await page.setViewportSize({width:1024,height:1200});await page.goto('/products/');
  const expected=new Map([['Coatings',8],['Plastics',8],['Masterbatch',7],['Printing Inks',4],['Paper',2],['Specialty Materials',1]]);
  const original=page.url();
  const data=JSON.parse(await page.locator('#products-selector-data').textContent());
  expect(data.explicitSelection).toBe(false);
  expect(JSON.stringify(data)).not.toMatch(/\b(?:PRODUCT-000|GRADE-[A-Z0-9-]+|PRODUCT-PROC-(?:CL|SU)|APP-000|DOC-000|MARKET-000)\b/);
  expect(Object.values(data.applications).flat().every(grade=>Object.keys(grade).sort().join(',')==='name,url')).toBe(true);
  await expect(page.locator('.product-selector')).toHaveAttribute('data-explicit-selection','false');
  for(const [label,count] of expected){
    const button=page.getByRole('button',{name:label,exact:true});await button.focus();await page.keyboard.press('Enter');
    await expect(button).toBeFocused();await expect(button).toHaveAttribute('aria-pressed','true');
    await expect(page.locator('.product-selector')).toHaveAttribute('data-explicit-selection','true');
    await expect(page.locator('#product-result-heading')).toContainText(`Grades to Review — ${count}`);
    await expect(page.locator('.product-selector-result')).toHaveCount(count);
    await expect(page.locator('.product-selector-result a')).toHaveCount(0);
    expect(page.url()).toBe(original);
  }
  const unsure=page.getByRole('button',{name:'Not Sure',exact:true});await unsure.focus();await page.keyboard.press('Space');
  await expect(unsure).toBeFocused();await expect(page.locator('#product-results')).toContainText('No grade is listed for this application.');
  await expect(page.locator('#product-results a[href="#all-grades"]')).toBeVisible();expect(page.url()).toBe(original);
  await page.locator('[data-module="selector"]').screenshot({path:`${evidence}/selector-not-sure-1024.png`});
});

test('FAQ: one open, four closed, server answers retained and keyboard toggles',async({page})=>{
  let initialHtml='';page.on('response',async response=>{if(response.url().endsWith('/products/'))initialHtml=await response.text();});
  await page.setViewportSize({width:768,height:1200});await page.goto('/products/');
  expect((initialHtml.match(/class="product-faq-answer"/g)||[]).length).toBe(5);
  const items=page.locator('.product-faq-item');await expect(items).toHaveCount(5);
  await expect(items.nth(0).locator('button')).toHaveAttribute('aria-expanded','true');await expect(items.nth(0).locator('.product-faq-answer')).toBeVisible();
  for(let i=1;i<5;i++){await expect(items.nth(i).locator('button')).toHaveAttribute('aria-expanded','false');await expect(items.nth(i).locator('.product-faq-answer')).toBeHidden();expect((await items.nth(i).locator('.product-faq-answer').textContent()).trim().length).toBeGreaterThan(20);}
  await items.nth(0).locator('button').focus();await page.keyboard.press('Enter');await expect(items.nth(0).locator('.product-faq-answer')).toBeHidden();await expect(items.nth(0).locator('button span')).toHaveText('+');
  await items.nth(1).locator('button').focus();await page.keyboard.press('Space');await expect(items.nth(1).locator('.product-faq-answer')).toBeVisible();await expect(items.nth(1).locator('button span')).toHaveText('−');
  await page.locator('[data-module="faq"]').screenshot({path:`${evidence}/faq-keyboard-768.png`});
});

test('mobile menu and cookie dialog preserve modal isolation and focus',async({page,context})=>{
  await page.setViewportSize({width:390,height:900});await page.goto('/products/');
  const trigger=page.locator('.menu-button');await trigger.focus();await page.keyboard.press('Enter');
  const menu=page.getByRole('dialog',{name:'Primary navigation menu'});await expect(menu).toBeVisible();await expect(page.getByRole('button',{name:'Close primary navigation menu'})).toBeFocused();
  await expect(menu.locator('a[aria-current="page"]')).toHaveText('Products');
  for(let i=0;i<16;i++){await page.keyboard.press('Tab');expect(await menu.evaluate(el=>el.contains(document.activeElement))).toBe(true);}
  expect(await page.locator('body').ariaSnapshot()).not.toContain('Titanium Dioxide Pigment Grades for Industrial Applications');
  await page.screenshot({path:`${evidence}/menu-390.png`});
  await page.keyboard.press('Escape');await expect(menu).toBeHidden();await expect(trigger).toBeFocused();
  const cookieTrigger=page.getByRole('button',{name:'Cookie Settings',exact:true});await cookieTrigger.click();
  const cookie=page.getByRole('dialog',{name:'Cookie settings',exact:true});await expect(cookie).toBeVisible();await expect(cookie.getByRole('button',{name:'Close',exact:true})).toBeFocused();
  expect(await page.locator('body').ariaSnapshot()).not.toContain('Titanium Dioxide Pigment Grades for Industrial Applications');
  await page.screenshot({path:`${evidence}/cookie-390.png`});
  await page.keyboard.press('Escape');await expect(cookie).toBeHidden();await expect(cookieTrigger).toBeFocused();
  expect(await page.evaluate(()=>({local:localStorage.length,session:sessionStorage.length}))).toEqual({local:0,session:0});expect(await context.cookies()).toEqual([]);
});

test('reduced motion disables product transitions and animations',async({page})=>{
  await page.emulateMedia({reducedMotion:'reduce'});await page.goto('/products/');
  const values=await page.locator('.product-selector').evaluate(node=>{const style=getComputedStyle(node);return {animation:style.animationDuration,transition:style.transitionDuration,scroll:getComputedStyle(document.documentElement).scrollBehavior};});
  expect(values.animation).toBe('0s');expect(values.transition).toBe('0s');expect(values.scroll).toBe('auto');
});
