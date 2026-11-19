/* Run with Playwright installed: node tests/native-maintenance.cjs */
const assert = require('node:assert/strict');
const path = require('node:path');
const {chromium} = require('playwright');
(async () => {
  const browser = await chromium.launch({headless:true}), results = [];
  async function test(name, run) {
    const page = await browser.newPage(), errors = [];
    page.on('pageerror', e => errors.push(e.message));
    try {
      await page.route('https://fixture.invalid/**', r => r.fulfill({contentType:'text/html', body:
        '<div id="insideprogress"></div><div id="updatepercent"></div><div id="statuscomment"></div><div id="statuslog"></div><div id="fc_import_failed_box" hidden></div><form id="adminForm" action="/save"><input name="taglist" value="One & two"><input name="token" value="1"><button>Submit</button></form><div id="result"></div>'}));
      await page.goto('https://fixture.invalid/');
      for (const name of ['form-utils.js', 'admin-actions.js']) await page.addScriptTag({path:path.join(__dirname,'../site/assets/js',name)});
      await run(page);
      assert.deepEqual(errors, []);
      assert.equal(await page.evaluate(() => typeof jQuery), 'undefined');
      results.push({name,status:'pass'});
    } catch (e) { results.push({name,status:'fail',message:e.stack}); }
    finally { await page.close(); }
  }
  const index = page => page.evaluate(() => FlexiAdmin.runIndexer({countUrl:'/count',indexUrl:'/index?cid[]=42&token=1',records:'items'}));
  const runImport = page => page.evaluate(() => FlexiAdmin.runImport({countUrl:'/count',importUrl:'/import',batch:1}));
  await test('Indexer preserves selected IDs and finishes all batches', async page => {
    const calls=[];
    await page.route('**/count',r=>r.fulfill({body:'success|2|1|0|Ready|'}));
    await page.route('**/index?**',r=>{const u=new URL(r.request().url());calls.push(u);return r.fulfill({body:'success|'+(Number(u.searchParams.get('records_cnt'))+1)+'|Done|'});});
    await index(page);
    assert.equal(calls.length,2);calls.forEach(u=>{assert.equal(u.searchParams.get('cid[]'),'42');assert.equal(u.searchParams.get('token'),'1');});
    assert.match(await page.locator('#statuscomment').innerText(),/INDEXING FINISHED/);
    assert.equal(await page.locator('#insideprogress').evaluate(n=>n.style.width),'100%');
  });
  await test('Empty index completes without a write request', async page => {
    await page.route('**/count',r=>r.fulfill({body:'success|0|0|0||'}));
    let writes=0;await page.route('**/index?**',r=>{writes++;return r.abort();});
    await index(page);assert.equal(writes,0);assert.match(await page.locator('#statuscomment').innerText(),/FINISHED/);
  });
  for (const mode of ['stalled','malformed','http']) await test('Indexer stops after '+mode+' response', async page => {
    await page.route('**/count',r=>r.fulfill({body:'success|2|1|0||'}));
    let writes=0;await page.route('**/index?**',r=>{writes++;return r.fulfill({status:mode==='http'?500:200,body:mode==='stalled'?'success|0||':'<script>window.injected=true</script>Failure'});});
    await index(page);assert.equal(writes,1);assert.match(await page.locator('#statuscomment').innerText(),/HALTED/);assert.equal(await page.evaluate(()=>window.injected),undefined);
  });
  await test('CSV import resumes and follows rotated form tokens', async page => {
    await page.route('**/count',r=>r.fulfill({body:'success|3|1|firstToken'}));
    const calls=[];await page.route('**/import?**',r=>{calls.push(new URL(r.request().url()));return r.fulfill({body:'success||||nextToken||||<div>Saved</div>'});});
    await runImport(page);assert.deepEqual(calls.map(u=>u.searchParams.get('itemcnt')),['1','2']);assert.equal(calls[0].searchParams.get('firstToken'),'1');assert.equal(calls[1].searchParams.get('nextToken'),'1');assert.equal(await page.locator('#statuslog > div').count(),2);assert.match(await page.locator('#statuscomment').innerText(),/FINISHED/);
  });
  await test('CSV import reports server-side partial failures', async page => {
    await page.route('**/count',r=>r.fulfill({body:'success|1|0|token'}));
    await page.route('**/import?**',r=>r.fulfill({body:'success||||token||||<div class="fc_import_failed">Field failed</div>'}));
    await runImport(page);assert.match(await page.locator('#statuscomment').innerText(),/1 FAILED/);assert.equal(await page.locator('#fc_import_failed_box').isVisible(),true);
  });
  await test('CSV HTTP failures halt without retrying a write', async page => {
    await page.route('**/count',r=>r.fulfill({body:'success|2|0|token'}));let calls=0;
    await page.route('**/import?**',r=>{calls++;return r.fulfill({status:500,body:'Failure'});});
    await runImport(page);assert.equal(calls,1);assert.match(await page.locator('#statuscomment').innerText(),/HALTED/);
  });
  await test('Result forms preserve data, reject duplicate submissions and recover from HTTP failure', async page => {
    const calls=[];await page.route('**/save',async r=>{calls.push(new URLSearchParams(r.request().postData()));await new Promise(resolve=>setTimeout(resolve,150));return r.fulfill({status:calls.length===1?500:200,body:'<p>Saved</p>'});});
    await page.evaluate(()=>FlexiAdmin.bindResultForm(document.getElementById('adminForm'),document.getElementById('result')));
    await page.evaluate(()=>{document.getElementById('adminForm').requestSubmit();document.getElementById('adminForm').requestSubmit();});
    await page.waitForFunction(()=>!document.getElementById('adminForm').fc_result_busy);
    assert.equal(calls.length,1);assert.match(await page.locator('#result').innerText(),/HTTP 500/);
    await page.locator('button').click();await page.locator('#result p').waitFor();assert.equal(calls[1].get('taglist'),'One & two');assert.equal(calls[1].get('token'),'1');
  });
  await browser.close();console.log(JSON.stringify(results,null,2));if(results.some(r=>r.status==='fail'))process.exitCode=1;
})().catch(e=>{console.error(e);process.exitCode=1;});
