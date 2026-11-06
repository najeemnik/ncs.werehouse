import {roundMoney} from './money';
import {inventoryUnits} from './apartmentFinance';
/**
 * ============================================================================
 * NIK SMART COUNT — WIP Capitalization & Unit Cost Allocation (Phase 3)
 * ============================================================================
 *
 * Construction-developer accounting model:
 *
 *   ┌─────────────┐   capitalize    ┌──────────────┐  on unit sale   ┌────────┐
 *   │ 5xxx direct  │ ──────────────▶ │ 1500 WIP     │ ──────────────▶ │ 5050   │
 *   │ costs        │                 │ (asset)      │                 │ COGS   │
 *   └─────────────┘                 └──────────────┘                 └────────┘
 *
 *  - Every construction purchase is capitalized into the Work-in-Progress
 *    ASSET (1500) instead of hitting the P&L directly. The natural cost
 *    classification (5100 Steel, 5200 Concrete...) is preserved on the
 *    journal line via `costAccountCode` for detail reporting.
 *  - When a unit is SOLD, its share of the cost pool is relieved from WIP
 *    to Cost of Units Sold (5050):
 *
 *        Unit COGS = Cost Pool × (Unit sellable area ÷ Total sellable area)
 *
 *    Area-proportional allocation is the industry-standard method for
 *    multi-unit developments (FAR/relative-area basis).
 *  - The cost pool is "costs incurred to date" (accrual basis — unpaid
 *    purchases count, matching budget commitment control). Allocations
 *    therefore refine automatically as more costs arrive; final unit
 *    margins are known when the project completes.
 *  - RESERVED units get NO COGS (their receipts sit in 2300 advances).
 * ============================================================================
 */

import {
  ApartmentUnit,
  Project,
  Expense,
  SteelRecord,
  ConcreteRecord,
  ProjectBudget,
  LaborRecord,
} from '../types';
import { isPosted } from './approval';
import { computeBudgetActuals, BudgetableActual } from './budgetUtils';

const round2=(n:number)=>roundMoney(n||0);

/* ------------------------------------------------------------------ */
/* Cost pool (all direct project costs — the WIP source)              */
/* ------------------------------------------------------------------ */

export interface CostPoolInput {
  laborRecords?: LaborRecord[];
  projectId: string;
  project?: Project;
  budgets?: ProjectBudget[]; // unused, kept for signature parity with budget report
  expenses: Expense[];
  steelRecords: SteelRecord[];
  concreteRecords: ConcreteRecord[];
}

/**
 * Total direct construction cost incurred (USD-normalized) for a project —
 * i.e. everything that capitalizes into WIP (all 5xxx accounts).
 * Computed from the same actuals source as the budget engine, guaranteeing
 * Budget view, WIP and the journal all agree numerically.
 */
export function computeProjectCostPoolUSD(input: CostPoolInput): number {
  const actuals: Map<string, BudgetableActual> = computeBudgetActuals({
    projectId: input.projectId,
    project: input.project,
    budgets: input.budgets || [],
    laborRecords: input.laborRecords,
    expenses: input.expenses,
    steelRecords: input.steelRecords,
    concreteRecords: input.concreteRecords,
  });
  let total = 0;
  for (const [code, act] of actuals) {
    if (code.startsWith('5')) total += act.committedUSD;
  }
  return round2(total);
}

/* ------------------------------------------------------------------ */
/* Sellable area                                                      */
/* ------------------------------------------------------------------ */

/**
 * Total sellable area (m²) = sum of all unit areas in the project.
 * Falls back to the project's declared building area when unit areas are
 * missing. A zero result disables allocation (everything stays in WIP).
 */
export function computeSellableAreaM2(units: ApartmentUnit[], project?: Project): number {
  units=inventoryUnits(units);
  const sum = round2(units.reduce((s, u) => s + (u.areaM2 || u.areaSqm || 0), 0));
  if (sum > 0) return sum;
  return round2(project?.buildingArea || project?.totalArea || 0);
}

/**
 * Cost allocated to one unit (USD), area-proportional to the cost pool.
 */
export function allocateUnitCostUSD(unit: ApartmentUnit, costPoolUSD: number, sellableAreaM2: number): number {
  const area = unit.areaM2 || unit.areaSqm || 0;
  if (costPoolUSD <= 0 || sellableAreaM2 <= 0 || area <= 0) return 0;
  return round2(costPoolUSD * (area / sellableAreaM2));
}

