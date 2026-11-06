import {roundMoney} from './money';
import type {LaborRecord} from '../types';
const cents=roundMoney;
export function payrollAmounts(wage:Pick<LaborRecord,'daysWorked'|'dailyRate'|'advanceDeduction'>){
 const gross=cents(wage.daysWorked*wage.dailyRate),advance=cents(wage.advanceDeduction||0);
 return {gross,advance,net:Math.max(0,cents(gross-advance))};
}
