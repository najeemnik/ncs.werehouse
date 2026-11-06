import {roundMoney} from './money';
import {adjustedGross} from './credits';
import {effectiveAmount} from './partialReversals';
import {payrollAmounts} from './payroll';
/**
 * ============================================================================
 * NIK SMART COUNT — Project Budget engine
 * ============================================================================
 *
 * Budget model:
 *  - One budget line per (project, COA account) — budgets are attached to
 *    real Chart-of-Accounts codes (normally 5xxx project-cost accounts),
 *    never to a parallel category list.
 *  - Actuals are DERIVED live from purchases (expenses / steel / concrete)
 *    through the very same category→account mapping the journal engine uses:
 *
 *        Committed (Actual Spent) = purchases recorded  (accrual basis)
 *        Paid                     = the settled portion of those purchases
 *        Payable                  = Committed − Paid
 *        Remaining Budget         = Budget − Committed
 *        Usage %                  = Committed / Budget × 100
 *
 *    Buying on credit therefore consumes budget immediately (committed),
 *    which is the standard cost-control behaviour in construction.
 *
 *  - Status thresholds (project-controlling convention):
 *        usage ≥ 100%  → over      🔴
 *        usage ≥ 80%   → warning   🟡
 *        otherwise     → normal    🟢
 *      Accounts with spending but NO budget line → 'unbudgeted' ⚪ (a
 *      separate control section — spending without an approved budget is
 *      itself a red flag worth surfacing).
 *
 *  - Currency: everything is compared in USD-normalized values using each
 *    record's own exchange rate (same convention as the journal engine).
 * ============================================================================
 */

import {
  Expense,
  SteelRecord,
  ConcreteRecord,
  Project,
  LaborRecord,
  ProjectBudget,
  BudgetLineReport,
  BudgetStatus,
} from '../types';
import { isActivePosted, settledAmount } from './approval';
import { Payment,ProjectLedgerTransfer } from '../types';
import { ACC, getAccount, getAncestorCodes, resolveExpenseAccount } from '../data/chartOfAccounts';

export const BUDGET_WARNING_THRESHOLD = 80;
export const BUDGET_OVER_THRESHOLD = 100;

const round2=(n:number)=>roundMoney(n||0);

const toUSD = (amount: number, currency: string | undefined, rate: number | undefined): number => {
  const r = rate && rate > 0 ? rate : 70;
  return currency === 'AFN' ? round2(amount / r) : round2(amount);
};

export type BudgetableActual = {
  accountCode: string;
  committedUSD: number;
  paidUSD: number;
};

export interface BudgetReportInput {
  payments?: Payment[];projectTransfers?:ProjectLedgerTransfer[];
  laborRecords?: LaborRecord[];
  projectId: string;
  project?: Project;
  budgets: ProjectBudget[];
  expenses: Expense[];
  steelRecords: SteelRecord[];
  concreteRecords: ConcreteRecord[];
}

/**
 * Per-account committed/paid actuals for a project, derived from purchases.
 * Steel → 5100, Concrete → 5200 (they post fixed accounts in the journal),
 * expenses → resolved via the shared category→account map.
 */
export function computeBudgetActuals(input: BudgetReportInput): Map<string, BudgetableActual> {
  const { projectId, expenses, steelRecords, concreteRecords } = input;
  const acc = new Map<string, BudgetableActual>();

  const push = (accountCode: string, committedUSD: number, paidUSD: number) => {
    if (committedUSD === 0 && paidUSD === 0) return;
    const cur = acc.get(accountCode) || { accountCode, committedUSD: 0, paidUSD: 0 };
    cur.committedUSD = round2(cur.committedUSD + committedUSD);
    cur.paidUSD = round2(cur.paidUSD + paidUSD);
    acc.set(accountCode, cur);
  };

  for (const e of expenses.filter(x => x.projectId === projectId && isActivePosted(x))) {
    const committed = toUSD(adjustedGross(e), e.currency, e.exchangeRate||input.project?.defaultExchangeRate);
    const paid = toUSD(settledAmount(e,input.payments||[]), e.currency, e.exchangeRate||input.project?.defaultExchangeRate);
    push(resolveExpenseAccount(e.category), committed, Math.min(paid, committed));
  }

  for (const s of steelRecords.filter(x => x.projectId === projectId && isActivePosted(x))) {
    const committed = toUSD(adjustedGross(s), s.currency, s.exchangeRate||input.project?.defaultExchangeRate);
    const paid = toUSD(settledAmount(s,input.payments||[]), s.currency, s.exchangeRate||input.project?.defaultExchangeRate);
    push(ACC.STEEL, committed, Math.min(paid, committed));
  }

  for (const c of concreteRecords.filter(x => x.projectId === projectId && isActivePosted(x))) {
    const committed = toUSD(adjustedGross(c), c.currency, c.exchangeRate||input.project?.defaultExchangeRate);
    const paid = toUSD(settledAmount(c,input.payments||[]), c.currency, c.exchangeRate||input.project?.defaultExchangeRate);
    push(ACC.CONCRETE, committed, Math.min(paid, committed));
  }

  for(const p of input.payments||[])if(p.projectId===projectId&&p.relatedType==='other'&&p.paymentType!=='income'&&isActivePosted(p)){const amount=toUSD(effectiveAmount(p),p.currency,p.exchangeRate||input.project?.defaultExchangeRate);push('5990',amount,amount);}
  for(const wage of input.laborRecords||[])if(wage.projectId===projectId&&isActivePosted(wage)){const {gross,advance}=payrollAmounts(wage);push('5600',toUSD(adjustedGross(wage,'labor'),wage.currency||'AFN',wage.exchangeRate||input.project?.defaultExchangeRate),toUSD(wage.paymentStatus==='paid'?gross:advance+(input.payments||[]).filter(p=>p.relatedType==='payroll'&&p.relatedId===wage.id&&p.projectId===wage.projectId&&isActivePosted(p)).reduce((s,p)=>s+(p.currency==='AFN'?effectiveAmount(p)/(p.exchangeRate||70):effectiveAmount(p))*((wage.currency||'AFN')==='AFN'?(wage.exchangeRate||70):1),0),wage.currency||'AFN',wage.exchangeRate||input.project?.defaultExchangeRate));}
  for(const r of input.projectTransfers||[]){if(r.kind!=='material_transfer'||!isActivePosted(r))continue;const sign=r.projectId===projectId?-1:r.toProjectId===projectId?1:0;if(sign)push(r.accountCode!,sign*toUSD(r.amount,r.currency,r.exchangeRate),0);}
  return acc;
}

