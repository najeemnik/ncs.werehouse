import {roundMoney} from './money';
import {creditedTotal} from './credits';
import {effectiveAmount} from './partialReversals';
import type {ApartmentUnit,Payment,InstallmentRecord} from '../types';
import {isActivePosted} from './approval';
const cents=roundMoney;
export function saleDate(unit:Pick<ApartmentUnit,'saleDate'|'contractDate'>):string {return unit.saleDate||unit.contractDate||'';}
export function paymentInUnitCurrency(unit:ApartmentUnit,payment:Payment):number {return cents((payment.currency==='AFN'?effectiveAmount(payment)/(payment.exchangeRate||70):effectiveAmount(payment))*(unit.currency==='AFN'?(unit.exchangeRate||70):1));}
export function saleReceipts(unit:ApartmentUnit,payments:Payment[]):Payment[] {return payments.filter(p=>p.projectId===unit.projectId&&p.relatedId===unit.id&&p.relatedType==='apartment_sale'&&isActivePosted(p));}
// Posted payments are authoritative; paid caches and nested histories never post cash.
export function installmentBalances(unit:ApartmentUnit,payments:Payment[]):InstallmentRecord[] {
 if(!unit.approvalStatus)return unit.installments||[];
 const receipts=saleReceipts(unit,payments),direct=new Map<string,number>();let general=0;
 for(const p of receipts){const amount=paymentInUnitCurrency(unit,p);if(p.installmentId)direct.set(p.installmentId,cents((direct.get(p.installmentId)||0)+amount));else general=cents(general+amount);}
 general=Math.max(0,cents(general-(unit.downPayment||0)));
 const result=new Map<string,InstallmentRecord>();
 for(const inst of [...(unit.installments||[])].sort((a,b)=>a.installmentNumber-b.installmentNumber||a.id.localeCompare(b.id))){
  const assigned=direct.get(inst.id)||0,extra=Math.min(general,Math.max(0,inst.amount-assigned));general=cents(general-extra);
  const paid=cents(assigned+extra),remaining=unit.reversal?0:Math.max(0,cents(inst.amount-paid));
  result.set(inst.id,{...inst,paidAmount:paid,remainingAmount:remaining,received:remaining===0,status:remaining===0?'Paid':paid>0?'Partially Paid':'Pending'});
 }
 let credit=creditedTotal(unit);for(const i of [...result.values()].sort((a,b)=>b.installmentNumber-a.installmentNumber||b.id.localeCompare(a.id))){const reduction=Math.min(credit,i.remainingAmount);i.amount=cents(i.amount-reduction);i.remainingAmount=cents(i.remainingAmount-reduction);credit=cents(credit-reduction);i.received=i.remainingAmount===0;i.status=i.received?'Paid':i.paidAmount>0?'Partially Paid':'Pending';}
 return (unit.installments||[]).map(i=>result.get(i.id)!);
}
// Corrections are contracts for the same physical unit, not additional sellable area.
export function inventoryRoot(unit:ApartmentUnit,units:ApartmentUnit[]):ApartmentUnit {
 const seen=new Set<string>();let current=unit;
 while(current.correctionOf&&!seen.has(current.id)){seen.add(current.id);const parent=units.find(u=>u.id===current.correctionOf&&u.projectId===unit.projectId);if(!parent)break;current=parent;}
 return current;
}
export function inventoryUnits(units:ApartmentUnit[]):ApartmentUnit[] {return [...new Map(units.map(u=>{const root=inventoryRoot(u,units);return [root.projectId+':'+root.id,root] as const;})).values()];}
