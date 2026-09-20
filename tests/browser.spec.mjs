import {test, expect} from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import fs from 'node:fs';
import path from 'node:path';
import {runtime} from './support/runtime.mjs';
const evidence=runtime.outputDir;
const output=name=>path.join(evidence,name);
for (const width of [1440,1024,768,390,320]) {
  test(`layout ${width}: approved geometry, content and accessibility`,async({page})=>{
    await page.setViewportSize({width,height:width===768?1400:width<768?1500:900});
    const errors=[]; page.on('pageerror',error=>errors.push(error.message));
    await page.goto('/'); await page.evaluate(()=>document.fonts.ready);
    await expect(page.locator('h1')).toHaveText('Malaysia Titanium Dioxide for Industrial Buyers');
    const metrics=await page.evaluate(()=>{
      const h=document.querySelector('h1'); const c=getComputedStyle(h); const r=document.createRange();r.selectNodeContents(h);
      return {width:innerWidth,scrollWidth:document.documentElement.scrollWidth,h1Size:c.fontSize,h1Weight:c.fontWeight,lines:new Set([...r.getClientRects()].map(x=>Math.round(x.top))).size,headerHeight:document.querySelector('header').getBoundingClientRect().height,heroBorder:getComputedStyle(document.querySelector('.hero-shell')).borderTopWidth,startColumns:getComputedStyle(document.querySelector('.start-grid')).gridTemplateColumns.split(' ').length,rfqDisplay:getComputedStyle(document.querySelector('.page-rfq')).display};
    });
    expect(metrics.scrollWidth).toBe(width);
    expect(metrics.h1Size).toBe(width>=1024?'56px':width>=768?'44px':'36px');
    expect(metrics.h1Weight).toBe('700');
    if([1440,768,390].includes(width)) expect(metrics.lines).toBe(width===390?3:2);
    expect(metrics.headerHeight).toBe(width>1024?84:64);
    expect(metrics.heroBorder).toBe('0px');
    expect(metrics.startColumns).toBe(width>=768?3:1);
    if(width<768) await expect(page.locator('.page-rfq')).toBeHidden(); else await expect(page.locator('.page-rfq')).toBeVisible();
    expect(errors).toEqual([]);
    const axe=await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze();
    fs.writeFileSync(output(`layout-${width}.json`),JSON.stringify({metrics,violations:axe.violations},null,2));
    expect(axe.violations).toEqual([]);
    await page.screenshot({path:output(`home-${width}.png`),fullPage:true,animations:'disabled'});
    if([1440,768,390].includes(width)) await page.screenshot({path:output(`hero-${width}.png`),animations:'disabled'});
  });
}
test('menu: keyboard focus, transparent backdrop, modal isolation and return',async({page})=>{
  await page.setViewportSize({width:390,height:900});await page.goto('/');
  const trigger=page.locator('.menu-button');
  await trigger.focus();await page.keyboard.press('Enter');
  const dialog=page.getByRole('dialog',{name:'Primary navigation menu'});
  await expect(dialog).toBeVisible();await expect(trigger).toHaveAttribute('aria-expanded','true');
  await expect(page.getByRole('button',{name:'Close primary navigation menu'})).toBeFocused();
  expect(await page.locator('#mobile-menu').evaluate(el=>getComputedStyle(el,'::backdrop').backgroundColor)).toBe('rgba(0, 0, 0, 0)');
  for(let i=0;i<16;i++){await page.keyboard.press('Tab');expect(await dialog.evaluate(el=>el.contains(document.activeElement))).toBe(true);}
  const ax=await page.locator('body').ariaSnapshot();expect(ax).not.toContain('heading "Malaysia Titanium');
  await page.screenshot({path:output('menu-390.png')});
  expect((await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze()).violations).toEqual([]);
  await page.keyboard.press('Escape');await expect(dialog).toBeHidden();await expect(trigger).toBeFocused();
  await trigger.click();await page.setViewportSize({width:1440,height:900});await expect(dialog).toBeHidden();
});
test('cookie settings: no analytics state, focus return and no storage',async({page,context})=>{
  await page.setViewportSize({width:390,height:900});const external=[];
  const expectedOrigin=new URL(runtime.baseURL).origin;
  page.on('request',r=>{if(new URL(r.url()).origin!==expectedOrigin)external.push(r.url())});
  await page.goto('/');await page.getByRole('button',{name:'Cookie Settings',exact:true}).click();
  const dialog=page.getByRole('dialog',{name:'Cookie settings',exact:true});await expect(dialog).toBeVisible();
  await expect(dialog.getByRole('button',{name:'Close',exact:true})).toBeFocused();
  expect(await dialog.getByRole('button',{name:'Close',exact:true}).evaluate(el=>getComputedStyle(el).backgroundColor)).toBe('rgb(255, 255, 255)');
  await expect(dialog).toContainText('No optional Analytics or advertising technology is currently active on this site.');
  expect(await dialog.getByRole('checkbox').count()).toBe(0);
  await page.screenshot({path:output('cookie-390.png')});
  expect((await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze()).violations).toEqual([]);
  await page.keyboard.press('Escape');await expect(dialog).toBeHidden();await expect(page.getByRole('button',{name:'Cookie Settings',exact:true})).toBeFocused();
  expect(await page.evaluate(()=>({local:localStorage.length,session:sessionStorage.length}))).toEqual({local:0,session:0});
  expect(await context.cookies()).toEqual([]);expect(external).toEqual([]);
  fs.writeFileSync(output('privacy.json'),JSON.stringify({externalRequests:external,cookies:[],localStorageKeys:0,sessionStorageKeys:0,state:'no_optional_analytics'},null,2));
});
test('mobile grades: default collapsed, keyboard expansion and all 14 labels reachable',async({page})=>{
  await page.setViewportSize({width:390,height:900});await page.goto('/');
  const groups=page.locator('.product-group');await expect(groups).toHaveCount(4);
  for(let i=0;i<4;i++) {
    const group=groups.nth(i), summary=group.locator('summary');
    await expect(summary).toHaveAttribute('aria-expanded','false');await expect(group.locator('.grades')).toBeHidden();
    await summary.focus();await page.keyboard.press('Enter');await expect(summary).toHaveAttribute('aria-expanded','true');await expect(group.locator('.grades')).toBeVisible();
  }
  await expect(page.locator('.grades span:visible')).toHaveCount(14);
  await page.locator('[data-module=products]').screenshot({path:output('products-expanded-390.png')});
});
