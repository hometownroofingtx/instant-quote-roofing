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
(async()=>{const browser=await chromium.launch({headless:true,channel:process.env.HTR_BROWSER_CHANNEL||undefined});try{
 for(const width of [375,1366]){
  const page=await browser.newPage({viewport:{width,height:900}});const errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.goto(pathToFileURL(path.join(__dirname,'.report-contact-preview.html')).href);
  await page.evaluate(()=>{
   const pg=document.querySelector('.pg');[...pg.children].forEach(section=>{if(!section.matches('.report-section--header,.report-section--footer'))section.remove();});
   pg.insertAdjacentHTML('beforeend','<section><h2>Page one body</h2><p>Report content preserved on page one.</p></section><section style="break-before:page"><h2>Page two body</h2><p>Report content preserved on page two.</p></section>');
   window.addEventListener('beforeprint',()=>{window.printMetrics={bands:document.querySelectorAll('.report-print-band').length,footerDirection:getComputedStyle(document.querySelector('.report-print-footer .report-contact-bar')).flexDirection,footerBottom:Math.round(innerHeight-document.querySelector('.report-fixed-footer').getBoundingClientRect().bottom)};});
  });
  await page.emulateMedia({media:'print'});
  const pdf=await page.pdf({printBackground:true,preferCSSPageSize:true});
  const pages=(pdf.toString('latin1').match(/\/Type\s*\/Page\b/g)||[]).length;assert.equal(pages,2,'Controlled fixture has two pages');
  const text=pdfText(pdf);
  assert.equal(text.split('Roof Measurement Report').length-1,2,'Header printed on both pages');
  assert.equal(text.split('hometownroofingtx.com').length-1,2,'Contact footer printed on both pages');
  assert.equal(text.split('Test disclaimer').length-1,1,'Disclaimer appears once across the entire PDF');
  assert.deepEqual(await page.evaluate(()=>window.printMetrics),{bands:3,footerDirection:'row',footerBottom:0});
  assert.equal(await page.locator('.report-print-band').count(),0,'Print clones cleaned up afterward');
  if(width===375){
   await page.evaluate(()=>document.querySelector('.pg').lastElementChild.remove());
   const shortPdf=await page.pdf({printBackground:true,preferCSSPageSize:true});
   assert.equal((shortPdf.toString('latin1').match(/\/Type\s*\/Page\b/g)||[]).length,1,'Short report stays one page');
   assert(pdfText(shortPdf).includes('hometownroofingtx.com'),'Short report footer retained');
   assert.equal(await page.evaluate(()=>window.printMetrics.footerBottom),0,'Short report footer anchored at bottom');
  }
  assert.deepEqual(errors,[]);fs.writeFileSync(path.join(__dirname,`.report-print-${width}.pdf`),pdf);await page.close();
 }
 for(const width of [375,1366]){
  const page=await browser.newPage({viewport:{width,height:900}});
  await page.route('https://maps.googleapis.com/**',route=>route.fulfill({contentType:'image/png',body:Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII=','base64')}));
  await page.goto(pathToFileURL(path.join(__dirname,'.report-full-preview.html')).href);
  assert.equal(await page.locator('#htr-rr-iso svg').count(),1);
  assert.equal(await page.locator('#htr-rr-planbox svg').count(),1);
  assert.equal(await page.locator('.img').evaluate(image=>image.complete&&image.naturalWidth>0),true,'Aerial image loads before print');
  await page.emulateMedia({media:'print'});
  const pdf=await page.pdf({printBackground:true,preferCSSPageSize:true});const text=pdfText(pdf);
  assert(text.includes('Illustration'),'Actual diagram illustration labels survive PDF rendering');
  assert((pdf.toString('latin1').match(/\/Subtype\s*\/Image\b/g)||[]).length>=2,'PDF contains aerial and branding images');
  for(const label of ['Aerial view','3D roof diagram','Roof plan view','Roof faces measured','2,500'])assert(text.includes(label),label+' remains in full PDF at '+width);
  console.log('Full report print content retained at '+width);
  fs.writeFileSync(path.join(__dirname,`.report-print-full-${width}.pdf`),pdf);await page.close();
 }
 {
  const page=await browser.newPage({viewport:{width:1366,height:900}});
  await page.goto(pathToFileURL(path.join(__dirname,'.report-full-preview.html')).href);
  await page.evaluate(()=>{
   const pg=document.querySelector('.pg'),group=document.querySelector('.report-face-details');
   group.querySelector('tbody').innerHTML=Array.from({length:6},(_,i)=>'<tr><td>Face '+(i+1)+'</td><td>500</td><td>6/12</td></tr>').join('');
   group.style.minHeight='190px';
   [...pg.children].filter(node=>!node.matches('.report-section--header,.report-section--footer')).forEach(node=>node.remove());
   const before=document.createElement('section');before.style.height='740px';before.textContent='Earlier report content';pg.insertBefore(before,pg.querySelector('.report-section--footer'));pg.insertBefore(group,pg.querySelector('.report-section--footer'));
  });
  await page.emulateMedia({media:'print'});
  const pdf=await page.pdf({printBackground:true,preferCSSPageSize:true});const pages=pdfText(pdf).split('\n');
  assert(!pages[0].includes('Roof faces measured'),'Heading does not get stranded on page one');
  assert(pages[1].includes('Roof faces measured')&&pages[1].includes('Face 1')&&pages[1].includes('Face 6'),'Heading and all six rows move together to page two');
  await page.close();console.log('Automatic page-break check passed: measured roof heading and all six rows stay together on page two.');
 }
 console.log('Two-page PDF checks passed at mobile/desktop widths: header/footer text repeats twice, horizontal footer retained, no extra pages and print cleanup works.');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1});