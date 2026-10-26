'use strict';
const {chromium}=require('playwright');
const fs=require('node:fs');
let browser;
(async()=>{
 const base=process.env.VV_TEST_BASE,checks=[],errors=[];
 const launch={headless:true,args:['--no-sandbox']};if(process.env.VV_BROWSER_EXECUTABLE)launch.executablePath=process.env.VV_BROWSER_EXECUTABLE;
 browser=await chromium.launch(launch);
 const ck=(n,v)=>{if(!v)throw Error(n);checks.push(n);};
 const publicContext=await browser.newContext({viewport:{width:1280,height:900}}),page=await publicContext.newPage();page.on('pageerror',e=>errors.push(e.message));
 await page.goto(base+'/?p=contact');
 ck('desktop form has accessible title',await page.locator('.vv-form').getAttribute('aria-labelledby')==='form-contact-title');
 ck('original brand image loads',await page.locator('img.vv-wordmark').evaluate(img=>img.complete&&img.naturalWidth===1024));
 ck('French flag and engineering signature visible',await page.locator('.vv-origin').innerText()==='Conception et ingénierie françaises');
 await page.locator('[name="fields[name]"]').fill('Browser visitor');await page.locator('[name="fields[email]"]').fill('browser@example.test');await page.locator('[name="fields[message]"]').fill('<script>browser private</script>');
 await page.locator('.vv-form button[type=submit]').click();await page.waitForURL('**/*form_received=contact*');
 ck('browser submits with signed PRG receipt',await page.locator('.vv-form-success').innerText()==='Saved request.');
 const owner=await browser.newContext({viewport:{width:1280,height:900}}),admin=await owner.newPage();admin.on('pageerror',e=>errors.push(e.message));
 await admin.goto(base+'/login.php');await admin.locator('[name=password]').fill('Forms_test_owner_130!');await admin.getByRole('button',{name:'Ouvrir mon espace'}).click();await admin.waitForURL('**/admin.php*');
 await admin.goto(base+'/admin.php?view=plugin-forms');await admin.locator('table a').first().click();
 ck('owner opens private submission',await admin.locator('dd').filter({hasText:'<script>browser private</script>'}).count()===1);
 ck('private values do not execute JavaScript',await admin.locator('dd script').count()===0);
 for(const view of ['plugin-forms','plugins','tools']){
  await admin.setViewportSize({width:390,height:844});await admin.goto(base+'/admin.php?view='+view);
  ck('mobile admin no page overflow '+view,await admin.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
 }
 const nojs=await browser.newContext({javaScriptEnabled:false,viewport:{width:390,height:844}}),plain=await nojs.newPage();await plain.goto(base+'/?p=contact&lang=en');
 ck('English mobile form renders without JS',await plain.locator('.vv-form form').count()===1&&await plain.locator('.vv-origin').innerText()==='French design and engineering');
 ck('mobile form no horizontal overflow',await plain.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
 await plain.locator('[name="fields[name]"]').fill('No JS');await plain.locator('[name="fields[email]"]').fill('nojs@example.test');await plain.locator('[name="fields[message]"]').fill('No JavaScript needed');await plain.locator('.vv-form button').click();
 ck('no-JS submission succeeds',await plain.locator('.vv-form-success').count()===1);
 ck('no JavaScript exceptions',errors.length===0);
 const report={checks:checks.length,passed:checks,errors,browser:browser.version()};fs.writeFileSync(process.env.VV_FORMS_BROWSER_REPORT,JSON.stringify(report,null,2)+'\n');await browser.close();console.log('PASS',checks.length,'Forms browser checks');
})().catch(async e=>{console.error(e);if(browser)await browser.close();process.exit(1);});
