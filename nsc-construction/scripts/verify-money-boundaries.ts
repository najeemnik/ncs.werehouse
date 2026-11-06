import {payrollAmounts} from '../src/utils/payroll';import {invoiceAmounts} from '../src/utils/withholding';import assert from 'node:assert/strict';import {roundMoney} from '../src/utils/money';import {budgetComponentUSD} from '../src/modern/budgetMath';
for(const [input,expected] of [[1.005,1.01],[10.075,10.08],[1.255*3,3.77],[-1.005,-1.01],[-.005,-.01],[0,0],[.01499999999999998,.02],[1000000.005,1000000.01]])assert.equal(roundMoney(input),expected,'money boundary '+input);
assert.equal(budgetComponentUSD({id:'x',accountCode:'5310',description:'',quantity:1,unit:'bag',unitPrice:1.005,currency:'USD'},70),1.01);assert.equal(budgetComponentUSD({id:'x',accountCode:'5310',description:'',quantity:3,unit:'bag',unitPrice:1.255,currency:'USD'},70),3.77);
assert.equal(payrollAmounts({daysWorked:1,dailyRate:10.075,advanceDeduction:0} as any).gross,10.08);assert.equal(invoiceAmounts({amount:10.075} as any).gross,10.08);
assert.equal(roundMoney(2.5*70.25),175.63);assert.equal(roundMoney(1.01*70),70.7);
console.log('14 monetary rounding boundary checks passed: half cents, negative values, multiplication and budget totals match PHP decimal rounding.');
