'use strict';
const fs=require('fs'),path=require('path'),{chromium}=require('playwright');
const f=JSON.parse(fs.readFileSync(process.env.VV_140_FIXTURE)),checks=[],errors=[],csp=[];let browser;
function ck(name,value){checks.push({name,ok:!!value});console.log((value?'PASS ':'FAIL ')+name);if(!value)throw Error(name);}
(async()=>{
 browser=await chromium.launch({headless:true,executablePath:process.env.VV_BROWSER_EXECUTABLE||undefined,args:['--no-sandbox','--disable-dev-shm-usage']});
 const ctx=await browser.newContext({viewport:{width:1440,height:1000},reducedMotion:'reduce'});const p=await ctx.newPage();p.setDefaultTimeout(18000);
 p.on('pageerror',e=>errors.push(e.message));p.on('console',m=>{if(m.type()==='error'&&/Content Security Policy|Refused to|violates/.test(m.text()))csp.push(m.text());});
 await p.goto(f.base+'/login.php');await p.locator('[name=password]').fill(f.password);await p.getByRole('button',{name:'Ouvrir mon espace'}).click();await p.waitForURL('**/admin.php*');
 const toggle=p.locator('.sidebar-collapse'),sidebar=p.locator('#sidebar');const expanded=(await sidebar.boundingBox()).width;
 ck('desktop collapse toggle is keyboard accessible',await toggle.isVisible()&&await toggle.getAttribute('aria-expanded')==='true');
 await toggle.focus();await p.keyboard.press('Enter');
 ck('desktop sidebar collapses to an icon rail',(await sidebar.boundingBox()).width<100&&expanded>200&&await toggle.getAttribute('aria-expanded')==='false');
 ck('collapsed links retain accessible names',await sidebar.getByRole('link',{name:'Articles',exact:true}).count()===1&&await sidebar.locator('nav>a').first().getAttribute('title')!==null);
 await p.reload();ck('collapse preference survives reload',await toggle.getAttribute('aria-expanded')==='false'&&(await sidebar.boundingBox()).width<100);
 await sidebar.getByRole('link',{name:'Articles',exact:true}).click();await p.waitForURL('**view=posts*');ck('collapsed navigation still opens posts',await p.locator('h1').innerText().then(s=>s.includes('Articles')));
 await toggle.click();ck('sidebar expands without losing the current view',await toggle.getAttribute('aria-expanded')==='true'&&(await sidebar.boundingBox()).width>200&&new URL(p.url()).searchParams.get('view')==='posts');
 ck('VoilaVoila wordmark uses the requested gradient',await p.locator('.admin-brand .vv-brand-word').innerText()==='VoilaVoila !'&&await p.locator('.admin-brand .vv-brand-word').evaluate(n=>getComputedStyle(n).backgroundImage.includes('rgb(201, 169, 99) 80%')));
 await p.screenshot({path:path.join(f.work,'admin-expanded-internal.png'),fullPage:true});await toggle.click();await p.screenshot({path:path.join(f.work,'admin-collapsed-internal.png'),fullPage:true});
 await p.goto(f.base+'/admin.php?view=health&lang=en');ck('health and maintenance interface translates to English',await p.locator('h1').innerText().then(s=>s.includes('Health & maintenance'))&&await p.getByRole('button',{name:'Save policy',exact:true}).count()===1);
 await p.locator('#sidebar').getByRole('link',{name:'SEO settings',exact:true}).click();await p.locator('.seo-fields summary').click();ck('SEO settings expose native bilingual fields',await p.locator('[name=seo_title_fr]').isVisible()&&await p.locator('[name=seo_description_en]').isVisible());
 for(const width of [390,320]){
  await p.setViewportSize({width,height:844});await p.goto(f.base+'/admin.php?view=health&lang=en');
  ck(width+'px admin fits viewport',await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
  ck(width+'px desktop control yields to mobile drawer',!await toggle.isVisible()&&await p.locator('.menu-toggle').isVisible());
  await p.locator('.menu-toggle').click();ck(width+'px mobile drawer opens despite saved desktop collapse',await sidebar.getByRole('link',{name:'Overview',exact:true}).isVisible()&&await p.locator('.menu-toggle').getAttribute('aria-expanded')==='true');
  await p.keyboard.press('Escape');ck(width+'px Escape closes drawer and returns focus',await p.locator('.menu-toggle').getAttribute('aria-expanded')==='false'&&await p.locator('.menu-toggle').evaluate(n=>n===document.activeElement));
  if(width===390)await p.screenshot({path:path.join(f.work,'admin-mobile-internal.png'),fullPage:true});
 }
 const nojs=await browser.newContext({javaScriptEnabled:false,viewport:{width:1440,height:1000}}),n=await nojs.newPage();await n.goto(f.base+'/login.php');await n.locator('[name=password]').fill(f.password);await n.getByRole('button',{name:'Ouvrir mon espace'}).click();await n.waitForURL('**/admin.php*');ck('admin remains navigable without JavaScript',await n.locator('#sidebar').getByRole('link',{name:'Articles',exact:true}).isVisible());await nojs.close();
 ck('no unhandled browser errors',errors.length===0);ck('no CSP violations in admin',csp.length===0);
 fs.writeFileSync(f.report,JSON.stringify({passed:true,checks,errors,csp},null,2));await browser.close();
})().catch(async e=>{console.error(e.stack);fs.writeFileSync(f.report,JSON.stringify({passed:false,checks,errors,csp,error:e.message},null,2));if(browser)await browser.close();process.exit(1);});
