const {chromium}=require('playwright');
const fs=require('fs');
(async()=>{
 const accounts=JSON.parse(fs.readFileSync(process.env.NSC_TEST_ACCOUNTS,'utf8'));
 const browser=await chromium.launch({executablePath:'/usr/bin/chromium',headless:true,args:['--no-sandbox']});
 const context=await browser.newContext({viewport:{width:1365,height:900}});const page=await context.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
 const base=process.env.NSC_TEST_URL||'http://127.0.0.1:8093';
 await page.goto(base+'/modules/login.php?lang=en');await page.locator('input[name=username]').fill(accounts.owner.username);await page.locator('input[name=password]').fill(accounts.owner.password);await Promise.all([page.waitForURL(u=>!u.pathname.endsWith('login.php')),page.locator('button[type=submit]').click()]);
 const leaks={};
 for(const path of ['/index.php','/modules/portfolio.php','/modules/management.php','/modules/customers.php','/modules/sales.php','/modules/stock.php','/modules/inventory_documents.php','/modules/sales_returns.php','/modules/reports.php','/modules/closing.php','/modules/about.php','/modules/debts.php','/modules/expenses.php','/modules/mudaraba.php','/modules/partners.php','/modules/capital.php','/modules/invoices.php','/modules/settings.php','/modules/treasury.php','/modules/accounting.php','/modules/suppliers.php','/modules/purchase_returns.php','/modules/purchases.php','/modules/fx.php','/modules/corrections.php','/modules/partnership_settlement.php']){
  const r=await page.goto(base+path+'?lang=en');if(r.status()!==200)throw Error(path+' HTTP '+r.status());
  const body=await page.locator('body').innerText();if(/Fatal error|Warning:|Parse error/.test(body))throw Error('PHP failure at '+path);
  const attributes=await page.evaluate(()=>Array.from(document.querySelectorAll('[title],[aria-label],[placeholder]')).filter(e=>e.getClientRects().length).flatMap(e=>['title','aria-label','placeholder'].map(k=>e.getAttribute(k)||'')).filter(s=>/[\u0600-\u06ff]/.test(s)));const lines=body.split('\n').filter(x=>/[\u0600-\u06ff]/.test(x));if(lines.length||attributes.length)leaks[path]=[...lines,...attributes];
  console.log('PASS page '+path);
 }
 fs.writeFileSync('/workspace/scratch/warehouse-tests/english-leaks.json',JSON.stringify(leaks,null,2));
 if(Object.keys(leaks).length)throw Error('Untranslated interface labels found: '+Object.keys(leaks).join(', '));
 await page.goto(base+'/modules/management.php?lang=da');await page.screenshot({path:'/workspace/scratch/warehouse-tests/management.png',fullPage:false});
 await page.setViewportSize({width:390,height:844});await page.goto(base+'/modules/customers.php?lang=da');await page.screenshot({path:'/workspace/scratch/warehouse-tests/mobile.png',fullPage:false});
 const overflow=await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+2);if(overflow)throw Error('Mobile horizontal overflow');
 if(errors.length)throw Error('Browser errors: '+errors.join('; '));
 console.log('PASS no uncaught browser errors; mobile fits viewport');
 const denied=await context.request.get(base+'/admin/office_2_api.php?view=users');if(denied.status()!==403)throw Error('Owner accessed Office 2');console.log('PASS owner blocked from central API');
 await browser.close();
})().catch(e=>{console.error(e.message);process.exit(1)});
