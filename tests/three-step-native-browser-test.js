const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const path = require('node:path');
const { pathToFileURL } = require('node:url');
(async () => {
 const browser = await chromium.launch({headless:true, channel:process.env.HTR_BROWSER_CHANNEL || undefined});
 try {
  const page = await browser.newPage({viewport:{width:375,height:900}});
  const errors=[]; page.on('pageerror', e=>errors.push(e.message));
  await page.addInitScript(() => {
   window.HTR_RR={browserKey:'fixture',contactMode:'plugin',ajaxUrl:'/ajax'};
   class Address extends HTMLElement {} customElements.define('gmp-place-autocomplete',Address);
   window.google={maps:{async importLibrary(name){
    if(name==='places')return {PlaceAutocompleteElement:Address};
    if(name==='maps')return {Map:class{addListener(){} setCenter(){} setZoom(){}}};
    if(name==='marker')return {Marker:class{addListener(){} setPosition(){}}};
   }}};
   window.fetch=async(url,opts)=>{
    const action=new URL(url,location.href).searchParams.get('action').replace('htr_rr_','');
    if(action==='preview')return {ok:true,json:async()=>({ok:true,measured:false,message:'No imagery fixture'})};
    window.submitted=JSON.parse(opts.body);
    return {ok:true,json:async()=>({ok:true,report:{token:'fixture',url:'https://example.test/report',measured:false,address:'123 Main St',firstName:'Test',disclaimer:'Fixture only'}})};
   };
  });
  await page.goto(pathToFileURL(path.join(__dirname,'.instant-plugin-preview.html')).href);
  assert.equal(await page.locator('[data-quote-step]').count(),3);
  await page.locator('[data-quote-find]').click();
  assert.equal(await page.locator('[data-card="4"]').isVisible(),false,'Invalid property cannot advance');
  await page.evaluate(()=>{
   const event=new Event('gmp-select');event.placePrediction={toPlace:()=>({formattedAddress:'123 Main St, TX',location:{lat:()=>29.4,lng:()=>-98.4},addressComponents:[{types:['street_number'],longText:'123'}],fetchFields:async()=>{}})};
   document.querySelector('gmp-place-autocomplete').dispatchEvent(event);
  });
  await page.locator('#htr-rr-confirm').click();
  await page.locator('[data-mat=asphalt]').focus();
  await page.keyboard.press('ArrowRight');
  await page.locator('[data-quote-contact]').click();
  assert.equal(await page.locator('[data-quote-step="2"]').getAttribute('aria-current'),'step');
  await page.locator('input[name=first_name]').fill('Test');
  await page.locator('input[name=last_name]').fill('Fixture');
  await page.locator('input[name=email]').fill('test@example.test');
  await page.locator('input[name=phone]').fill('2105550100');
  for(const name of ['sms_service','sms_marketing'])assert.equal(await page.locator(`input[name=${name}]`).isChecked(),false);
  await page.locator('[data-quote-back="3"]').click();
  await page.locator('[data-quote-contact]').click();
  assert.equal(await page.locator('input[name=email]').inputValue(),'test@example.test','Back preserves contact');
  await page.locator('#htr-rr-submit').click();
  await page.waitForFunction(()=>document.querySelector('#htr-rr-result').textContent.length>0);
  assert.equal(await page.locator('[data-quote-step="3"]').getAttribute('aria-current'),'step');
  assert.equal(await page.evaluate(()=>window.submitted.sms_marketing),false);
  assert.deepEqual(errors,[]);
  console.log('Native quote browser checks passed: validation, radio keyboard, retained contact, unchecked consents, three steps and no-imagery report.');
 } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1});