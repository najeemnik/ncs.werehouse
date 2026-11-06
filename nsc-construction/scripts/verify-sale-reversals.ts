import assert from 'node:assert/strict';
import {buildJournal,computeAccountBalances,buildTrialBalance} from '../src/utils/journalEngine';
import {installmentBalances,inventoryUnits} from '../src/utils/apartmentFinance';
import {projectMetrics} from '../src/modern/metrics';
const reversal=(id:string,date:string)=>({id,date,reason:'Canceled duplicate contract',actorId:'owner',actorName:'Owner',at:'2026-10-13T01:00:00Z'});
const sold:any={id:'sale',projectId:'p',unitNumber:'1',floor:1,areaM2:100,salePrice:200,currency:'USD',exchangeRate:70,status:'sold',contractDate:'2026-10-10',approvalStatus:'posted',downPayment:20,installments:[{id:'i1',installmentNumber:1,amount:80,dueDate:'2026-11-01',paidAmount:0,remainingAmount:80,paymentHistory:[],currency:'USD'},{id:'i2',installmentNumber:2,amount:100,dueDate:'2026-12-01',paidAmount:0,remainingAmount:100,paymentHistory:[],currency:'USD'}]};
const other:any={id:'inventory',projectId:'p',unitNumber:'2',floor:1,areaM2:100,status:'available',approvalStatus:'draft'};
const bill=(id:string,date:string,amount:number):any=>({id,date,amount,projectId:'p',currency:'USD',approvalStatus:'posted'});
const pay=(id:string,amount:number,date='2026-10-10',installmentId?:string):any=>({id,amount,date,installmentId,relatedType:'apartment_sale',relatedId:'sale',projectId:'p',currency:'USD',exchangeRate:70,method:'Cash',approvalStatus:'posted'});
const input:any={projects:[{id:'p',defaultExchangeRate:70}],expenses:[bill('b1','2026-10-09',100),bill('b2','2026-10-11',40)],steelRecords:[],concreteRecords:[],apartments:[sold,other],payments:[pay('down',20),pay('installment',30,'2026-10-11','i1')],projectInvestments:[]};
const ledger=()=>buildJournal(input),bal=(code:string)=>computeAccountBalances(ledger()).find(x=>x.accountCode===code)?.balanceUSD||0;
assert.equal(bal('4100'),200);assert.equal(bal('5050'),70);assert.equal(bal('1200'),150);assert.equal(bal('1120'),50);
const historic=ledger().filter(e=>e.date<'2026-10-12');
input.payments.forEach((p:any)=>p.reversal=reversal('r-'+p.id,'2026-10-12'));sold.reversal=reversal('r-sale','2026-10-12');
assert.deepEqual(ledger().filter(e=>e.date<'2026-10-12'),historic);for(const c of ['4100','5050','1200','1120'])assert.equal(bal(c),0,c+' cancels');assert.equal(bal('1500'),140);
const inverses=ledger().filter(e=>e.sourceType==='reversal'&&e.sourceId==='r-sale');assert.equal(inverses.length,3);for(const inv of inverses){const original=ledger().find(e=>e.id===inv.reversalOf)!;assert.deepEqual(inv.lines,original.lines.map(l=>({...l,debit:l.credit,credit:l.debit,debitUSD:l.creditUSD,creditUSD:l.debitUSD,debitAFN:l.creditAFN,creditAFN:l.debitAFN})));}
input.expenses.push(bill('b3','2026-10-13',60));assert.equal(bal('5050'),0,'canceled sale receives no later costs');assert.equal(bal('1500'),200);assert(!ledger().some(e=>e.sourceType==='apartment_sale'&&e.sourceId==='sale'&&e.date==='2026-10-13'));
const afterCancel=ledger().filter(e=>e.date<'2026-10-13');const corrected:any={...sold,id:'correction',correctionOf:'sale',reversal:undefined,contractDate:'2026-10-13',salePrice:220,downPayment:0,installments:[],approvalStatus:'draft'};input.apartments.push(corrected);assert.equal(inventoryUnits(input.apartments).length,2);assert.deepEqual(ledger().filter(e=>e.date<'2026-10-13'),afterCancel);
corrected.approvalStatus='posted';assert.deepEqual(ledger().filter(e=>e.date<'2026-10-13'),afterCancel);assert.equal(bal('5050'),100,'corrected sale uses physical area once');assert.equal(bal('1500'),100);assert.equal(bal('4100'),220);assert.equal(bal('1200'),220);
const metrics=projectMetrics({...input,steelRecords:[],concreteRecords:[],getProjectJournal:()=>ledger()},'p');assert.equal(metrics.units,2);assert.equal(metrics.sold,1);
let plan=installmentBalances({...sold,reversal:undefined},[pay('dp',20),pay('draft',80,'2026-10-10','i1'),pay('cash',30,'2026-10-11','i1')].map(p=>p.id==='draft'?{...p,approvalStatus:'draft'}:p));assert.equal(plan[0].remainingAmount,50);assert.equal(plan[1].remainingAmount,100);
plan=installmentBalances({...sold,reversal:undefined},[pay('dp',40),pay('cash',30,'2026-10-11','i1')]);assert.equal(plan[0].remainingAmount,30,'general receipts above quoted downpayment allocate FIFO');
plan=installmentBalances({...sold,reversal:undefined},[pay('cash',30,'2026-10-11','i1')].map(p=>({...p,reversal:reversal('cash-reverse','2026-10-12')})));assert.equal(plan[0].remainingAmount,80,'reversed receipt restores installment');assert(installmentBalances(sold,input.payments).every(i=>i.remainingAmount===0),'canceled schedule closes collection');
// Exact original currency lines must cancel for fractional AFN contracts too.
for(const amount of [0.35,7000.55,123456.78]){
 const u:any={...sold,id:'afn',currency:'AFN',exchangeRate:73.25,salePrice:amount,downPayment:0,installments:[],reversal:undefined};const t:any={...input,apartments:[u,other],expenses:[],payments:[]};const original=buildJournal(t);u.reversal=reversal('afn-reversal','2026-10-12');const all=buildJournal(t);assert.equal(all.length,original.length*2);assert.equal(buildTrialBalance(all).totalDebitUSD,0);assert.equal(buildTrialBalance(all).totalCreditUSD,0);assert.deepEqual(all.filter(e=>e.date<'2026-10-12'),original);
}
console.log('Sale reversals passed: exact sale/COGS/cash/receivables cancellation, immutable report prefixes, no later allocation, same physical unit after correction, draft/posted/reversed installment receipts, FIFO general receipts and fractional AFN.');