export function getBudgetStatus(usagePct: number | null, hasBudget: boolean): BudgetStatus {
  if (!hasBudget || usagePct === null) return 'unbudgeted';
  if (usagePct >= BUDGET_OVER_THRESHOLD) return 'over';
  if (usagePct >= BUDGET_WARNING_THRESHOLD) return 'warning';
  return 'normal';
}

/**
 * Full budget report for one project — every budget line plus an
 * 'unbudgeted' line for accounts that consumed money without a budget.
 */
export function computeBudgetReport(input: BudgetReportInput): BudgetLineReport[] {
  const { projectId, project, budgets } = input;

  // Budget per account (upsert-merged — one line per account conceptually,
  // multiple stored lines are summed defensively)
  const budgetMap = new Map<string, number>();
  for (const b of budgets.filter(x => x.projectId === projectId)) {
    const rate = b.exchangeRate || project?.defaultExchangeRate || 70;
    const usd = b.amountUSD ?? toUSD(b.amount, b.currency, rate);
    budgetMap.set(b.accountCode, round2((budgetMap.get(b.accountCode) || 0) + usd));
  }

  const rawActuals = computeBudgetActuals(input);
  const actuals = new Map<string,BudgetableActual>();
  for(const [code,value] of rawActuals){
    const target=budgetMap.has(code)?code:getAncestorCodes(code).find(parent=>budgetMap.has(parent))||code;
    const existing=actuals.get(target)||{accountCode:target,committedUSD:0,paidUSD:0};
    actuals.set(target,{accountCode:target,committedUSD:round2(existing.committedUSD+value.committedUSD),paidUSD:round2(existing.paidUSD+value.paidUSD)});
  }

  const allCodes = new Set<string>([...budgetMap.keys(), ...actuals.keys()]);
  const rows: BudgetLineReport[] = [];

  for (const code of allCodes) {
    const account = getAccount(code);
    if (!account) continue;
    const hasBudget = budgetMap.has(code);
    const budget = round2(budgetMap.get(code) || 0);
    const act = actuals.get(code);
    const committed = round2(act?.committedUSD || 0);
    const paid = round2(Math.min(act?.paidUSD || 0, committed));
    const payable = round2(Math.max(0, committed - paid));
    const usagePct = hasBudget && budget > 0 ? round2((committed / budget) * 100) : null;
    rows.push({
      accountCode: code,
      nameEn: account.nameEn,
      nameFa: account.nameFa,
      namePs: account.namePs,
      hasBudget,
      budgetUSD: budget,
      committedUSD: committed,
      paidUSD: paid,
      payableUSD: payable,
      remainingUSD: round2(budget - committed),
      usagePct,
      status: getBudgetStatus(usagePct, hasBudget),
    });
  }

  // Budgeted rows first (by account code), unbudgeted at the end
  return rows.sort((a, b) => {
    if (a.hasBudget !== b.hasBudget) return a.hasBudget ? -1 : 1;
    return a.accountCode < b.accountCode ? -1 : 1;
  });
}

export interface BudgetTotals {
  totalBudgetUSD: number;
  totalCommittedUSD: number;
  totalPaidUSD: number;
  totalPayableUSD: number;
  totalRemainingUSD: number;
  usagePct: number | null;
  status: BudgetStatus;
}

export function summarizeBudgetReport(rows: BudgetLineReport[]): BudgetTotals {
  const totalBudgetUSD = round2(rows.reduce((s, r) => s + r.budgetUSD, 0));
  const totalCommittedUSD = round2(rows.reduce((s, r) => s + r.committedUSD, 0));
  const totalPaidUSD = round2(rows.reduce((s, r) => s + r.paidUSD, 0));
  const totalPayableUSD = round2(rows.reduce((s, r) => s + r.payableUSD, 0));
  const totalRemainingUSD = round2(totalBudgetUSD - totalCommittedUSD);
  const usagePct = totalBudgetUSD > 0 ? round2((totalCommittedUSD / totalBudgetUSD) * 100) : null;
  return {
    totalBudgetUSD,
    totalCommittedUSD,
    totalPaidUSD,
    totalPayableUSD,
    totalRemainingUSD,
    usagePct,
    status: getBudgetStatus(usagePct, totalBudgetUSD > 0),
  };
}
