(function ($) {
 'use strict';
 const form=document.getElementById('htr-layout-form'); if(!form)return;
 const list=document.getElementById('htr-layout-sections'), input=document.getElementById('htr-layout-json');
 const core=JSON.parse(document.getElementById('htr-layout-core').textContent);
 const initial=JSON.parse(input.value); let sequence=0;
 const styles={},copies={};let previewRows=[],previewRevision=0;initial.forEach(section=>{if(section.style)styles[section.type]=section.style;if(section.copy)copies[section.type]=section.copy;});
 const status=document.getElementById('htr-layout-status');
 function button(label,fn){const b=document.createElement('button');b.type='button';b.className='button';if(label==='Move up'||label==='Move down'){b.classList.add('htr-layout-icon-button');b.setAttribute('aria-label',label);b.title=label;const icon=document.createElement('span');icon.setAttribute('aria-hidden','true');icon.textContent=label==='Move up'?'\u2191':'\u2193';b.appendChild(icon);}else{b.textContent=label;}b.addEventListener('click',fn);return b;}
 function serialize(){input.value=JSON.stringify([...list.children].map(row=>row.dataset.type==='text'?{type:'text',title:row.querySelector('input').value,content:row.querySelector('textarea').value}:{type:row.dataset.type,...(styles[row.dataset.type]?{style:styles[row.dataset.type]}:{}),...(copies[row.dataset.type]?{copy:copies[row.dataset.type]}:{})}));}
 function add(section){
  const row=document.createElement('div');row.className='htr-layout-section';row.dataset.type=section.type;
  const header=document.createElement('div');header.className='htr-layout-section-head';
  const handle=document.createElement('span');handle.className='htr-layout-handle';handle.textContent='\u2630';handle.setAttribute('aria-hidden','true');header.appendChild(handle);
  const label=document.createElement('strong');label.textContent=section.type==='text'?'Custom text':core[section.type];header.appendChild(label);
  const actions=document.createElement('div');actions.className='htr-layout-row-actions';
  function move(direction){const sibling=direction<0?row.previousElementSibling:row.nextElementSibling;if(!sibling)return;if(direction<0)list.insertBefore(row,sibling);else list.insertBefore(sibling,row);status.textContent=label.textContent+' moved '+(direction<0?'up.':'down.');serialize();}
  actions.appendChild(button('Move up',()=>move(-1)));actions.appendChild(button('Move down',()=>move(1)));
  if(section.type==='text')actions.appendChild(button('Remove',()=>{const focus=row.nextElementSibling||row.previousElementSibling;row.remove();serialize();status.textContent='Text section removed.';if(focus)focus.querySelector('button').focus();else document.getElementById('htr-layout-add').focus();}));
  header.appendChild(actions);row.appendChild(header);
  if(section.type==='text'){
   const id='htr-text-'+(++sequence);
   const titleLabel=document.createElement('label');titleLabel.textContent='Heading (optional)';titleLabel.htmlFor=id+'-title';const title=document.createElement('input');title.id=titleLabel.htmlFor;title.type='text';title.maxLength=200;title.value=section.title||'';
   const contentLabel=document.createElement('label');contentLabel.textContent='Report text';contentLabel.htmlFor=id+'-content';const content=document.createElement('textarea');content.id=contentLabel.htmlFor;content.rows=5;content.maxLength=20000;content.value=section.content||'';
   row.append(titleLabel,title,contentLabel,content);
  }
  row.addEventListener('input',serialize);list.appendChild(row);return row;
 }
 const dialog=document.getElementById('htr-report-style-dialog');let styleTarget='header',styleOpener=null;
 const styleDefaults={background:'none',from:'#103b5c',to:'#0875d1',color:'#ffffff',padding:24,align:'left',logo_size:56,logo_id:0,footer_title:'',footer_details:'',footer_design:'plain',contact_phone:'',contact_website:'',contact_area:'',accent:'#d6202b'};
 document.querySelectorAll('[data-report-style-open]').forEach(opener=>opener.addEventListener('click',()=>{
  synchronizePreview();styleTarget=opener.dataset.reportStyleOpen;styleOpener=opener;
  document.getElementById('htr-report-style-title').textContent=styleTarget==='header'?'Report header style':'Report footer style';
  const values={...styleDefaults,...styles[styleTarget]};
  dialog.querySelectorAll('[data-style]').forEach(field=>{field.value=values[field.dataset.style];});
  dialog.querySelector('[data-footer-fields]').hidden=styleTarget!=='footer';
  showLogo(values.logo_id);
  dialog.showModal();
 }));
 function showLogo(id,url){
  const image=dialog.querySelector('[data-logo-preview]');image.hidden=true;image.removeAttribute('src');
  if(url){image.src=url;image.hidden=false;return;}
  if(id&&window.wp&&wp.media){const attachment=wp.media.attachment(id);attachment.fetch().then(()=>{if(Number(dialog.querySelector('[data-style="logo_id"]').value)!==Number(id))return;const sizes=attachment.get('sizes');image.src=sizes&&sizes.thumbnail?sizes.thumbnail.url:attachment.get('url');image.hidden=false;}).catch(()=>{});}
 }
 dialog.querySelector('[data-logo-choose]').addEventListener('click',()=>{
  if(!window.wp||!wp.media){status.textContent='Media Library could not load. Reload this page and try again.';return;}
  const frame=wp.media({title:'Choose report logo',button:{text:'Use this logo'},library:{type:'image'},multiple:false});
  frame.on('select',()=>{const image=frame.state().get('selection').first().toJSON();dialog.querySelector('[data-style="logo_id"]').value=image.id;showLogo(image.id,image.sizes&&image.sizes.thumbnail?image.sizes.thumbnail.url:image.url);});
  frame.on('close',()=>{if(!dialog.open){dialog.showModal();dialog.querySelector('[data-logo-choose]').focus();}});
  dialog.close();frame.open();
 });
 dialog.querySelector('[data-logo-clear]').addEventListener('click',()=>{dialog.querySelector('[data-style="logo_id"]').value=0;showLogo(0);});
 dialog.querySelectorAll('[data-style-close]').forEach(button=>button.addEventListener('click',()=>dialog.close()));
 dialog.addEventListener('close',()=>{if(styleOpener)styleOpener.focus();});
 document.getElementById('htr-style-apply').addEventListener('click',()=>{
  const values={};let valid=true;
  dialog.querySelectorAll('[data-style]').forEach(field=>{if(!field.checkValidity()){field.reportValidity();valid=false;}values[field.dataset.style]=(field.type==='number'||field.dataset.style==='logo_id')?Number(field.value):field.value;});
  if(!valid)return;styles[styleTarget]=values;serialize();dialog.close();
  document.getElementById('htr-layout-preview').click();
 });
 initial.forEach(add);
 $(list).sortable({handle:'.htr-layout-handle',items:'.htr-layout-section',update:()=>{serialize();status.textContent='Report section order updated.';}});
 document.getElementById('htr-layout-add').addEventListener('click',()=>{if(list.children.length>=36){status.textContent='The report supports up to 36 sections.';return;}const row=add({type:'text',title:'',content:''});serialize();row.querySelector('input').focus();});
 document.getElementById('htr-layout-reset').addEventListener('click',()=>{Object.keys(core).forEach(type=>{const row=[...list.children].find(row=>row.dataset.type===type);if(row)list.appendChild(row);});serialize();status.textContent='Built-in section order restored. Custom text kept above the built-in sections.';});
 form.addEventListener('submit',()=>{synchronizePreview();serialize();form.elements.action.value='htr_rr_layout_save';form.removeAttribute('target');});
 document.getElementById('htr-layout-preview').addEventListener('click',()=>{
  synchronizePreview();serialize();previewRows=[...list.children];form.elements.preview_key.value=String(++previewRevision);const frame=document.getElementById('htr-layout-preview-frame');frame.hidden=false;form.target=frame.name;form.elements.action.value='htr_rr_layout_preview';form.submit();form.removeAttribute('target');form.elements.action.value='htr_rr_layout_save';status.textContent='Loading private report preview below...';
 });
 function receivePreviewCopy(event){
  const frame=document.getElementById('htr-layout-preview-frame'),data=event.data;
  if(event.source!==frame.contentWindow||event.origin!==window.location.origin||!data||data.type!=='htr-report-copy'||data.previewKey!==form.elements.preview_key.value||!Number.isInteger(data.index)||typeof data.key!=='string'||typeof data.text!=='string'||data.text.length>20000)return;
  const row=previewRows[data.index];if(!row||!list.contains(row))return;
  if(data.custom&&row.dataset.type==='text'){
   if(data.key==='title')row.querySelector('input').value=data.text.slice(0,200);
   else if(data.key==='content')row.querySelector('textarea').value=data.text;
   else return;
  }else if(!data.custom&&row.dataset.type!=='text'){
   if(data.text.length>5000)return;
   if(row.dataset.type==='footer'&&['footer_title','footer_details'].includes(data.key)){
    styles.footer={...styleDefaults,...styles.footer,[data.key]:data.text};
   }else{copies[row.dataset.type]={...copies[row.dataset.type],[data.key]:data.text};}
  }else return;
  serialize();status.textContent='Preview wording updated. Save report layout to publish these changes.';
 }
 window.addEventListener('message',receivePreviewCopy);
 function synchronizePreview(){
  const frame=document.getElementById('htr-layout-preview-frame');
  try {
   const doc=frame.contentDocument;
   if(!doc||doc.documentElement.dataset.reportPreviewKey!==form.elements.preview_key.value)return;
   doc.querySelectorAll('[data-copy-edited="true"]').forEach(field=>{
    const section=field.closest('[data-report-index]');
    receivePreviewCopy({source:frame.contentWindow,origin:window.location.origin,data:{type:'htr-report-copy',previewKey:form.elements.preview_key.value,index:Number(section.dataset.reportIndex),key:field.dataset.reportCopy||field.dataset.reportField,custom:field.hasAttribute('data-report-field'),text:field.innerText}});
    delete field.dataset.copyEdited;
   });
  }catch(error){/* A failed or cross-origin preview cannot modify editor data. */}
 }
 document.getElementById('htr-layout-preview-frame').addEventListener('load',()=>{if(document.getElementById('htr-layout-preview-frame').hidden)return;status.textContent='Preview loaded. Changes are not saved until you select Save report layout.';});
})(jQuery);