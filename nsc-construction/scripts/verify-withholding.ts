import assert from 'node:assert/strict';
import {invoiceAmounts} from '../src/utils/withholding';
import {buildJournal,computeAccountBalances,buildTrialBalance} from '../src/utils/journalEngine';
import {computeBudgetReport} from '../src/utils/budgetUtils';
const bill:any={id:'contract-bill',projectId:'p',date:'2026-10-13',description:'Contractor progress bill',partyType:'contractor',amount:70000,currency:'AFN',exchangeRate:70,retentionPercentage:5,taxPercentage:2,approvalStatus:'posted'};
assert.deepEqual(invoiceAmounts(bill),{gross:70000,retention:3500,tax:1400,net:65100});
const payment=(id:string,relatedType:string,amount:number,currency='AFN',exchangeRate=70):any=>({id,relatedId:bill.id,relatedType,projectId:'p',amount,currency,exchangeRate,date:'2026-10-13',method:'Cash',approvalStatus:'posted'});
const input:any={projects:[{id:'p',defaultExchangeRate:70}],expenses:[bill],steelRecords:[],concreteRecords:[],apartments:[],laborRecords:[],projectInvestments:[],payments:[]};
const balances=()=>{const journal=buildJournal(input),trial=buildTrialBalance(journal);assert.equal(trial.totalDebitUSD,trial.totalCreditUSD);return computeAccountBalances(journal);};
const balance=(code:string)=>balances().find(b=>b.accountCode===code)?.balanceUSD||0;
assert.equal(balance('1500'),1000);assert.equal(balance('2250'),930);assert.equal(balance('2200'),0);assert.equal(balance('2400'),50);assert.equal(balance('2600'),20);
input.payments=[payment('net','expense',65100)];assert.equal(balance('2250'),0);assert.equal(balance('2400'),50);assert.equal(balance('2600'),20);assert.equal(balance('1110'),-930);
let report=computeBudgetReport({...input,projectId:'p',budgets:[],concreteRecords:[],steelRecords:[]});assert.equal(report[0].committedUSD,1000);assert.equal(report[0].paidUSD,930);assert.equal(report[0].payableUSD,70);
input.payments.push(payment('retention','retention_release',50,'USD'));assert.equal(balance('2400'),0);assert.equal(balance('2600'),20);assert.equal(balance('1120'),-50);
input.payments.push(payment('tax','tax_remittance',1400));assert.equal(balance('2600'),0);assert.equal(balance('1500'),1000);report=computeBudgetReport({...input,projectId:'p',budgets:[]});assert.equal(report[0].paidUSD,1000);assert.equal(report[0].payableUSD,0);
// Pending release/remittance cannot affect the books or budget.
input.payments[1].approvalStatus='approved';input.payments[2].approvalStatus='draft';assert.equal(balance('2400'),50);assert.equal(balance('2600'),20);assert.equal(computeBudgetReport({...input,projectId:'p',budgets:[]})[0].paidUSD,930);
// Percent rounding must never produce more credits than a tiny invoice's debit.
for(const amount of [0.01,0.03,1.01,100.01])for(const [retentionPercentage,taxPercentage] of [[50,50],[5,2],[99.99,0.01],[0,100]]){const b={...bill,amount,retentionPercentage,taxPercentage,currency:'USD'};const v=invoiceAmounts(b);assert.equal(Math.round((v.net+v.retention+v.tax)*100),Math.round(amount*100));const trial=buildTrialBalance(buildJournal({...input,expenses:[b],payments:[]}));assert.equal(trial.totalDebitUSD,trial.totalCreditUSD);}
console.log('Withholding passed: gross cost recorded once, net contractor payable, separate retention/tax liabilities, AFN/USD settlement, independent approved releases, budget reconciliation and cent rounding.');

for(let cents=1;cents<=2000;cents++){const b={...bill,amount:cents/100};const amounts=invoiceAmounts(b);const payments=[payment('net','expense',amounts.net),payment('ret','retention_release',amounts.retention),payment('tax','tax_remittance',amounts.tax)];const journal=buildJournal({...input,expenses:[b],payments}),trial=buildTrialBalance(journal);assert.equal(trial.totalDebitUSD,trial.totalCreditUSD,'AFN normalized trial balance '+cents);const balances=computeAccountBalances(journal);for(const code of ['2250','2400','2600'])assert.equal(balances.find(b=>b.accountCode===code)?.balanceUSD||0,0,'Fully settled liability '+code+' / '+cents);for(const entry of journal){assert.equal(entry.totalDebitUSD,entry.totalCreditUSD);assert.equal(entry.lines.reduce((sum,line)=>sum+line.debitAFN,0),entry.lines.reduce((sum,line)=>sum+line.creditAFN,0));}}
console.log('2,000 fractional AFN invoices reconcile in both currencies; explicit rounding entries preserve original amounts and clear settled liabilities.');
