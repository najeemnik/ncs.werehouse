import assert from 'node:assert/strict';
import {buildJournal,computeAccountBalances,isEntryBalanced} from '../src/utils/journalEngine';
import {computeProjectCostPoolUSD} from '../src/utils/wipAllocation';
import {computeBudgetActuals,computeBudgetReport,summarizeBudgetReport} from '../src/utils/budgetUtils';
const wage:any={id:'wage1',projectId:'project1',workerName:'QA Worker',daysWorked:10,dailyRate:700,advanceDeduction:1400,paymentStatus:'paid',currency:'AFN',exchangeRate:70,date:'2026-10-13'};
const input:any={projects:[{id:'project1',defaultExchangeRate:70}],expenses:[],steelRecords:[],concreteRecords:[],payments:[],apartments:[],projectInvestments:[],laborRecords:[wage]};
const journal=buildJournal(input),balances=computeAccountBalances(journal),balance=(code:string)=>balances.find(b=>b.accountCode===code)?.balanceUSD||0;
assert(journal.every(e=>isEntryBalanced(e,0.01)));assert.equal(balance('1500'),100);assert.equal(balance('1110'),-80);assert.equal(balance('1310'),-20);assert.equal(balance('2200'),0);
assert.equal(computeProjectCostPoolUSD({...input,projectId:'project1'}),100);assert.equal(computeBudgetActuals({...input,projectId:'project1',budgets:[]}).get('5600')?.committedUSD,100);
const unpaid=buildJournal({...input,laborRecords:[{...wage,paymentStatus:'pending',advanceDeduction:0}]});const b=computeAccountBalances(unpaid);assert.equal(b.find(x=>x.accountCode==='2200')?.balanceUSD,100);assert.equal(b.find(x=>x.accountCode==='1110')?.balanceUSD||0,0);
console.log('Payroll: accrual, advance application, net cash, unpaid liability, budget and WIP checks passed.');

const report=computeBudgetReport({projectId:'project1',budgets:[{id:'b',projectId:'project1',accountCode:'5300',amount:200,currency:'USD'} as any],expenses:[{id:'e',projectId:'project1',category:'5310',amount:100,currency:'USD',paidAmount:20} as any],steelRecords:[],concreteRecords:[]});
assert.equal(report.find(r=>r.accountCode==='5300')?.committedUSD,100);assert.equal(summarizeBudgetReport(report).totalCommittedUSD,100);assert.equal(report.some(r=>r.accountCode==='5310'),false);console.log('Material subaccounts roll up to the parent budget without double counting.');
