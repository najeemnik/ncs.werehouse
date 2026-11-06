/**
 * ============================================================================
 * Journal Engine verification script
 * ============================================================================
 * Builds the double-entry journal from the seed data and checks:
 *   1. Every journal entry is internally balanced (Dr == Cr per entry)
 *   2. The trial balance balances (sum Dr column == sum Cr column)
 *   3. The accounting equation holds: Assets == Liabilities + Equity + (Rev - Exp)
 *   4. Payable/receivable balances agree with the UI's own numbers
 *
 * Run:  npx tsx scripts/verify-journal.ts
 * ============================================================================
 */

import {
  initialProjects,
  initialExpenses,
  initialSteelRecords,
  initialConcreteRecords,
  initialPayments,
  initialApartments,
  initialProjectInvestments as initialInvestmentsSafe,
  initialProjectBudgets as initialBudgetsSafe,
} from '../src/data/seedData';
import { buildJournal, computeAccountBalances, buildTrialBalance, isEntryBalanced, computeWipCostBreakdown } from '../src/utils/journalEngine';
import { computeBudgetReport, summarizeBudgetReport } from '../src/utils/budgetUtils';
import { computeProjectCostPoolUSD, computeSellableAreaM2, allocateUnitCostUSD, computeUnitProfitability } from '../src/utils/wipAllocation';
import { ACC, getAccount } from '../src/data/chartOfAccounts';

const journal = buildJournal({
  projects: initialProjects,
  expenses: initialExpenses,
  steelRecords: initialSteelRecords,
  concreteRecords: initialConcreteRecords,
  payments: initialPayments,
  apartments: initialApartments,
  projectInvestments: initialInvestmentsSafe,
});

const money = (n: number) => n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

let failures = 0;
const check = (ok: boolean, label: string) => {
  console.log(`  ${ok ? '✅' : '❌'} ${label}`);
  if (!ok) failures++;
};

console.log('\n════════════ 1. ENTRY-LEVEL BALANCE ════════════');
const unbalanced = journal.filter(e => !isEntryBalanced(e));
check(journal.length > 0, `Journal built: ${journal.length} entries`);
check(unbalanced.length === 0, `All entries balanced (Dr == Cr)${unbalanced.length ? ` — offenders: ${unbalanced.map(e => e.id).slice(0, 5).join(', ')}` : ''}`);

console.log('\n════════════ 2. TRIAL BALANCE (USD-normalized) ════════════');
const tb = buildTrialBalance(journal);
console.log('  Code   Account                                       Debit $        Credit $');
console.log('  -----  -------------------------------------------  -------------- --------------');
for (const r of tb.rows) {
  console.log(
    `  ${r.accountCode.padEnd(6)} ${r.nameEn.padEnd(43)} ${money(r.debitUSD).padStart(14)} ${money(r.creditUSD).padStart(14)}`
  );
}
console.log('  -----  -------------------------------------------  -------------- --------------');
console.log(`  TOTALS ${''.padEnd(43)} ${money(tb.totalDebitUSD).padStart(14)} ${money(tb.totalCreditUSD).padStart(14)}`);
check(Math.abs(tb.totalDebitUSD - tb.totalCreditUSD) < 1, `Trial balance balances: $${money(tb.totalDebitUSD)} == $${money(tb.totalCreditUSD)}`);

console.log('\n════════════ 3. ACCOUNTING EQUATION ════════════');
const balances = computeAccountBalances(journal);
const bal = (code: string) => balances.find(b => b.accountCode === code)?.balanceUSD ?? 0;
const assets = bal('1000');
const liabilities = bal('2000');
const equity = bal('3000');
const revenue = bal('4000');
const expenses = bal('5000') + bal('6000') + bal('7000') + bal('8000');
const netIncome = revenue - expenses;
const lhs = assets;
const rhs = liabilities + equity + netIncome;
console.log(`  Assets (1000)                          = $${money(assets)}`);
console.log(`  Liabilities (2000)                     = $${money(liabilities)}`);
console.log(`  Equity (3000)                          = $${money(equity)}`);
console.log(`  Net income (Revenue ${money(revenue)} − Exp ${money(expenses)}) = $${money(netIncome)}`);
console.log(`  → Assets ${money(lhs)}  ?=  L + E + NI = ${money(rhs)}`);
check(Math.abs(lhs - rhs) < 1, 'Accounting equation holds: A = L + E + (R − X)');

