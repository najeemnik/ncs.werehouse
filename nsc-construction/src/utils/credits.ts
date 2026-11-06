import type {FinancialApproval} from '../types';
import {invoiceAmounts} from './withholding';
import {roundMoney} from './partialReversals';
export const creditSourceCollections=['expenses','steel','concrete','apartments','labor'];
export const clientSourceCollection=(c:string)=>({steel:'steelRecords',concrete:'concreteRecords',labor:'laborRecords'} as Record<string,string>)[c]||c;
export function sourceGross(r:any,c=''):number{return roundMoney(c==='labor'?r.daysWorked*r.dailyRate:Number(r.amount??r.totalAmount??r.salePrice??0));}
export function creditedTotal(r:FinancialApproval):number{return roundMoney((r.creditAdjustments||[]).reduce((s,e)=>s+e.amount,0));}
export function adjustedGross(r:any,c=''):number{return Math.max(0,roundMoney(sourceGross(r,c)-creditedTotal(r)));}
export function adjustedInvoiceAmounts(r:any){const a=invoiceAmounts(r),credit=creditedTotal(r);return {...a,gross:adjustedGross(r),net:Math.max(0,roundMoney(a.net-(a.gross?roundMoney(a.net*credit/a.gross):0))),retention:Math.max(0,roundMoney(a.retention-(a.gross?roundMoney(a.retention*credit/a.gross):0))),tax:Math.max(0,roundMoney(a.tax-(a.gross?roundMoney(a.tax*credit/a.gross):0)))};}
export function datedInventoryArea(project:any,fallback:number,date:string):number{const history=project?.inventoryAreaHistory||[];return [...history].filter(e=>e.date<=date).sort((a,b)=>a.date.localeCompare(b.date)).at(-1)?.totalAreaM2??fallback;}

export function adjustedDays(r:any):number{return Math.max(0,Math.round(((r.daysWorked||0)-(r.creditAdjustments||[]).reduce((s:number,e:any)=>s+(e.daysReduction||0),0))*1e8)/1e8);}
