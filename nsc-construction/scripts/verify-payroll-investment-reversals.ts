import assert from 'node:assert/strict';
import {buildJournal,computeAccountBalances,isEntryBalanced} from '../src/utils/journalEngine';
import {computeBudgetActuals} from '../src/utils/budgetUtils';
import {projectMetrics} from '../src/modern/metrics';
import {payrollAmounts} from '../src/utils/payroll';
const reversed=(id:string,date='2026-10-13')=>({id,date,reason:'Wrong record reversed',actorId:'owner',actorName:'Owner',at:'2026-10-13T01:00:00Z'});
const empty=():any=>({projects:[{id:'p',defaultExchangeRate:73.25}],expenses:[],steelRecords:[],concreteRecords:[],laborRecords:[],payments:[],apartments:[],projectInvestments:[]});
const balance=(input:any,code:string)=>computeAccountBalances(buildJournal(input)).find(b=>b.accountCode===code)?.balanceUSD||0;
let checks=0;
for(const currency of ['USD','AFN'])for(const paymentStatus of ['pending','paid'])for(const advance of [0,14.55,70.35]){
 const wage:any={id:'wage',projectId:'p',date:'2026-10-10',workerName:'QA Worker',daysWorked:1,dailyRate:70.35,advanceDeduction:advance,currency,exchangeRate:73.25,paymentStatus,approvalStatus:'posted'};
 const input=empty();input.laborRecords=[wage];const original=buildJournal(input);assert(original.length>=1);wage.reversal=reversed('rev-wage');const all=buildJournal(input);
 assert.deepEqual(all.filter(e=>e.date<'2026-10-13'),original);assert.equal(all.length,original.length*2);for(const e of original){const inverse=all.find(r=>r.reversalOf===e.id)!;assert(inverse);assert.deepEqual(inverse.lines,e.lines.map(l=>({...l,debit:l.credit,credit:l.debit,debitUSD:l.creditUSD,creditUSD:l.debitUSD,debitAFN:l.creditAFN,creditAFN:l.debitAFN})));}
 for(const b of computeAccountBalances(all)){assert.equal(b.balanceUSD,0);assert.equal(b.balanceAFN,0);}assert(all.every(e=>isEntryBalanced(e)));assert(!computeBudgetActuals({...input,projectId:'p',budgets:[]}).has('5600'));checks++;
}
for(const currency of ['USD','AFN'])for(const type of ['initial','additional'])for(const method of ['Cash','Bank','Hawala','Personal']){
 const inv:any={id:'investment',projectId:'p',date:'2026-10-10',amount:1234.56,currency,exchangeRate:73.25,type,paymentMethod:method,approvalStatus:'posted'};
 const input=empty();input.projectInvestments=[inv];const original=buildJournal(input);assert.equal(original.length,1);const equity=type==='initial'?'3100':'3200',cash=method==='Bank'?'1130':method==='Hawala'?'1140':currency==='USD'?'1120':'1110';assert(original[0].lines.some(l=>l.accountCode===cash&&l.debit>0));assert(original[0].lines.some(l=>l.accountCode===equity&&l.credit>0));
 inv.reversal=reversed('rev-investment');assert.deepEqual(buildJournal(input).filter(e=>e.date<'2026-10-13'),original);for(const b of computeAccountBalances(buildJournal(input))){assert.equal(b.balanceUSD,0);assert.equal(b.balanceAFN,0);}checks++;
}
// Reversal must bind to the collection and exact entry, even with colliding source identifiers.
const collision=empty();collision.laborRecords=[{id:'same',projectId:'p',date:'2026-10-10',daysWorked:1,dailyRate:100,advanceDeduction:20,paymentStatus:'paid',currency:'USD',approvalStatus:'posted',reversal:reversed('r-wage')}];collision.expenses=[{id:'same',projectId:'p',date:'2026-10-10',amount:50,currency:'USD',approvalStatus:'posted'}];collision.projectInvestments=[{id:'same',projectId:'p',date:'2026-10-10',amount:200,currency:'USD',type:'initial',approvalStatus:'posted'}];assert.equal(balance(collision,'1500'),50);assert.equal(balance(collision,'3100'),200);assert.equal(balance(collision,'1120'),200);assert(!buildJournal(collision).some(e=>e.reversalOf==='je-expense-same-purchase'));
// Sold-unit allocations undo payroll cost on reversal date, preserving previous reports.
const costing=empty();const wage:any={id:'sold-cost',projectId:'p',date:'2026-10-10',daysWorked:10,dailyRate:10,advanceDeduction:20,paymentStatus:'paid',currency:'USD',approvalStatus:'posted'};costing.laborRecords=[wage];costing.apartments=[{id:'a',projectId:'p',areaM2:100,status:'sold',salePrice:200,currency:'USD',saleDate:'2026-10-11',approvalStatus:'posted'},{id:'b',projectId:'p',areaM2:100,status:'available'}];const prefix=buildJournal(costing).filter(e=>e.date<'2026-10-13');assert.equal(balance(costing,'5050'),50);wage.reversal=reversed('r-cost');assert.deepEqual(buildJournal(costing).filter(e=>e.date<'2026-10-13'),prefix);assert.equal(balance(costing,'5050'),0);assert.equal(balance(costing,'1500'),0);
const corrected:any={...wage,id:'corrected',correctionOf:wage.id,date:'2026-10-13',reversal:undefined,daysWorked:8,advanceDeduction:0,paymentStatus:'pending',approvalStatus:'draft'};costing.laborRecords.push(corrected);assert.equal(balance(costing,'1500'),0);corrected.approvalStatus='posted';assert.equal(balance(costing,'5050'),40);assert.equal(balance(costing,'1500'),40);assert.equal(balance(costing,'2200'),80);assert.equal(balance(costing,'1120'),0);assert.deepEqual(buildJournal(costing).filter(e=>e.date<'2026-10-13'),prefix);
const metrics=projectMetrics({...costing,getProjectJournal:()=>buildJournal(costing)},'p');assert.equal(metrics.committed,80);assert.equal(metrics.paid,0);assert.equal(metrics.payable,80);
assert.deepEqual(payrollAmounts({daysWorked:0.5,dailyRate:0.47,advanceDeduction:0.1}),{gross:0.24,advance:0.1,net:0.14});
console.log(`${checks} currency/status/advance/investment-channel scenarios passed; exact inverses, source collisions, dated sold-unit costs, previous report preservation, independent corrected payroll, current budget and half-cent rounding passed.`);