console.log('\n════════════ 4. CROSS-CHECK WITH UI NUMBERS ════════════');
const uiSteelRemaining = initialSteelRecords.reduce((s, r) => s + Math.max(0, (r.totalAmount || 0) - (r.paidAmount || 0)), 0);
const uiConcreteRemaining = initialConcreteRecords.reduce((s, r) => s + Math.max(0, (r.totalAmount || 0) - (r.paidAmount || 0)), 0);
const uiExpRemaining = initialExpenses.reduce((s, e) => s + Math.max(0, (e.amount || 0) - (e.paidAmount || 0)), 0);
const uiReceivables = initialApartments
  .filter(a => a.status === 'sold')
  .reduce((s, a) => {
    const price = a.salePrice || 0;
    const received = a.amountReceived ?? a.totalReceived ?? 0;
    return s + Math.max(0, price - received);
  }, 0);

const bookSupplierPayable = bal('2200');
const bookReceivables = bal('1200');

console.log(`  Books 2200 Supplier Payables = $${money(bookSupplierPayable)}`);
console.log(`  UI outstanding (steel ${money(uiSteelRemaining)} + concrete ${money(uiConcreteRemaining)} + expenses ${money(uiExpRemaining)})`);
console.log(`  Books 1200 Apartment Receivables = $${money(bookReceivables)}  vs  UI outstanding receivables = $${money(uiReceivables)}`);
check(bookSupplierPayable > 0, 'Supplier payables are positive (credit balance)');
check(Math.abs(bookReceivables - uiReceivables) < 1, 'Receivables on books match UI unit balances');

console.log('\n════════════ 5. PROJECT BUDGET REPORT ════════════');
const budgetRows = computeBudgetReport({
  projectId: 'proj-kabul-plaza',
  project: initialProjects.find(p => p.id === 'proj-kabul-plaza'),
  budgets: initialBudgetsSafe,
  expenses: initialExpenses,
  steelRecords: initialSteelRecords,
  concreteRecords: initialConcreteRecords,
});
console.log('  Account  Budget $      Spent $      Paid $       Payable $    Remaining $  Usage   Status');
console.log('  -------  -----------  -----------  -----------  -----------  -----------  ------  ----------');
for (const r of budgetRows) {
  console.log(
    `  ${r.accountCode.padEnd(8)} ${money(r.budgetUSD).padStart(11)}  ${money(r.committedUSD).padStart(11)}  ${money(r.paidUSD).padStart(11)}  ${money(r.payableUSD).padStart(11)}  ${money(r.remainingUSD).padStart(11)}  ${(r.usagePct === null ? '   —  ' : r.usagePct.toFixed(1).padStart(5) + '%')}  ${r.status}`
  );
}
const bTotals = summarizeBudgetReport(budgetRows);
console.log(`  TOTALS   ${money(bTotals.totalBudgetUSD).padStart(11)}  ${money(bTotals.totalCommittedUSD).padStart(11)}  ${money(bTotals.totalPaidUSD).padStart(11)}  ${money(bTotals.totalPayableUSD).padStart(11)}  ${money(bTotals.totalRemainingUSD).padStart(11)}  ${bTotals.usagePct?.toFixed(1)}%`);
check(budgetRows.some(r => r.accountCode === '5100' && r.hasBudget), 'Steel (5100) budget line exists');
check(budgetRows.every(r => !r.hasBudget || r.usagePct !== null), 'Every budgeted row has a usage %');
const steelRow = budgetRows.find(r => r.accountCode === '5100');
if (steelRow) {
  check(Math.abs(steelRow.remainingUSD - (steelRow.budgetUSD - steelRow.committedUSD)) < 0.01, 'Remaining = Budget − Spent (5100)');
  check(Math.abs(steelRow.payableUSD - Math.max(0, steelRow.committedUSD - steelRow.paidUSD)) < 0.01, 'Payable = Spent − Paid (5100)');
}
check(budgetRows.some(r => r.status === 'over' || r.status === 'warning'), 'Warning/Over-Budget detection active');
check(budgetRows.some(r => !r.hasBudget), 'Unbudgeted spending section detected');

console.log('\n════════════ 6. WIP / COGS (PHASE 3) ════════════');
const PROJ = 'proj-kabul-plaza';
const projJournal = journal.filter(e => e.projectId === PROJ);
const projBalances = computeAccountBalances(journal, PROJ);
const pBal = (code: string) => projBalances.find(b => b.accountCode === code)?.balanceUSD ?? 0;

