'use strict';
const {chromium}=require('playwright'),jsQR=require('jsqr'),crypto=require('node:crypto'),fs=require('node:fs');let browser;
function decode32(text){let bits=0,buffer=0,bytes=[];for(const c of text){buffer=buffer*32+'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'.indexOf(c);bits+=5;while(bits>=8){bits-=8;bytes.push((buffer>>>bits)&255);}buffer&=(1<<bits)-1;}return Buffer.from(bytes);}
function code(secret,step=Math.floor(Date.now()/30000)){const counter=Buffer.alloc(8);counter.writeBigUInt64BE(BigInt(step));const h=crypto.createHmac('sha1',decode32(secret)).update(counter).digest(),offset=h[h.length-1]&15;return String((h.readUInt32BE(offset)&0x7fffffff)%1000000).padStart(6,'0');}
(async()=>{
 const base=process.env.VV_TEST_BASE,pass='OTP_browser_owner_131!',checks=[],errors=[],external=[];
 const launch={headless:true,args:['--no-sandbox']};if(process.env.VV_BROWSER_EXECUTABLE)launch.executablePath=process.env.VV_BROWSER_EXECUTABLE;browser=await chromium.launch(launch);
 const ck=(n,v)=>{if(!v)throw Error(n);checks.push(n);};
 async function page(){const ctx=await browser.newContext({viewport:{width:1280,height:900}}),p=await ctx.newPage();p.setDefaultTimeout(20000);p.on('pageerror',e=>errors.push(e.message));p.on('request',r=>{if(new URL(r.url()).origin!==new URL(base).origin)external.push(r.url());});return p;}
 async function password(p){await p.goto(base+'/login.php');await p.locator('[name=password]').fill(pass);await p.getByRole('button',{name:'Ouvrir mon espace'}).click();}
 const owner=await page();await password(owner);await owner.waitForURL('**/admin.php*');await owner.goto(base+'/admin.php?view=plugin-otp');
 const begin=owner.locator('form:has(input[name=op][value=begin])');await begin.locator('[name=current_password]').fill(pass);await begin.locator('button').click();await owner.waitForLoadState('networkidle');await owner.locator('#otp-qr').waitFor();
 const pixels=await owner.locator('#otp-qr').evaluate(canvas=>({width:canvas.width,height:canvas.height,bytes:Array.from(canvas.getContext('2d').getImageData(0,0,canvas.width,canvas.height).data),uri:canvas.dataset.uri}));
 const qr=jsQR(Uint8ClampedArray.from(pixels.bytes),pixels.width,pixels.height);ck('real generated QR decodes to exact provisioning URI',qr?.data===pixels.uri&&qr.data.startsWith('otpauth://totp/'));
 const secret=new URL(qr.data).searchParams.get('secret');ck('QR uses six-digit SHA1 and 30-second TOTP',new URL(qr.data).searchParams.get('digits')==='6'&&new URL(qr.data).searchParams.get('period')==='30');
 const enable=owner.locator('form:has(input[name=op][value=enable])'),enrolledStep=Math.floor(Date.now()/30000);await enable.locator('[name=otp]').fill(code(secret,enrolledStep));await enable.locator('[name=ack]').check();await enable.locator('[name=current_password]').fill(pass);await enable.locator('button').click();await owner.locator('#otp-recovery-codes').waitFor();
 const codes=(await owner.locator('#otp-recovery-codes').innerText()).trim().split('\n');ck('browser enrollment enables MFA and displays ten recovery codes',codes.length===10);
 await owner.reload();ck('browser refresh hides recovery codes',await owner.locator('#otp-recovery-codes').count()===0);
 const login=await page();await password(login);await login.waitForURL('**/login.php?factor=1*');ck('password opens only the second-factor challenge',await login.locator('[name=otp]').count()===1);
 ck('pending challenge cannot fetch private native page',(await login.request.get(base+'/?preview='+process.env.VV_OTP_PRIVATE_ID)).status()===404);
 await login.locator('[name=otp]').fill('000000'===code(secret)?'111111':'000000');await login.getByRole('button',{name:'Vérifier et ouvrir'}).click();ck('invalid app code stays outside administration',login.url().includes('factor=1')&&await login.locator('[role=alert]').count()===1);
 await login.locator('[name=otp]').fill(codes[0]);await login.getByRole('button',{name:'Vérifier et ouvrir'}).click();await login.waitForURL('**/admin.php*');ck('browser recovery code completes sign-in',await login.locator('h1').innerText()==='Vue d’ensemble.');
 const second=await page();await password(second);await second.waitForURL('**/login.php?factor=1*');await second.locator('[name=otp]').fill(codes[0]);await second.getByRole('button',{name:'Vérifier et ouvrir'}).click();ck('recovery code is single-use across browsers',second.url().includes('factor=1')&&await second.locator('[role=alert]').count()===1);
 while(Math.floor(Date.now()/30000)<=enrolledStep)await new Promise(resolve=>setTimeout(resolve,500));
 await second.locator('[name=otp]').fill(code(secret));await second.getByRole('button',{name:'Vérifier et ouvrir'}).click();await second.waitForURL('**/admin.php*');ck('fresh authenticator code completes sign-in',await second.locator('h1').innerText()==='Vue d’ensemble.');
 await second.setViewportSize({width:390,height:844});await second.goto(base+'/admin.php?view=plugin-otp');ck('mobile OTP management fits viewport',await second.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
 ck('provisioning never calls an external QR service',external.length===0);ck('no JavaScript runtime errors',errors.length===0);
 fs.writeFileSync(process.env.VV_OTP_BROWSER_REPORT,JSON.stringify({checks:checks.length,passed:checks,browser:browser.version(),external_requests:external.length,errors},null,2)+'\n');await browser.close();console.log('PASS',checks.length,'OTP browser checks');
})().catch(async e=>{console.error(e);if(browser)await browser.close();process.exit(1);});
