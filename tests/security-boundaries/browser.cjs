const {chromium}=require('playwright');
const {execFileSync}=require('node:child_process');
const fs=require('node:fs');
const path=require('node:path');
(async()=>{
 const php=['/usr/bin/php','/usr/local/bin/php'].find(p=>fs.existsSync(p));
 if(!php)throw Error('Local browser image has no PHP toolchain');
 const data=JSON.parse(execFileSync(php,[path.join(__dirname,'render.php'),'json'],{env:{PATH:'/usr/local/bin:/usr/bin:/bin',HOME:'/scratch',TMPDIR:'/scratch',LANG:'C.UTF-8',TZ:'UTC'},maxBuffer:100000}).toString());
 const browser=await chromium.launch({headless:true,args:['--no-sandbox','--disable-dev-shm-usage','--disable-gpu']});let checks=0,failures=0;
 const check=(ok,label)=>{checks++;if(!ok)failures++;console.log((ok?'PASS ':'FAIL ')+label);};
 try{
  const page=await browser.newPage();await page.route('**/*',r=>r.abort());
  await page.setContent(data.editor);
  check(await page.locator('[data-audit-marker]').count()===0,'Chromium metadata stays inert');
  check(await page.locator('#altname').inputValue()===data.title,'Chromium preserves complete title');
  check(await page.locator('#file-desc').inputValue()===data.description,'Chromium preserves literal description');
  await page.setContent('<table>'+data.advancedOrdinary+'</table>');check(await page.locator('.advanced-file-props').count()===0,'Chromium ordinary editor has no advanced controls');
  await page.setContent('<table>'+data.advancedManager+'</table>');check(await page.locator('#hits').count()===1,'Chromium permitted manager retains advanced controls');
  for(let i=0;i<data.addresses.length;i++){
   const row=data.addresses[i];await page.setContent(row.html);
   const a=page.locator('.directions a');const href=await a.getAttribute('href');
   check((i<2?href===row.source:href.startsWith('https://maps.google.com/maps?q=')),'Chromium directions policy '+i);
   check(await page.locator('[data-audit-marker]').count()===0,'Chromium URL stays inside its attribute '+i);
  }
 }finally{await browser.close();}
 console.log(`CHECKS=${checks} FAILURES=${failures}`);process.exitCode=failures?1:0;
})().catch(e=>{console.error(e.stack);process.exitCode=1;});