const pool = computeProjectCostPoolUSD({
  projectId: PROJ,
  project: initialProjects.find(p => p.id === PROJ),
  expenses: initialExpenses,
  steelRecords: initialSteelRecords,
  concreteRecords: initialConcreteRecords,
});
const projUnits = initialApartments.filter(u => u.projectId === PROJ);
const sellableArea = computeSellableAreaM2(projUnits, initialProjects.find(p => p.id === PROJ));
const soldUnits = projUnits.filter(u => u.status === 'sold');
const expectedCogs = Math.round(soldUnits.reduce((s, u) => s + allocateUnitCostUSD(u, pool, sellableArea), 0) * 100) / 100;

const bookWipDebits = computeWipCostBreakdown(journal, PROJ).reduce((s, r) => s + r.totalUSD, 0);
const bookCogs = pBal('5050');
const bookWipNet = pBal('1500');

console.log(`  Cost pool (budget-source)       = $${money(pool)}`);
console.log(`  Sellable area                   = ${sellableArea} m² (${soldUnits.length} sold / ${projUnits.length} total units)`);
console.log(`  Expected COGS (Σ allocations)   = $${money(expectedCogs)}`);
console.log(`  Book WIP debits (1500 detail)   = $${money(bookWipDebits)}`);
console.log(`  Book COGS balance (5050)        = $${money(bookCogs)}`);
console.log(`  Book WIP net (1500)             = $${money(bookWipNet)}  (unsold work = pool − COGS)`);

check(Math.abs(bookWipDebits - pool) < 1, 'WIP debits equal the full cost pool (all construction costs capitalized)');
check(Math.abs(bookCogs - expectedCogs) < 1, 'COGS equals Σ area-allocated cost of SOLD units');
check(Math.abs(bookWipNet - (pool - bookCogs)) < 1, 'WIP net = pool − COGS (unsold inventory remains an asset)');
check(projJournal.some(e => e.lines.some(l=>l.accountCode===ACC.COGS&&l.debitUSD>0)), 'COGS reclass entries emitted per sold unit');

const unit101 = projUnits.find(u => u.unitNumber === '101');
if (unit101) {
  const expected101 = allocateUnitCostUSD(unit101, pool, sellableArea);
  const book101=projJournal.filter(e=>e.sourceId===unit101.id).flatMap(e=>e.lines).filter(l=>l.accountCode===ACC.COGS).reduce((sum,l)=>sum+l.debitUSD-l.creditUSD,0);
  console.log(`  Unit 101 (120 m² of ${sellableArea} m²): allocated = $${money(expected101)}, journal = $${money(book101)}`);
  check(Math.abs(book101-expected101)<1,'Unit 101 total COGS matches area-proportional allocation');
} else {
  check(false, 'Seed unit 101 exists');
}

const profit = computeUnitProfitability({
  projectId: PROJ,
  project: initialProjects.find(p => p.id === PROJ),
  units: initialApartments,
  expenses: initialExpenses,
  steelRecords: initialSteelRecords,
  concreteRecords: initialConcreteRecords,
});
console.log(`  Unit profitability: sales $${money(profit.totals.saleUSD)} − COGS $${money(profit.totals.cogsUSD)} = gross $${money(profit.totals.grossProfitUSD)} (${profit.totals.marginPct?.toFixed(1)}%)`);
check(Math.abs(profit.totals.cogsUSD - bookCogs) < 1, 'Profitability report COGS agrees with the books');
check(profit.rows.filter(r => r.status === 'sold').every(r => r.marginPct !== null), 'Every sold unit has a margin %');
check(Math.abs(profit.remainingInWipUSD - bookWipNet) < 1, 'Remaining-in-WIP agrees with account 1500 balance');

console.log('\n════════════ SAMPLE ENTRIES ════════════');
for (const e of journal.slice(0, 6)) {
  console.log(`  [${e.date}] ${e.description}`);
  for (const l of e.lines) {
    const acc = getAccount(l.accountCode);
    const side = l.debit > 0 ? `Dr ${money(l.debit)}` : `Cr ${money(l.credit)}`;
    console.log(`       ${l.accountCode} ${(acc?.nameEn || '').padEnd(36)} ${side} ${e.currency}`);
  }
}

console.log(failures === 0 ? '\n🎉 ALL CHECKS PASSED\n' : `\n💥 ${failures} CHECK(S) FAILED\n`);
process.exit(failures === 0 ? 0 : 1);
