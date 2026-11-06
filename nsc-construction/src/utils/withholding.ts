import {roundMoney} from './money';
import type {Expense} from '../types';
const round=roundMoney;
export function invoiceAmounts(row:Pick<Expense,'amount'|'partyType'|'retentionPercentage'|'taxPercentage'>){
 const gross=round(Number(row.amount)||0),contractor=row.partyType==='contractor';
 const retention=contractor?round(gross*(Number(row.retentionPercentage)||0)/100):0;
 const tax=contractor?Math.min(round(Math.max(0,gross-retention)),round(gross*(Number(row.taxPercentage)||0)/100)):0;
 return {gross,retention,tax,net:round(Math.max(0,gross-retention-tax))};
}
