(function(){'use strict';const button=document.getElementById('estimate-download');if(!button)return;button.addEventListener('click',async()=>{if(button.disabled)return;button.disabled=true;button.textContent='Preparing print...';try{await Promise.all([...document.images].map(image=>image.decode?image.decode().catch(()=>{}):Promise.resolve()));if(document.fonts)await document.fonts.ready;window.print();}finally{button.disabled=false;button.textContent='Download report';}});})();
(function(){
 'use strict';const previewKey=document.documentElement.dataset.reportPreviewKey;if(!previewKey)return;
 document.querySelectorAll('[data-estimate-copy]').forEach(field=>{
  let timer;
  function notify(){window.parent.postMessage({type:'htr-estimate-copy',previewKey,key:field.dataset.estimateCopy,text:field.innerText},window.location.origin);}
  function update(){field.dataset.copyEdited='true';document.querySelectorAll('[data-estimate-copy]').forEach(other=>{if(other!==field&&other.dataset.estimateCopy===field.dataset.estimateCopy)other.textContent=field.innerText;});clearTimeout(timer);timer=setTimeout(notify,250);}
  field.addEventListener('input',update);field.addEventListener('blur',()=>{clearTimeout(timer);notify();});
  field.addEventListener('click',event=>{if(field.closest('a'))event.preventDefault();});
  field.addEventListener('paste',event=>{event.preventDefault();const text=event.clipboardData.getData('text/plain'),selection=window.getSelection();if(!selection.rangeCount)return;const range=selection.getRangeAt(0);if(!field.contains(range.commonAncestorContainer))return;range.deleteContents();const node=document.createTextNode(text);range.insertNode(node);range.setStartAfter(node);range.collapse(true);selection.removeAllRanges();selection.addRange(range);update();notify();});
 });
})();