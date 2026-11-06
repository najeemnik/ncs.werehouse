import {effectiveAmount} from './partialReversals';
import type { FinancialApproval, Payment } from '../types';
// Untagged historical rows preserve their existing balances pending reviewed migration.
export const isPosted=(row:FinancialApproval)=>!row.approvalStatus||row.approvalStatus==='posted';
export const isActivePosted=(row:FinancialApproval)=>isPosted(row)&&!row.reversal;
export const reversibleCollections=['expenses','steel','concrete','payments','apartments','labor','projectInvestments','projectTransfers','creditNotes'];
export const financialCollections=['expenses','steel','concrete','labor','payments','apartments','projectInvestments','projectTransfers','creditNotes'];
export const collectionKey=(key:string)=>({steelRecords:'steel',concreteRecords:'concrete',laborRecords:'labor'} as Record<string,string>)[key]||key;
export function settledAmount(row:any,payments:Payment[]):number{
 if(!row.approvalStatus)return row.paidAmount||row.amountReceived||row.totalReceived||0;
 const types=row.unitNumber!==undefined?['apartment_sale']:row.quantityM3!==undefined?['material_concrete','concrete']:row.kg!==undefined?['material_steel','steel']:['expense','retention_release','tax_remittance'];
 return payments.filter(p=>isActivePosted(p)&&p.projectId===row.projectId&&p.relatedId===row.id&&types.includes(p.relatedType)).reduce((sum,p)=>{
  const dollars=p.currency==='AFN'?effectiveAmount(p)/(p.exchangeRate||70):effectiveAmount(p);
  return sum+(row.currency==='AFN'?dollars*(row.exchangeRate||70):dollars);
 },0);
}
