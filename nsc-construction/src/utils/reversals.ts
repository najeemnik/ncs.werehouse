import {moneyReversalEvents,roundMoney} from './partialReversals';
import type {FinancialReversal,JournalEntry} from '../types';
import type {JournalBuildInput} from './journalEngine';
export function entryReversal(entry:JournalEntry,input:JournalBuildInput):FinancialReversal|undefined {
 if(entry.sourceType==='expense'&&['payroll','advance-applied','payroll-paid'].some(suffix=>entry.id===`je-expense-${entry.sourceId}-${suffix}`))return input.laborRecords?.find(w=>w.id===entry.sourceId&&w.projectId===entry.projectId)?.reversal;
 if(entry.sourceType==='investment')return input.projectInvestments.find(inv=>inv.id===entry.sourceId&&inv.projectId===entry.projectId&&entry.id===`je-investment-${inv.id}-${inv.type||'initial'}`)?.reversal;
 if(entry.sourceType==='transfer'&&['from','to'].some(s=>entry.id===`je-transfer-${entry.sourceId}-${s}`))return input.projectTransfers?.find(t=>t.id===entry.sourceId&&(t.projectId===entry.projectId||t.toProjectId===entry.projectId))?.reversal;
 const rows=entry.sourceType==='expense'?input.expenses:entry.sourceType==='steel'?input.steelRecords:entry.sourceType==='concrete'?input.concreteRecords:[];
 if(rows.length&&entry.id===`je-${entry.sourceType}-${entry.sourceId}-purchase`)return rows.find(r=>r.id===entry.sourceId&&r.projectId===entry.projectId)?.reversal;
 if(entry.sourceType==='apartment_sale'&&(entry.id===`je-apartment_sale-${entry.sourceId}-sale`||entry.id===`je-apartment_sale-${entry.sourceId}-cogs`||entry.id.startsWith(`je-apartment_sale-${entry.sourceId}-cogs-`)))return input.apartments.find(u=>u.id===entry.sourceId&&u.projectId===entry.projectId)?.reversal;
 if(entry.sourceType==='payment'||entry.sourceType==='apartment_sale'&&entry.id===`je-apartment_sale-${entry.sourceId}-receipt`)return input.payments.find(p=>p.id===entry.sourceId&&p.projectId===entry.projectId)?.reversal;
}
export function appendReversalEntries(entries:JournalEntry[],input:JournalBuildInput):void {
 for(const original of [...entries]){
  const cash=original.sourceType==='investment'?input.projectInvestments.find(r=>r.id===original.sourceId&&r.projectId===original.projectId):original.sourceType==='payment'||original.sourceType==='apartment_sale'&&original.id===`je-apartment_sale-${original.sourceId}-receipt`?input.payments.find(r=>r.id===original.sourceId&&r.projectId===original.projectId):undefined;
  if(cash?.partialReversals?.length){let previous=0;for(const part of moneyReversalEvents(cash)){const next=roundMoney(previous+part.amount),total=roundMoney(cash.amount);const delta=(v:number)=>roundMoney(roundMoney(v*next/total)-roundMoney(v*previous/total));const lines=original.lines.map(l=>({...l,debit:delta(l.credit),credit:delta(l.debit),debitUSD:delta(l.creditUSD),creditUSD:delta(l.debitUSD),debitAFN:delta(l.creditAFN),creditAFN:delta(l.debitAFN)}));entries.push({...original,id:`je-reversal-${part.id}-${original.id}`,sourceType:'reversal',sourceId:part.id,reversalOf:original.id,date:part.date,time:undefined,description:part.reason||'',lines,totalDebitUSD:roundMoney(lines.reduce((s,l)=>s+l.debitUSD,0)),totalCreditUSD:roundMoney(lines.reduce((s,l)=>s+l.creditUSD,0))});previous=next;}continue;}
  const reversal=entryReversal(original,input);if(!reversal)continue;
  entries.push({...original,id:`je-reversal-${reversal.id}-${original.id}`,date:reversal.date,time:undefined,description:reversal.reason||'',sourceType:'reversal',sourceId:reversal.id,reversalOf:original.id,lines:original.lines.map(l=>({...l,debit:l.credit,credit:l.debit,debitUSD:l.creditUSD,creditUSD:l.debitUSD,debitAFN:l.creditAFN,creditAFN:l.debitAFN})),totalDebitUSD:original.totalCreditUSD,totalCreditUSD:original.totalDebitUSD});
 }
}
