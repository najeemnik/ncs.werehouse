const {chromium}=require('playwright');
const fs=require('fs');
const assert=require('node:assert/strict');
(async()=>{
 const accounts=JSON.parse(fs.readFileSync(process.env.NSC_TEST_ACCOUNTS,'utf8'));
 const base=process.env.NSC_TEST_URL||'http://127.0.0.1:8093';
 const browser=await chromium.launch({executablePath:'/usr/bin/chromium',headless:true,args:['--no-sandbox']});
 try {
  const context=await browser.newContext({viewport:{width:1440,height:900}}),page=await context.newPage(),errors=[];
  page.on('pageerror',e=>errors.push(e.message));
  await page.goto(base+'/modules/login.php?lang=en');
  await page.locator('[name=username]').fill(accounts.owner.username);
  await page.locator('[name=password]').fill(accounts.owner.password);
  await Promise.all([page.waitForURL(u=>!u.pathname.endsWith('login.php')),page.locator('button[type=submit]').click()]);
  for(const lang of ['en','da','ps']) {
   await page.goto(base+'/modules/customers.php?lang='+lang);
   for(const width of [320,360,390,480,640,768,900,901,1024,1199,1280,1440]) {
    await page.setViewportSize({width,height:844});
    await page.waitForTimeout(60);
    const layout=await page.evaluate(()=>{
     const h=document.querySelector('.compact-topbar'),r=h.getBoundingClientRect();
     return {height:r.height,overflow:document.documentElement.scrollWidth-innerWidth,controls:[...h.querySelectorAll('button,a,select')].filter(e=>e.getClientRects().length).map(e=>{const b=e.getBoundingClientRect();return {left:b.left,right:b.right,top:b.top,bottom:b.bottom};}),header:{left:r.left,right:r.right,top:r.top,bottom:r.bottom}};
    });
    assert(layout.height<=72,`${lang}/${width}: header too tall ${layout.height}`);
    assert(layout.overflow<=2,`${lang}/${width}: page overflow`);
    for(const c of layout.controls)assert(c.left>=layout.header.left-1&&c.right<=layout.header.right+1&&c.top>=layout.header.top&&c.bottom<=layout.header.bottom,`${lang}/${width}: control outside header`);
    if(width<=900) {
     await page.locator('#headerToolsToggle').click();
     const r=await page.locator('#headerToolsPanel').boundingBox();
     assert(r&&r.x>=0&&r.x+r.width<=width+1,`${lang}/${width}: tools outside screen`);
     assert.equal(await page.locator('#headerToolsToggle').getAttribute('aria-expanded'),'true');
     await page.locator('#langDropdownBtn').click();
     assert(await page.locator('#langDropdownMenu').isVisible());
     await page.keyboard.press('Escape');
     assert.equal(await page.locator('#langDropdownBtn').getAttribute('aria-expanded'),'false');
     assert.equal(await page.locator('#headerToolsToggle').getAttribute('aria-expanded'),'true');
     await page.keyboard.press('Escape');
     assert.equal(await page.locator('#headerToolsToggle').getAttribute('aria-expanded'),'false');
     assert(await page.locator('#headerToolsToggle').evaluate(e=>e===document.activeElement));
    }else assert(await page.locator('#headerReportCurrency').isVisible());
   }
   console.log('PASS '+lang+': 12 widths, one compact row, all controls fit, disclosures and Escape');
  }
  await page.setViewportSize({width:390,height:844});
  await page.goto(base+'/modules/customers.php?lang=en&per_page=25&page=1');
  await page.locator('#headerToolsToggle').focus();await page.keyboard.press('ArrowDown');
  assert(await page.locator('#headerReportCurrency').evaluate(e=>e===document.activeElement));
  await page.locator('#langDropdownBtn').focus();await page.keyboard.press('ArrowDown');
  assert(await page.locator('#langDropdownMenu a').first().evaluate(e=>e===document.activeElement));
  await page.keyboard.press('Escape');await page.keyboard.press('Escape');
  await page.locator('#headerToolsToggle').click();await page.locator('.page-title h2').click();
  assert.equal(await page.locator('#headerToolsToggle').getAttribute('aria-expanded'),'false');
  await page.locator('#headerToolsToggle').click();
  await page.setViewportSize({width:1280,height:900});
  assert(await page.locator('#headerReportCurrency').isVisible());
  await page.setViewportSize({width:390,height:844});
  assert.equal(await page.locator('#headerToolsToggle').getAttribute('aria-expanded'),'false');
  await page.locator('#mobileMenuToggle').click();
  assert(await page.locator('.app-sidebar').evaluate(e=>e.classList.contains('sidebar-open')));
  await page.locator('#sidebarCloseBtn').click();
  await page.locator('#headerToolsToggle').click();await page.locator('#langDropdownBtn').click();
  const link=page.locator('#langDropdownMenu a').filter({hasText:'Dari'});
  assert.equal(new URL(await link.getAttribute('href'),base).searchParams.get('per_page'),'25');
  assert(!/[\u0600-\u06ff]/.test(await page.locator('.compact-topbar').innerText()),'English header contains translated text');
  await link.click();await page.waitForURL(u=>u.searchParams.get('lang')==='da');
  assert.equal(await page.locator('html').getAttribute('dir'),'rtl');
  assert.equal(new URL(page.url()).searchParams.get('per_page'),'25');
  await page.locator('#headerToolsToggle').click();await page.locator('#langDropdownBtn').click();
  await page.locator('#langDropdownMenu a').filter({hasText:'English'}).click();
  await page.waitForURL(u=>u.searchParams.get('lang')==='en');
  assert.equal(await page.locator('html').getAttribute('dir'),'ltr');
  await page.locator('#headerToolsToggle').click();
  console.log('PASS keyboard, resize, sidebar and real language switching');
  const select=page.locator('#headerReportCurrency'),before=await select.inputValue();
  const other=await select.locator('option').evaluateAll((items,value)=>items.find(e=>e.value!==value)?.value,before);
  if(other) {
   await Promise.all([page.waitForURL(u=>u.searchParams.get('report_currency')===other),select.selectOption(other)]);
   assert.equal(new URL(page.url()).searchParams.get('per_page'),'25');
   assert.equal(await select.inputValue(),other);
   await page.locator('#headerToolsToggle').click();
  }else await page.locator('#headerToolsToggle').click();
  console.log('PASS currency selection');
  const themeResponse=page.waitForResponse(r=>r.url().includes('/includes/change_theme.php')&&r.request().method()==='POST');
  await page.locator('[data-toggle-mode]').click();assert((await themeResponse).ok());
  await page.waitForFunction(()=>!document.querySelector('[data-toggle-mode]').disabled);
  await page.waitForTimeout(180);
  await page.screenshot({path:'/workspace/scratch/warehouse-tests/header-mobile.png'});
  await page.locator('.page-title h2').click();
  await page.screenshot({path:'/workspace/scratch/warehouse-tests/header-mobile-closed.png'});
  await page.setViewportSize({width:1365,height:900});
  await page.waitForTimeout(200);
  await page.screenshot({path:'/workspace/scratch/warehouse-tests/header-desktop.png'});
  console.log('PASS theme save');
  await page.locator('a.header-sale').click();await page.waitForURL(u=>u.pathname.endsWith('/modules/sales.php')); 
  assert.equal(await page.locator('.compact-topbar').count(),1);
  await page.setViewportSize({width:390,height:844});await page.locator('#headerToolsToggle').click();
  const lockResponse=page.waitForResponse(r=>r.url().includes('/includes/screen_lock_action.php'));
  await page.locator('[data-header-lock]').click();
  assert((await lockResponse).ok());assert(await page.locator('#nikxorScreenLockOverlay').isVisible());
  assert.equal(await page.locator('#headerToolsToggle').getAttribute('aria-expanded'),'false');
  assert.deepEqual(errors,[]);
  console.log('PASS keyboard, focus, outside click, resize, sidebar, language URLs, English labels, currency, theme, quick sale and server screen lock');
 } finally {await browser.close();}
})().catch(e=>{console.error(e.stack);process.exit(1)});
