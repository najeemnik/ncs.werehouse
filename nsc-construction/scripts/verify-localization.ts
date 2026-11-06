import assert from 'node:assert/strict';
import {writeFileSync} from 'node:fs';
import {labels,translate,type LabelKey} from '../src/modern/i18n';
import {modules} from '../src/modern/model';
import {CHART_OF_ACCOUNTS,getAccountName} from '../src/data/chartOfAccounts';
import {systemKey,localizedSystemValue,displayDate,displayTimestamp,buttonLabel,timezoneLabel,resolvePreferredLanguage,clockTime} from '../src/modern/presentation';
const langs=['en','fa','ps'] as const;
let checks=0;const check=(condition:boolean,message:string)=>{assert(condition,message);checks++;};
for(const [key,entries] of Object.entries(labels)){
 check(entries.length===3&&entries.every(e=>e.trim().length>0),key+' has all languages');
 check(!/[\u0600-\u06ff]/.test(entries[0]),key+' English has no Arabic-script copy');
}
const columnAliases:Record<string,string>={assetTag:'code',totalAmount:'amount',remainingBalance:'remaining',remainingAmount:'remaining',method:'paymentMethod',areaM2:'area',taxPercentage:'taxRate'};
for(const m of modules){
 check(Object.hasOwn(labels,m.id),m.id+' module label');
 for(const f of m.fields){check(Object.hasOwn(labels,f.label),m.id+' field '+f.key);for(const o of f.options||[])check(!!systemKey(o),m.id+' localizable option '+o);}
 for(const column of m.columns){const key=m.fields.find(f=>f.key===column)?.label||columnAliases[column]||column;check(Object.hasOwn(labels,key),m.id+' column '+column);}
}
for(const account of CHART_OF_ACCOUNTS){for(const lang of langs){check(getAccountName(account.code,lang).trim().length>0,account.code+' '+lang);if(lang==='en')check(!/[\u0600-\u06ff]/.test(getAccountName(account.code,lang)),account.code+' English');}}
for(const [raw,key] of [['steelRaber','steel'],['concreatePours','concrete'],['materialSuppliers','suppliers'],['material_steel','steel'],['pending','pendingPayment'],['Bank Transfer','bank']] as const)for(const lang of langs)check(localizedSystemValue(lang,raw)===translate(lang,key),raw+' '+lang);
for(const raw of ['constructor','__proto__','Customer Garden A','نام اصلی مشتری'])for(const lang of langs)check(localizedSystemValue(lang,raw)===raw,'user data preserved '+raw);
check(systemKey('__proto__')===undefined,'prototype is not a UI key');
check(displayDate('en','2026-10-13')==='Oct 13, 2026','English Gregorian date');
check(displayDate('fa','2026-10-13')==='۱۳ اکتوبر ۲۰۲۶','Afghan Dari Gregorian month and digits');
check(displayDate('ps','2026-10-13')==='۱۳ اکتوبر ۲۰۲۶','Pashto date');
check(displayDate('fa','2026-02-30')==='2026-02-30','invalid date preserved');
check(clockTime('ps',new Date('2026-10-13T12:00:00Z'),'Asia/Kabul').includes('له غرمې وروسته'),'Pashto clock suffix');
check(displayDate('en',undefined)==='—','missing date');
check(displayTimestamp('fa','2026-10-13T20:00:00Z').startsWith('۱۴ اکتوبر ۲۰۲۶'),'Kabul timestamp day rollover');
for(const lang of langs){check(buttonLabel(lang,{label:{en:'Custom English button'},action:'expenses'})===(lang==='en'?'Custom English button':translate(lang,'expenses')),'button has local fallback '+lang);check(timezoneLabel(lang,'Asia/Kabul')===translate(lang,'zoneKabul'),'clock zone '+lang);check(resolvePreferredLanguage(lang,undefined)===lang,'company default '+lang);check(resolvePreferredLanguage('en',lang)===lang,'personal override '+lang);}
check(resolvePreferredLanguage('bad',null)==='en','safe default English');
check(resolvePreferredLanguage('fa','bad')==='fa','invalid override ignored');
if(process.env.NSC_LOCALIZATION_METADATA)writeFileSync(process.env.NSC_LOCALIZATION_METADATA,JSON.stringify({labels,modules,accounts:CHART_OF_ACCOUNTS,columnAliases}));
console.log(`${checks} localization checks passed: complete UI/COA labels, enum aliases, locale fallbacks, dates and per-user language policy.`);
