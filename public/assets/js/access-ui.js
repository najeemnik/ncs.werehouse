/* Presentation only. PHP enforces every permission again for direct requests. */
'use strict';
(()=>{
 const node=document.getElementById('nsc-access-config');if(!node)return;const config=JSON.parse(node.textContent);
 const module=config.module;if(!module)return;
 const mode=key=>config.services[key]||'hidden';
 function apply(element,key){const state=mode(key);if(state==='enabled')return;if(state==='hidden'){element.hidden=true;return;}
  element.classList.add('service-locked');element.setAttribute('aria-disabled','true');element.title=config.locked;
  if(element.matches('button,input,select,textarea'))element.disabled=true;
  if(element.tagName==='A'){element.removeAttribute('href');element.removeAttribute('onclick');}
 }
 function action(value){if(/delete|remove|reset|clear|factory/.test(value))return'delete';if(/approve|confirm|post|settle|payout|progress|pay_installment/.test(value))return'approve';if(/edit|update|save|toggle|keep/.test(value))return'edit';return config.defaultAction;}
 document.querySelectorAll('[data-permission]').forEach(node=>{const key=node.dataset.permission;apply(node,key);if(mode(key)==='locked'){node.querySelectorAll('input,select,textarea,button').forEach(el=>apply(el,key));const note=document.createElement('p');note.textContent=config.locked;node.prepend(note);}});
 document.querySelectorAll('form').forEach(form=>{
  if((form.getAttribute('method')||'get').toLowerCase()!=='post')return;
  const url=new URL(form.getAttribute('action')||location.href,location.href);
  if(url.pathname!==location.pathname)return;
  const key=module+'.'+action(form.querySelector('[name=action]')?.value||'');
  if(mode(key)==='hidden'){form.hidden=true;return;}
  form.querySelectorAll('button:not([type=button]),input[type=submit]').forEach(button=>apply(button,key));
 });
 document.querySelectorAll('a[href]').forEach(a=>{const url=new URL(a.href,location.href);if(url.origin!==location.origin)return;
  if(url.searchParams.has('export')||url.searchParams.has('download'))apply(a,module+'.export');
  if(url.pathname.includes('/print/'))apply(a,module+'.print');
 });
 document.querySelectorAll('[onclick]').forEach(button=>{
  const handler=button.getAttribute('onclick')||'';
  if(/window\.print/.test(handler))apply(button,module+'.print');
  if(/openEdit|editModal/.test(handler))apply(button,module+'.edit');
  if(/openAdd|openCreate/.test(handler))apply(button,module+'.create');
  // Posted invoice mutations were replaced by documented return operations.
  if(module==='sales'&&/openEditInvoiceModal/.test(handler))button.hidden=true;
 });
 if(['expenses','losses','debts','capital','mudaraba'].includes(module))document.querySelectorAll('form').forEach(form=>{const value=form.querySelector('[name=action]')?.value||'';if(/delete|remove/.test(value))form.hidden=true;});
 if(module==='mudaraba'){
  document.querySelectorAll('form').forEach(form=>{if(['payout_partner','update_progress'].includes(form.querySelector('[name=action]')?.value)){form.querySelectorAll('button[type=submit]').forEach(button=>{button.disabled=true;button.title=config.partnershipPending;});const note=document.createElement('p');note.setAttribute('role','status');note.textContent=config.partnershipPending;const link=document.createElement('a');link.href='partnership_settlement.php';link.textContent=config.partnershipSettlement;note.append(' ',link);form.prepend(note);}});
 }
 if(config.script==='invoices.php'||config.script==='stock.php')document.querySelectorAll('form').forEach(form=>{
  const value=form.querySelector('[name=action]')?.value||'';
  if(['delete_invoice','bulk_delete_invoices','delete','bulk_delete','delete_all'].includes(value))form.hidden=true;
 });
})();
