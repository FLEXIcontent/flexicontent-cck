'use strict';
const {chromium}=require('playwright'),assert=require('node:assert/strict'),path=require('node:path');
const jquery=process.env.FLEXI_TEST_JQUERY;
if(!jquery)throw new Error('Set FLEXI_TEST_JQUERY to the Joomla jQuery asset used for legacy gallery tests.');
(async()=>{
 const browser=await chromium.launch({headless:true});
 try {
  for(const file of ['jmultibox.js','jmultibox.min.js'])for(const width of [390,768,1280]){
   const page=await browser.newPage({viewport:{width,height:900}}),errors=[];page.on('pageerror',e=>errors.push(e.message));
   page.setDefaultTimeout(10000);
   await page.route('https://fixture.invalid/**',route=>{
    const name=new URL(route.request().url()).pathname;
    if(name.endsWith('.png')){
     const [w,h]=name==='/tall.png'?[400,1600]:name==='/small.png'?[100,80]:[1600,1000];
     return route.fulfill({contentType:'image/svg+xml',body:`<svg xmlns="http://www.w3.org/2000/svg" width="${w}" height="${h}"><rect width="100%" height="100%" fill="teal"/></svg>`});
    }
    return route.fulfill({contentType:'text/html',body:'<header style="position:relative;z-index:10;height:260px">Navigation</header><a class="mb" href="/wide.png">Wide</a><a class="mb" href="/tall.png">Tall</a><a class="mb" href="/small.png">Small</a>'});
   });
   await page.goto('https://fixture.invalid/');
   await page.addStyleTag({path:path.join(__dirname,'../site/librairies/jmultibox/styles/multibox.css')});
   await page.addScriptTag({path:jquery});
   await page.addScriptTag({path:path.join(__dirname,'../site/librairies/jmultibox/js',file)});
   await page.evaluate(()=>jQuery('a.mb').jmultibox({openFromLink:false,useOverlay:false}));
   await page.locator('a.mb').first().click();
   for(const name of ['wide','tall','small']){
    await page.waitForFunction(name=>{
     const img=document.querySelector('.MultiBoxContent img');
     return img?.complete&&img.naturalWidth>0&&img.src.endsWith('/'+name+'.png')&&getComputedStyle(img.parentElement).opacity==='1';
    },name);
    const size=await page.locator('.MultiBoxContent img').evaluate(n=>({rect:n.getBoundingClientRect().toJSON(),natural:n.naturalWidth/n.naturalHeight}));
    const close=await page.locator('.MultiBoxClose').boundingBox();
    assert(size.rect.x>=0&&size.rect.right<=width,'Image extends outside viewport');
    assert(size.rect.y>=0&&size.rect.bottom<=900,'Image extends below viewport');
    assert(close.x>=0&&close.x+close.width<=width&&close.y>=0,'Close button is outside viewport');
    assert(Math.abs(size.rect.width/size.rect.height-size.natural)<0.03,'Image aspect ratio changed');
    if(name==='small')assert.equal(size.rect.width,100,'Small images must not be enlarged');
    else await page.locator('.MultiBoxNext').click();
   }
   await page.locator('.MultiBoxClose').click();await page.locator('.MultiBoxContainer').waitFor({state:'hidden'});
   await page.locator('a.mb').first().click();await page.waitForTimeout(1400);
   await page.keyboard.press('Escape');await page.locator('.MultiBoxContainer').waitFor({state:'hidden'});
   assert.deepEqual(errors,[]);console.log(`PASS: ${file}, ${width}px: wide/tall/small images fit, navigate and close`);
   await page.close();
  }
 }finally{await browser.close()}
})().catch(e=>{console.error(e);process.exitCode=1});
