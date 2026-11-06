/* Denomination selectors only: never change an entered numeric amount when its currency changes. */
(function(){
 'use strict';
 const initialized=new WeakSet();
 const number=value=>Number(String(value??'').replace(/[۰-۹]/g,c=>'۰۱۲۳۴۵۶۷۸۹'.indexOf(c)).replace(/[٠-٩]/g,c=>'٠١٢٣٤٥٦٧٨٩'.indexOf(c)).replace(/[,٬]/g,'').replace('٫','.'));
 function state(form){const controls=form.querySelector('.currency-fields');return controls?{controls,code:form.elements.currency_code,fields:JSON.parse(controls.dataset.moneyFields)}:null;}
 function precision(el,s){const name=el.name.includes('[')?el.name.match(/\[([^\]]+)\]$/)[1]:el.name;return Number(s.fields[name]??2);}
 function inputs(form,s){return Array.from(form.elements).filter(el=>el.tagName==='INPUT'&&Object.keys(s.fields).some(name=>el.name===name||el.name.endsWith('['+name+']')));}
 function labels(form,code){const s=state(form);new Set([...form.querySelectorAll('label,th'),...inputs(form,s).flatMap(el=>Array.from(el.labels||[]))]).forEach(label=>{if(label.closest('.currency-fields')||label.closest('[data-own-currency]'))return;label.childNodes.forEach(n=>{if(n.nodeType===3)n.textContent=n.textContent.replace(/\b(AFN|USD|AED|EUR)\b/g,code);});});form.querySelectorAll('[data-currency-label]').forEach(el=>el.textContent=code);}
 function initialize(form){const s=state(form);if(!s)return;inputs(form,s).forEach(el=>{if(initialized.has(el))return;initialized.add(el);const scale=precision(el,s);el.step=String(10**-scale);if(number(el.min)>0)el.min=String(10**-scale);});if(s.controls.dataset.ready)return;s.controls.dataset.ready='1';labels(form,s.code.value);s.code.addEventListener('change',()=>{labels(form,s.code.value);form.dispatchEvent(new CustomEvent('currencychange',{detail:{code:s.code.value,changedCurrency:true}}));});}
 window.MoneyForm={initialize,number,rate:()=>1,code:form=>form.elements.currency_code?.value||'AFN',setCurrency(form,code){const s=state(form);if(!s)return;s.code.value=code;s.code.dispatchEvent(new Event('change',{bubbles:true}));},setLedger(form,name,value,max){const s=state(form),el=form.elements[name];if(!s||!el)return;el.value=number(value).toFixed(precision(el,s));if(max!==undefined)el.max=String(max);},displayLedger(form,value,scale=2){return window.NikxorUI?NikxorUI.formatNumber(number(value),scale):number(value).toFixed(scale);}};
 function boot(){document.querySelectorAll('form').forEach(initialize);document.dispatchEvent(new CustomEvent('currenciesready'));}
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot);else boot();
})();