/* ------------------------------------------------------------------ */
/* Per-unit profitability                                             */
/* ------------------------------------------------------------------ */

export interface UnitProfitabilityRow {
  unitId: string;
  unitNumber: string;
  unitType?: string;
  buyerName?: string;
  status: ApartmentUnit['status'] | string;
  areaM2: number;
  /** Share of total sellable area (%) */
  areaSharePct: number;
  salePriceUSD: number;
  receivedUSD: number;
  /** COGS relieved from WIP (sold units only; 0 otherwise) */
  allocatedCostUSD: number;
  /** Sale price − allocated cost (sold units only) */
  grossProfitUSD: number;
  /** margin % = grossProfit / salePrice — null when not sold/no price */
  marginPct: number | null;
}

export function computeUnitProfitability(input: {
  projectId: string;
  project?: Project;
  units: ApartmentUnit[];
  costPoolUSD?: number;
  expenses?: Expense[];
  steelRecords?: SteelRecord[];
  concreteRecords?: ConcreteRecord[];
}): {
  rows: UnitProfitabilityRow[];
  sellableAreaM2: number;
  costPoolUSD: number;
  allocatedToSoldUSD: number;
  remainingInWipUSD: number;
  totals: { saleUSD: number; receivedUSD: number; cogsUSD: number; grossProfitUSD: number; marginPct: number | null };
} {
  const units = (input.units || []).filter(u => u.projectId === input.projectId);
  const costPoolUSD =
    input.costPoolUSD ??
    (input.expenses && input.steelRecords && input.concreteRecords
      ? computeProjectCostPoolUSD({
          projectId: input.projectId,
          project: input.project,
          expenses: input.expenses,
          steelRecords: input.steelRecords,
          concreteRecords: input.concreteRecords,
        })
      : 0);

  const sellableAreaM2 = computeSellableAreaM2(units, input.project);

  const rows: UnitProfitabilityRow[] = units.map(u => {
    const sold = u.status === 'sold' && isPosted(u);
    const area = u.areaM2 || u.areaSqm || 0;
    const salePriceUSD =
      u.salePrice && (u.currency === 'AFN')
        ? round2(u.salePrice / (u.exchangeRate || input.project?.defaultExchangeRate || 70))
        : round2(u.salePriceInUSD ?? u.totalPriceUSD ?? u.salePrice ?? 0);
    const rate = u.exchangeRate || input.project?.defaultExchangeRate || 70;
    const received = u.amountReceived ?? u.totalReceived ?? 0;
    const receivedUSD = u.currency === 'AFN' ? round2(received / rate) : round2(received);
    const allocated = sold ? allocateUnitCostUSD(u, costPoolUSD, sellableAreaM2) : 0;
    const grossProfit = sold ? round2(salePriceUSD - allocated) : 0;
    const marginPct = sold && salePriceUSD > 0 ? round2((grossProfit / salePriceUSD) * 100) : null;
    return {
      unitId: u.id,
      unitNumber: u.unitNumber || u.id,
      unitType: u.unitType || u.type,
      buyerName: u.buyerName,
      status: u.status,
      areaM2: area,
      areaSharePct: sellableAreaM2 > 0 ? round2((area / sellableAreaM2) * 100) : 0,
      salePriceUSD,
      receivedUSD,
      allocatedCostUSD: allocated,
      grossProfitUSD: grossProfit,
      marginPct,
    };
  });

  rows.sort((a, b) => (a.unitNumber < b.unitNumber ? -1 : 1));

  const soldRows = rows.filter(r => r.status === 'sold' && isPosted(units.find(u=>u.id===r.unitId)!));
  const cogsUSD = round2(soldRows.reduce((s, r) => s + r.allocatedCostUSD, 0));
  const saleUSD = round2(soldRows.reduce((s, r) => s + r.salePriceUSD, 0));
  const receivedUSD = round2(soldRows.reduce((s, r) => s + r.receivedUSD, 0));
  const grossProfitUSD = round2(saleUSD - cogsUSD);

  return {
    rows,
    sellableAreaM2,
    costPoolUSD,
    allocatedToSoldUSD: cogsUSD,
    remainingInWipUSD: round2(costPoolUSD - cogsUSD),
    totals: {
      saleUSD,
      receivedUSD,
      cogsUSD,
      grossProfitUSD,
      marginPct: saleUSD > 0 ? round2((grossProfitUSD / saleUSD) * 100) : null,
    },
  };
}
