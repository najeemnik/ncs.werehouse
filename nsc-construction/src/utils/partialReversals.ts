import type {FinancialApproval,FinancialReversal} from '../types';
export type ReversalPart=FinancialReversal&{amount:number};
import {roundMoney} from './money';
export {roundMoney} from './money';
export function effectiveAmount(row:FinancialApproval&{amount?:number}):number {if(row.reversal)return 0;return Math.max(0,roundMoney((row.amount||0)-(row.partialReversals||[]).reduce((sum,p)=>sum+p.amount,0)));}
export function moneyReversalEvents(row:FinancialApproval&{amount?:number}):ReversalPart[]{const parts=[...(row.partialReversals||[])];if(row.reversal)parts.push({...row.reversal,amount:row.reversal.amount??Math.max(0,roundMoney((row.amount||0)-parts.reduce((s,p)=>s+p.amount,0)))});return parts;}
