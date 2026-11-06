import assert from 'node:assert/strict';
import {buildJournal,buildTrialBalance,computeAccountBalances} from '../src/utils/journalEngine';
import {computeBudgetActuals} from '../src/utils/budgetUtils';
import {isPosted,settledAmount} from '../src/utils/approval';
const purchase:any={id:'e',projectId:'p',category:'5310',description:'Cement',date:'2026-10-13',amount:7000,paidAmount:3500,currency:'AFN',exchangeRate:70,approvalStatus:'draft'};
const payment:any={id:'r',projectId:'p',relatedId:'e',relatedType:'expense',date:'2026-10-13',amount:3500,currency:'AFN',exchangeRate:70,method:'Cash',approvalStatus:'draft'};
const base:any={projects:[{id:'p',defaultExchangeRate:70}],expenses:[purchase],steelRecords:[],concreteRecords:[],payments:[payment],apartments:[],projectInvestments:[],laborRecords:[]};
const cost=()=>computeBudgetActuals({...base,projectId:'p',budgets:[]}).get('5310');
for(const status of ['draft','submitted','approved']){purchase.approvalStatus=status;payment.approvalStatus=status;assert.equal(buildJournal(base).length,0,status);assert.equal(cost(),undefined,status);assert.equal(isPosted(purchase),false);}
purchase.approvalStatus='posted';payment.approvalStatus='approved';assert.equal(buildJournal(base).length,1);assert.equal(cost()?.committedUSD,100);assert.equal(cost()?.paidUSD,0);assert.equal(settledAmount(purchase,base.payments),0);
payment.approvalStatus='posted';let journal=buildJournal(base);assert.equal(journal.length,2);assert.equal(cost()?.paidUSD,50);assert.equal(settledAmount(purchase,base.payments),3500);assert.equal(computeAccountBalances(journal).find(b=>b.accountCode==='2200')?.balanceUSD,50);
const trial=buildTrialBalance(journal);assert.equal(trial.totalDebitUSD,trial.totalCreditUSD);
// A posted payment cannot cause a draft purchase (or wrong-project parent) to enter the books.
purchase.approvalStatus='draft';assert.equal(buildJournal(base).length,0);purchase.approvalStatus='posted';payment.projectId='other';assert.equal(buildJournal(base).length,1);payment.projectId='p';
// Initial embedded cash is never synthesized into a reconciliation for workflow invoices.
base.payments=[];assert.equal(buildJournal(base).length,1);assert.equal(cost()?.paidUSD,0);
// Legacy rows keep their previous reconciled balances until a reviewed migration.
delete purchase.approvalStatus;assert.equal(buildJournal(base).length,2);assert.equal(cost()?.paidUSD,50);
// All other source collections must respect the same gate.
const sources=[['steelRecords',{id:'s',projectId:'p',totalAmount:100,currency:'USD',date:'2026-10-13'}],['concreteRecords',{id:'c',projectId:'p',totalAmount:100,currency:'USD',date:'2026-10-13'}],['laborRecords',{id:'l',projectId:'p',daysWorked:1,dailyRate:100,advanceDeduction:0,currency:'USD',date:'2026-10-13',paymentStatus:'paid'}],['projectInvestments',{id:'i',projectId:'p',amount:100,currency:'USD',date:'2026-10-13',type:'initial'}],['apartments',{id:'a',projectId:'p',status:'sold',salePrice:100,currency:'USD',saleDate:'2026-10-13'}]] as const;
for(const [key,row] of sources){const input:any={...base,expenses:[],[key]:[{...row,approvalStatus:'approved'}]};assert.equal(buildJournal(input).length,0,key);input[key][0].approvalStatus='posted';assert(buildJournal(input).length>0,key);assert.equal(buildTrialBalance(buildJournal(input)).totalDebitUSD,buildTrialBalance(buildJournal(input)).totalCreditUSD,key);}
console.log('Approval accounting passed: seven source types, four states, partial AFN payment, no premature cash/revenue, no duplicate posting, balanced journal and legacy compatibility.');
