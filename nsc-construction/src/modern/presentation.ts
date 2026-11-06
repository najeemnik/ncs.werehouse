import type {Language} from '../types';
import {labels,translate,locales,numberingSystems,type LabelKey} from './i18n';
const aliases:Record<string,LabelKey>={steelraber:'steel',steelrebar:'steel',steelrecords:'steel',materialsteel:'steel',concreatepours:'concrete',concretepours:'concrete',concreterecords:'concrete',materialconcrete:'concrete',materialsuppliers:'suppliers',fixedassets:'assets',laborrecords:'labor',projectbudgets:'budget',projectpartners:'partners',projectinvestments:'investments',banktransfer:'bank',bank:'bank',cash:'cash',hawalasarafi:'hawala',hawala:'hawala',personal:'personal',personalfunds:'personal',pending:'pendingPayment',connectdrive:'documents'};
export function systemKey(value:string):LabelKey|undefined {
 const normalized=value.replace(/[\s_\/\-]/g,'').toLowerCase();
 if(Object.hasOwn(aliases,normalized))return aliases[normalized];
 return Object.hasOwn(labels,value)?value as LabelKey:undefined;
}
export function localizedSystemValue(language:Language,value:unknown,settings?:any):string {
 const raw=String(value??'—'),key=systemKey(raw);const custom=key?settings?.dashboardDesign?.texts?.[key]?.[language]:undefined;return key?(typeof custom==='string'&&custom.trim()?custom:translate(language,key)):raw;
}
const months={fa:['جنوری','فبروری','مارچ','اپریل','می','جون','جولای','آگست','سپتمبر','اکتوبر','نومبر','دسمبر'],ps:['جنوري','فبروري','مارچ','اپرېل','مې','جون','جولای','اګست','سپتمبر','اکتوبر','نومبر','دسمبر']};
export function displayDate(language:Language,value:string|undefined):string {
 if(!value)return '—';if(!/^\d{4}-\d{2}-\d{2}$/.test(value))return value;
 const date=new Date(value+'T12:00:00Z');if(!Number.isFinite(date.getTime())||date.toISOString().slice(0,10)!==value)return value;
 if(language==='en')return new Intl.DateTimeFormat('en-US',{dateStyle:'medium',timeZone:'UTC',calendar:'gregory'}).format(date);
 const number=(n:number)=>new Intl.NumberFormat(locales[language],{useGrouping:false,numberingSystem:numberingSystems[language]}).format(n);
 return `${number(date.getUTCDate())} ${months[language][date.getUTCMonth()]} ${number(date.getUTCFullYear())}`;
}
function isoDay(date:Date,timezone:string):string {
 const parts=new Intl.DateTimeFormat('en-US',{timeZone:timezone,year:'numeric',month:'2-digit',day:'2-digit',calendar:'gregory',numberingSystem:'latn'}).formatToParts(date);
 const part=(name:string)=>parts.find(p=>p.type===name)!.value;return `${part('year')}-${part('month')}-${part('day')}`;
}
export function todayISO(timezone='Asia/Kabul'):string {return isoDay(new Date(),timezone);}
const zones:Record<string,LabelKey>={'Asia/Kabul':'zoneKabul',UTC:'zoneUTC','Asia/Dubai':'zoneDubai','Europe/London':'zoneLondon','America/New_York':'zoneNewYork','Asia/Karachi':'zoneKarachi','Europe/Berlin':'zoneBerlin'};
export function timezoneLabel(language:Language,zone:string,settings?:any):string {
 if(Object.hasOwn(zones,zone))return localizedSystemValue(language,zones[zone],settings);
 try{const name=new Intl.DateTimeFormat(locales[language],{timeZone:zone,timeZoneName:'longGeneric'}).formatToParts(new Date()).find(p=>p.type==='timeZoneName')?.value;return name&&(language==='en'||/[\u0600-\u06ff]/.test(name))?name:translate(language,'timezone');}catch{return translate(language,'timezone');}
}
export function buttonLabel(language:Language,button:{label:Partial<Record<Language,string>>;action:string}):string {
 return button.label?.[language]?.trim()||translate(language,systemKey(button.action)||'add');
}

export function resolvePreferredLanguage(companyLanguage:unknown,localOverride:unknown):Language {
 const valid=(v:unknown):v is Language=>v==='en'||v==='fa'||v==='ps';
 return valid(localOverride)?localOverride:valid(companyLanguage)?companyLanguage:'en';
}

export function displayTimestamp(language:Language,value:string):string {
 const date=new Date(value);if(!Number.isFinite(date.getTime()))return '—';
 const iso=isoDay(date,'Asia/Kabul');
 const time=clockTime(language,date,'Asia/Kabul',true,false);
 return displayDate(language,iso)+' · '+time;
}

export function clockTime(language:Language,date:Date,timezone:string,hour24=false,seconds=false,settings?:any):string {
 const parts=new Intl.DateTimeFormat('en-GB',{timeZone:timezone,hour:'2-digit',minute:'2-digit',second:'2-digit',hourCycle:'h23',numberingSystem:'latn'}).formatToParts(date);
 const val=(name:string)=>Number(parts.find(p=>p.type===name)?.value||0),h=val('hour');
 const num=(n:number)=>new Intl.NumberFormat(locales[language],{minimumIntegerDigits:2,useGrouping:false,numberingSystem:numberingSystems[language]}).format(n);
 return `${num(hour24?h:h%12||12)}:${num(val('minute'))}${seconds?':'+num(val('second')):''}${hour24?'':' '+localizedSystemValue(language,h>=12?'afternoon':'morning',settings)}`;
}
