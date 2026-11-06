import type {LocalText} from './designModel';
export const templateKinds=['salesBill','cashReceipt','goodsReceipt','presaleBill'] as const;
export const documentBlocks=['headerBlock','detailsBlock','itemsBlock','notesBlock','signaturesBlock','footerBlock'] as const;
export type DocumentBlock=typeof documentBlocks[number];
export type DocumentTemplate={paper:'A4'|'A5'|'Letter'|'80mm';orientation:'portrait'|'landscape';accent:string;fontSize:number;margin:number;logoWidth:number;order:DocumentBlock[];hidden:DocumentBlock[];texts:Record<string,LocalText>};
export const defaultTemplate:DocumentTemplate={paper:'A4',orientation:'portrait',accent:'#2563eb',fontSize:13,margin:16,logoWidth:80,order:[...documentBlocks],hidden:[],texts:{}};
export const templateFields=['company','project','number','date','party','amount','currency','status','phone','address'];
export function validTemplate(value:any):value is DocumentTemplate{
 const allowed=Object.keys(defaultTemplate);if(!value||typeof value!=='object'||Object.keys(value).some(k=>!allowed.includes(k)))return false;
 if(!['A4','A5','Letter','80mm'].includes(value.paper)||!['portrait','landscape'].includes(value.orientation)||!/^#[0-9a-f]{6}$/i.test(value.accent))return false;
 for(const [key,min,max] of [['fontSize',9,22],['margin',0,35],['logoWidth',0,180]] as const)if(typeof value[key]!=='number'||!Number.isFinite(value[key])||value[key]<min||value[key]>max)return false;
 for(const key of ['order','hidden'])if(!Array.isArray(value[key])||value[key].length>6||new Set(value[key]).size!==value[key].length||value[key].some((x:string)=>!documentBlocks.includes(x as any)))return false;
 if(value.order.length!==6||value.hidden.includes('itemsBlock')||value.hidden.includes('detailsBlock'))return false;
 if(!value.texts||typeof value.texts!=='object'||Array.isArray(value.texts)||Object.keys(value.texts).some(k=>!documentBlocks.includes(k as any)))return false;
 for(const text of Object.values(value.texts) as any[])if(!text||typeof text!=='object'||Array.isArray(text)||Object.keys(text).some(k=>!['en','fa','ps'].includes(k))||Object.values(text).some(v=>typeof v!=='string'||v.length>5000||v.includes('\0')))return false;
 return true;
}
export function getTemplate(settings:any,kind:string):DocumentTemplate{const v=settings?.documentTemplates?.[kind];return validTemplate(v)?v:defaultTemplate;}
export function fillDocumentText(text:string,data:Record<string,string>){return text.replace(/\{\{([a-z]+)\}\}/g,(match,key)=>templateFields.includes(key)?data[key]||'':match);}
