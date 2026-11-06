/* Sort only the current server page; server filters/pagination remain authoritative. */
'use strict';
(()=>{
 const collator=new Intl.Collator(document.documentElement.lang||'en',{numeric:true,sensitivity:'base'});
 const digits=s=>s.replace(/[۰-۹٠-٩]/g,c=>'۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩'.indexOf(c)%10).replace(/[٬,]/g,'').replace(/٫/g,'.');
 const number=s=>{const text=digits(s.trim());return /^[+-]?(?:\d+(?:\.\d+)?|\.\d+)(?:\s+(?:AFN|USD|AED|EUR|%))?$/.test(text)?Number.parseFloat(text):null;};
 document.querySelectorAll('table.table').forEach(table=>{
  const heads=Array.from(table.querySelectorAll('thead tr:first-child th'));
  heads.forEach((head,index)=>{
   if(head.colSpan!==1||head.querySelector('input,button,a,select')||!head.textContent.trim())return;
   const button=document.createElement('button');button.type='button';button.className='table-sort';button.textContent=head.textContent.trim();head.replaceChildren(button);head.setAttribute('aria-sort','none');
   button.addEventListener('click',()=>{
    const ascending=head.getAttribute('aria-sort')!=='ascending';heads.forEach(h=>h.setAttribute('aria-sort','none'));head.setAttribute('aria-sort',ascending?'ascending':'descending');
    Array.from(table.tBodies).forEach(body=>{
     const rows=Array.from(body.rows),fixed=[],sortable=[];rows.forEach((row,order)=>{if(row.querySelector('th,[colspan]')||row.cells.length!==heads.length)fixed.push(row);else sortable.push({row,order});});
     sortable.sort((a,b)=>{const x=a.row.cells[index].textContent.trim(),y=b.row.cells[index].textContent.trim(),nx=number(x),ny=number(y);const delta=nx!==null&&ny!==null?nx-ny:collator.compare(x,y);return (ascending?delta:-delta)||a.order-b.order;});sortable.forEach(({row})=>body.append(row));fixed.forEach(row=>body.append(row));
    });
   });
  });
 });
})();
