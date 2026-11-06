import type {JournalEntry} from '../types';
import type {JournalBuildInput} from './journalEngine';
import {sourceGross} from './credits';
import {roundMoney} from './partialReversals';
export function appendCreditEntries(entries:JournalEntry[],input:JournalBuildInput,onlySales=false):void{
 for(const original of [...entries]){
  const c=original.sourceType==='expense'?(original.id===`je-expense-${original.sourceId}-payroll`?'labor':original.id===`je-expense-${original.sourceId}-purchase`?'expenses':null):original.sourceType==='steel'&&original.id===`je-steel-${original.sourceId}-purchase`?'steel':original.sourceType==='concrete'&&original.id===`je-concrete-${original.sourceId}-purchase`?'concrete':original.sourceType==='apartment_sale'&&original.id===`je-apartment_sale-${original.sourceId}-sale`?'apartments':null;
  if(!c||onlySales&&c!=='apartments'||!onlySales&&c==='apartments')continue;const rows=c==='labor'?input.laborRecords:c==='expenses'?input.expenses:c==='steel'?input.steelRecords:c==='concrete'?input.concreteRecords:input.apartments;const row=rows?.find(r=>r.id===original.sourceId&&r.projectId===original.projectId);if(!row?.creditAdjustments?.length)continue;
  const gross=sourceGross(row,c);if(!gross)continue;let prior=0;
  for(const event of row.creditAdjustments){const next=roundMoney(prior+event.amount),change=(value:number)=>roundMoney(roundMoney(value*next/gross)-roundMoney(value*prior/gross));const lines=original.lines.map(l=>{const d=change(l.credit),cr=change(l.debit),du=change(l.creditUSD),cu=change(l.debitUSD),da=change(l.creditAFN),ca=change(l.debitAFN);return {...l,debit:Math.max(0,d)+Math.max(0,-cr),credit:Math.max(0,cr)+Math.max(0,-d),debitUSD:Math.max(0,du)+Math.max(0,-cu),creditUSD:Math.max(0,cu)+Math.max(0,-du),debitAFN:Math.max(0,da)+Math.max(0,-ca),creditAFN:Math.max(0,ca)+Math.max(0,-da)};});
   const sum=(debit:string,credit:string)=>roundMoney(lines.reduce((s,l)=>s+(l as any)[debit]-(l as any)[credit],0));const diff=sum('debit','credit'),du=sum('debitUSD','creditUSD'),da=sum('debitAFN','creditAFN');if(diff||du||da)lines.push({accountCode:'8210',debit:Math.max(0,-diff),credit:Math.max(0,diff),debitUSD:Math.max(0,-du),creditUSD:Math.max(0,du),debitAFN:Math.max(0,-da),creditAFN:Math.max(0,da)} as any);
   entries.push({...original,id:`je-credit-${event.id}-${original.id}`,sourceType:'credit_note',sourceId:event.creditNoteId,date:event.date,time:undefined,description:event.reason||'',lines,totalDebitUSD:roundMoney(lines.reduce((s,l)=>s+l.debitUSD,0)),totalCreditUSD:roundMoney(lines.reduce((s,l)=>s+l.creditUSD,0))});prior=next;
  }
 }
}
