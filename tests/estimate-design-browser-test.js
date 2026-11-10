const {chromium}=require('playwright');const fs=require('node:fs');const path=require('node:path');const {pathToFileURL}=require('node:url');const assert=require('node:assert/strict');const zlib=require('node:zlib');
function pdfText(pdf){
 const raw=pdf.toString('latin1'),streams=[];
 for(const match of raw.matchAll(/stream\r?\n([\s\S]*?)\r?\nendstream/g)){
  const bytes=Buffer.from(match[1],'latin1');try{streams.push(zlib.inflateSync(bytes).toString('latin1'));}catch{streams.push(match[1]);}
 }
 const cmap=new Map();
 for(const stream of streams){
  for(const block of stream.matchAll(/beginbfchar([\s\S]*?)endbfchar/g))for(const m of block[1].matchAll(/<([0-9a-f]+)>\s*<([0-9a-f]+)>/gi))cmap.set(parseInt(m[1],16),String.fromCharCode(parseInt(m[2],16)));
  for(const block of stream.matchAll(/beginbfrange([\s\S]*?)endbfrange/g))for(const m of block[1].matchAll(/<([0-9a-f]+)>\s*<([0-9a-f]+)>\s*<([0-9a-f]+)>/gi))for(let i=parseInt(m[1],16);i<=parseInt(m[2],16);i++)cmap.set(i,String.fromCharCode(parseInt(m[3],16)+i-parseInt(m[1],16)));
 }
 return streams.filter(s=>s.includes(' Tj')||s.includes(' TJ')).map(s=>[...s.matchAll(/<([0-9a-f]+)>/gi)].map(m=>m[1].match(/.{4}/g)?.map(c=>cmap.get(parseInt(c,16))||'').join('')||'').join('')).join('\n');
}
(async()=>{const b=await chromium.launch({headless:true,channel:process.env.HTR_BROWSER_CHANNEL||undefined});try{
 for(const width of [375,1366]){
  const p=await b.newPage({viewport:{width,height:1000}});const errors=[];p.on('pageerror',e=>errors.push(e.message));
  await p.route('https://maps.googleapis.com/**',route=>route.fulfill({contentType:'image/svg+xml',body:'<svg xmlns="http://www.w3.org/2000/svg" width="640" height="360"><rect width="640" height="360" fill="#e5edf1"/><text x="180" y="180" font-size="22" fill="#526c83">Test imagery placeholder</text></svg>'}));
  await p.goto(pathToFileURL(path.join(__dirname,'.estimate-design-preview.html')).href);
  assert.equal(await p.locator('.estimate-page').count(),2);
  const warrantyHeading=p.locator('.estimate-warranty h2');const originalHeading=await warrantyHeading.textContent();
  await warrantyHeading.evaluate(el=>el.textContent='LIFETIME TRANSFERABLE LABOR WARRANTY');
  async function checkWarrantyRow(){assert.equal(await p.locator('.estimate-warranty').evaluate(el=>{const image=el.querySelector('img').getBoundingClientRect(),copy=el.querySelector('div').getBoundingClientRect();return image.right<=copy.left&&Math.abs(image.top+image.height/2-copy.top-copy.height/2)<2;}),true,'Mascot stays beside and centered on warranty copy');}
  await checkWarrantyRow();await p.emulateMedia({media:'print'});await checkWarrantyRow();await p.emulateMedia({media:null});
  await warrantyHeading.evaluate((el,text)=>el.textContent=text,originalHeading);
  assert.equal(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true,'Mobile has no horizontal overflow');
  await p.screenshot({path:path.join(__dirname,`.estimate-design-${width}.png`),fullPage:true});
  const pdf=await p.pdf({preferCSSPageSize:true,printBackground:true});const count=(pdf.toString('latin1').match(/\/Type\s*\/Page\b/g)||[]).length;
  console.log('Estimate PDF pages at '+width+': '+count);
  assert.equal(count,2,'Standard estimate fits two printed pages');
  const text=pdfText(pdf);for(const phrase of ['Your roof. Your estimate.','$15,000 - $20,000','2,500','Your roof at a glance','Inspect & verify','Test disclaimer'])assert(text.includes(phrase),phrase);
  assert.deepEqual(errors,[]);fs.writeFileSync(path.join(__dirname,`.estimate-design-${width}.pdf`),pdf);
  await p.locator('.estimate-warranty h2').evaluate(el=>el.textContent='LIFETIME TRANSFERABLE LABOR WARRANTY');
  await p.locator('.estimate-warranty p').evaluate(el=>el.textContent="Workmanship protection you can carry forward. Coverage and transfer are subject to Hometown's written warranty terms.");
  await p.locator('.estimate-notes p:last-child').evaluate(el=>el.textContent='This is an estimated price range based on aerial measurements and the roof type you selected. Final pricing may vary after an on-site inspection.');
  const detailedPdf=await p.pdf({preferCSSPageSize:true,printBackground:true});
  assert.equal((detailedPdf.toString('latin1').match(/\/Type\s*\/Page\b/g)||[]).length,2,'Long warranty and production disclaimer keep footer on details page');
  fs.writeFileSync(path.join(__dirname,`.estimate-design-warranty-${width}.pdf`),detailedPdf);
  await p.goto(pathToFileURL(path.join(__dirname,'.estimate-design-long-preview.html')).href);
  const longPdf=await p.pdf({preferCSSPageSize:true,printBackground:true});assert(pdfText(longPdf).includes('35'),'Long table retains last face');
  await p.close();
 }
 console.log('Estimate design browser/PDF checks passed: responsive layout, two-page standard report, actual values and table continuation.');
}finally{await b.close();}})().catch(e=>{console.error(e);process.exitCode=1});
