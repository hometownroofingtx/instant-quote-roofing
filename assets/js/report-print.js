/* Repeating table bands reserve real space on every printed report page. */
(function(){
 'use strict';let table,placeholder,report,fixedFooter,moved=[];
 function cleanup(){moved.forEach(item=>item.marker.replaceWith(item.node));moved=[];if(placeholder)placeholder.remove();if(table)table.remove();if(fixedFooter)fixedFooter.remove();table=placeholder=report=fixedFooter=null;document.body.classList.remove('report-repeat-print');}
 function prepare(){
  cleanup();report=document.querySelector('.pg');if(!report)return;
  placeholder=document.createComment('report print position');report.before(placeholder);
  table=document.createElement('table');table.className='report-print-table';
  function band(type,tag){
   const source=report.querySelector('.report-section--'+type);if(!source)return;
   const group=document.createElement(tag),row=document.createElement('tr'),cell=document.createElement('td'),holder=document.createElement('div');holder.className='report-print-band report-print-'+type;
   const copy=source.cloneNode(true);copy.querySelectorAll('.report-disclaimer').forEach(node=>node.remove());copy.removeAttribute('data-report-index');copy.querySelectorAll('[id],[contenteditable]').forEach(el=>{el.removeAttribute('id');el.removeAttribute('contenteditable');el.removeAttribute('role');el.removeAttribute('aria-label');});
   holder.appendChild(copy);cell.appendChild(holder);row.appendChild(cell);group.appendChild(row);table.appendChild(group);
  }
  band('header','thead');
  const body=document.createElement('tbody');
  [...report.children].filter(node=>!node.matches('.report-section--header,.report-section--footer')).forEach(node=>{
   const marker=document.createComment('report section position');node.before(marker);moved.push({marker,node});
   const row=document.createElement('tr'),cell=document.createElement('td');cell.appendChild(node);row.appendChild(cell);body.appendChild(row);
  });
  const disclaimer=report.querySelector('.report-disclaimer');
  if(disclaimer){
   const marker=document.createComment('report disclaimer position');disclaimer.before(marker);moved.push({marker,node:disclaimer});
   const row=document.createElement('tr'),cell=document.createElement('td');cell.appendChild(disclaimer);row.appendChild(cell);body.appendChild(row);
  }
  table.appendChild(body);
  band('footer','tfoot');placeholder.after(table);document.body.classList.add('report-repeat-print');
  const footer=table.querySelector('.report-print-footer');
  if(footer){fixedFooter=footer.cloneNode(true);fixedFooter.classList.add('report-fixed-footer');document.body.appendChild(fixedFooter);}
 }
 window.addEventListener('beforeprint',prepare);window.addEventListener('afterprint',cleanup);
})();