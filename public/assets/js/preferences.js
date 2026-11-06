/* Appearance and presentation only. This file never changes financial input values. */
(function(){
 'use strict';
 const root=document.documentElement;
 let config={};try{config=JSON.parse(document.getElementById('nikxor-ui-config')?.textContent||'{}');}catch(_){}
 const media=window.matchMedia('(prefers-color-scheme: dark)');
 const syncSystem=()=>root.dataset.systemDark=String(media.matches);syncSystem();media.addEventListener?.('change',syncSystem);
 const sets={theme:['horizon','coreui','adminlte','materio'],palette:['crimson_teal','matrix_mint','ultramarine','titanium_gold'],effect:['none','glass','clay'],mode:['light','dark','system']};
 const apply=p=>Object.keys(sets).forEach(k=>{if(sets[k].includes(p[k]))root.dataset[k]=p[k];});
 const escape=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 function formatNumber(value,decimals=2,trim=false){
  const style=config.number_format||'locale';let locale='en-US';
  if(style==='persian'||(style==='locale'&&document.documentElement.lang!=='en'))locale='fa-IR';
  return Number(value).toLocaleString(locale,{minimumFractionDigits:trim?0:decimals,maximumFractionDigits:decimals,useGrouping:style!=='plain'});
 }
 function currencyLabel(code){return code+(config.currency_display==='code_symbol'?' '+({AFN:'؋',USD:'$',AED:'د.إ',EUR:'€'}[code]||''):'');}
 function toast(text){let el=document.getElementById('uiToast');if(!el){el=document.createElement('div');el.id='uiToast';el.className='ui-toast';el.role='status';document.body.appendChild(el);}el.textContent=text;clearTimeout(toast.timer);toast.timer=setTimeout(()=>el.remove(),4500);}
 window.NikxorUI={apply,formatNumber,currencyLabel,escape,toast,formatCurrency:(value,code,decimals=2)=>formatNumber(value,decimals)+' '+currencyLabel(code)};
 function init(){
  document.querySelectorAll('input[type="password"]').forEach(el=>el.dataset.sensitive='1');
  document.querySelectorAll('[data-toggle-password]').forEach(btn=>btn.addEventListener('click',()=>{
   const input=document.getElementById(btn.dataset.togglePassword);if(!input)return;const visible=input.type==='password';input.type=visible?'text':'password';input.dataset.sensitive='1';btn.setAttribute('aria-pressed',String(visible));btn.setAttribute('aria-label',visible?btn.dataset.hideLabel:btn.dataset.showLabel);const icon=btn.querySelector('i');if(icon)icon.className=visible?'fa-regular fa-eye-slash':'fa-regular fa-eye';
  }));
  const appearance=document.getElementById('appearanceForm');
  if(appearance)appearance.addEventListener('change',()=>{const values=Object.fromEntries(new FormData(appearance));apply(values);const notice=document.getElementById('appearancePreviewNote');if(notice)notice.hidden=false;});
  document.querySelectorAll('[data-toggle-mode]').forEach(btn=>btn.addEventListener('click',async()=>{
   const before=root.dataset.mode;const dark=before==='dark'||(before==='system'&&media.matches);const mode=dark?'light':'dark';apply({mode});
   if(appearance){appearance.elements.mode.value=mode;appearance.dispatchEvent(new Event('change'));return;}
   if(btn.dataset.public==='1'){try{localStorage.setItem('nikxor_login_mode',mode);}catch(_){}return;}
   btn.disabled=true;
   try{
    const body=new URLSearchParams({mode,csrf_token:document.querySelector('meta[name="csrf-token"]').content});
    const response=await fetch(config.theme_url,{method:'POST',body,credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}});const data=await response.json();
    if(data.auth_required){location.href=data.login_url;return;}
    if(!response.ok||!data.success)throw new Error();config={...config,...data.preferences};apply(data.preferences);
   }catch(_){apply({mode:before});toast(config.save_error||'Could not save. Please refresh.');}finally{btn.disabled=false;}
  }));
  document.querySelectorAll('form').forEach(form=>{if(form.method.toLowerCase()!=='post')return;form.addEventListener('submit',event=>{if(event.defaultPrevented)return;if(form.dataset.submitting==='1'){event.preventDefault();return;}form.dataset.submitting='1';form.setAttribute('aria-busy','true');});});
  window.addEventListener('pageshow',()=>document.querySelectorAll('form[data-submitting]').forEach(form=>{delete form.dataset.submitting;form.removeAttribute('aria-busy');}));
  const logo=document.getElementById('businessLogo');let objectUrl;
  logo?.addEventListener('change',()=>{const file=logo.files?.[0],image=document.getElementById('logoPreviewImage');if(!file||!image||!['image/png','image/jpeg','image/webp'].includes(file.type)||file.size>3*1024*1024)return;if(objectUrl)URL.revokeObjectURL(objectUrl);objectUrl=URL.createObjectURL(file);image.src=objectUrl;image.hidden=false;document.getElementById('logoPlaceholder')?.setAttribute('hidden','');});
  if(config.auto_dismiss==='on')document.querySelectorAll('.alert-success[data-flash]').forEach(el=>setTimeout(()=>el.remove(),7000));
  const sidebar=document.querySelector('.app-sidebar'),menuToggle=document.getElementById('mobileMenuToggle');
  if(sidebar&&menuToggle){const mobile=matchMedia('(max-width:900px)');const sync=()=>{const open=sidebar.classList.contains('sidebar-open');menuToggle.setAttribute('aria-expanded',String(open));sidebar.inert=mobile.matches&&!open;sidebar.setAttribute('aria-hidden',String(mobile.matches&&!open));};new MutationObserver(sync).observe(sidebar,{attributes:true,attributeFilter:['class']});mobile.addEventListener?.('change',sync);sync();}
  // Give existing dialogs keyboard focus without replacing their business-specific handlers.
  document.querySelectorAll('.modal-backdrop,.feature-modal-backdrop').forEach(modal=>{
   modal.setAttribute('role','dialog');modal.setAttribute('aria-modal','true');let wasOpen=false,previous;
   new MutationObserver(()=>{const open=getComputedStyle(modal).display!=='none';if(open&&!wasOpen){previous=document.activeElement;const target=modal.querySelector('input:not([type="hidden"]):not([disabled]),select:not([disabled]),button');target?.focus({preventScroll:true});}else if(!open&&wasOpen&&previous?.isConnected)previous.focus({preventScroll:true});wasOpen=open;}).observe(modal,{attributes:true,attributeFilter:['style','class']});
   modal.addEventListener('keydown',e=>{if(e.key!=='Tab')return;const nodes=[...modal.querySelectorAll('button,a[href],input,select,textarea')].filter(el=>!el.disabled&&el.type!=='hidden'&&el.offsetParent!==null);if(!nodes.length)return;const first=nodes[0],last=nodes[nodes.length-1];if(e.shiftKey&&document.activeElement===first){e.preventDefault();last.focus();}else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first.focus();}});
  });
 }
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();
