import {roundMoney} from '../utils/money';
import {adjustedGross} from '../utils/credits';
import {effectiveAmount} from '../utils/partialReversals';
import {payrollAmounts} from '../utils/payroll';
import {computeAccountBalances} from '../utils/journalEngine';
import {resolveExpenseAccount} from '../data/chartOfAccounts';
import {isActivePosted} from '../utils/approval';
import {inventoryUnits} from '../utils/apartmentFinance';
import {usd} from './model';
export function recognizedProfit(balances:Array<{accountCode:string;balanceUSD:number}>){const bal=(code:string)=>balances.find(b=>b.accountCode===code)?.balanceUSD||0;return roundMoney(bal('4000')-['5000','6000','7000','8000'].reduce((sum,code)=>sum+bal(code),0));}
export function projectMetrics(a:any,pid:string){
 const keep=(r:any)=>r.projectId===pid&&isActivePosted(r);
 const expenses=a.expenses.filter(keep),steel=a.steelRecords.filter(keep),concrete=a.concreteRecords.filter(keep),payments=a.payments.filter((r:any)=>keep(r)&&(!['expense','retention_release','tax_remittance','material_steel','material_concrete','apartment_sale','payroll'].includes(r.relatedType)||[...a.expenses,...a.steelRecords,...a.concreteRecords,...a.apartments,...(a.laborRecords||[])].some((parent:any)=>parent.id===r.relatedId&&parent.projectId===pid&&isActivePosted(parent)))),apartments=a.apartments.filter((r:any)=>r.projectId===pid);
 const rate=a.projects.find((p:any)=>p.id===pid)?.defaultExchangeRate||70;
 const value=(r:any,amount:number)=>usd(amount,r.currency,r.exchangeRate||rate);
 const total=(rows:any[],field:string)=>rows.reduce((s,r)=>s+value(r,adjustedGross(r)),0);
 const wages=(a.laborRecords||[]).filter(keep);const wageGross=wages.reduce((s:number,w:any)=>s+value({...w,currency:w.currency||'AFN'},adjustedGross(w,'labor')),0);
 let committed=total(expenses,'amount')+total(steel,'totalAmount')+total(concrete,'totalAmount')+wageGross;
 const paidWages=wages.filter((w:any)=>w.paymentStatus==='paid').reduce((s:number,w:any)=>s+value({...w,currency:w.currency||'AFN'},payrollAmounts(w).net),0);
 let paid=paidWages+payments.filter((p:any)=>p.relatedType!=='apartment_sale'&&p.relatedType!=='client_advance'&&p.paymentType!=='income').reduce((s:number,p:any)=>s+value(p,effectiveAmount(p)),0);
 const standalone=payments.filter((p:any)=>p.relatedType==='other'&&p.paymentType!=='income').reduce((sum:number,p:any)=>sum+value(p,effectiveAmount(p)),0);committed+=standalone;
 const journal=a.getProjectJournal(pid);paid+=journal.filter((entry:any)=>['expense','steel','concrete'].includes(entry.sourceType)&&entry.id===`je-${entry.sourceType}-${entry.sourceId}-unlinked-settlement`).reduce((sum:number,entry:any)=>sum+entry.lines.filter((line:any)=>line.accountCode.startsWith('11')).reduce((value:number,line:any)=>value+line.creditUSD-line.debitUSD,0),0);
 const balances=computeAccountBalances(journal);const bal=(code:string)=>balances.find(b=>b.accountCode===code)?.balanceUSD||0;
 const mix:Record<string,number>={'5600':wageGross,'5990':standalone};for(const r of expenses){const code=resolveExpenseAccount(r.category);mix[code]=(mix[code]||0)+value(r,adjustedGross(r));}mix['5100']=(mix['5100']||0)+total(steel,'totalAmount');mix['5200']=(mix['5200']||0)+total(concrete,'totalAmount');
 for(const r of a.projectTransfers||[]){if(r.kind!=='material_transfer'||!isActivePosted(r))continue;const sign=r.projectId===pid?-1:r.toProjectId===pid?1:0;const v=sign*value(r,adjustedGross(r));committed+=v;mix[r.accountCode]=(mix[r.accountCode]||0)+v;}
 return {committed,paid,payable:Math.max(0,bal('2200'))+Math.max(0,bal('2250'))+Math.max(0,bal('2400'))+Math.max(0,bal('2600')),receivable:Math.max(0,bal('1200')),sales:bal('4100'),received:payments.filter((p:any)=>p.relatedType==='apartment_sale').reduce((s:number,p:any)=>s+value(p,effectiveAmount(p)),0),profit:recognizedProfit(balances),wip:bal('1500'),cogs:bal('5050'),steelTons:steel.reduce((s:number,r:any)=>s+(r.kg||0)/1000,0),concreteVolume:concrete.reduce((s:number,r:any)=>s+(r.quantityM3||0),0),sold:apartments.filter((r:any)=>r.status==='sold'&&isActivePosted(r)).length,units:inventoryUnits(apartments).length,mix,expenses,payments};
}
