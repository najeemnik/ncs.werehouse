import React from 'react';
import {ChartNoAxesCombined} from 'lucide-react';
import {useApp} from '../context/AppContext';
import {useText} from './EntityView';
import {projectMetrics} from './metrics';
import {displayDate} from './presentation';
import {canAccess,usd} from './model';
export function SmartAnalysis(){
 const a=useApp(),t=useText(),pid=a.currentProject?.id||'',m=projectMetrics(a,pid),money=(v:number)=>a.formatCurrency(v,'USD');
 const budget=a.getBudgetReport(pid).reduce((sum,b)=>sum+b.budgetUSD,0),progress=(a.currentProject as any)?.progressPercentage;
 const months:Record<string,number>={};for(const e of a.getProjectJournal(pid)){for(const l of e.lines){if(l.accountCode==='1500')months[e.date.slice(0,7)]=(months[e.date.slice(0,7)]||0)+l.debitUSD-l.creditUSD;}}
 const values=Object.entries(months).sort(([x],[y])=>x.localeCompare(y)).slice(-8),max=Math.max(1,...values.map(([,v])=>Math.abs(v)));
 return <div className="nsc-card nsc-dashboard-card nsc-smart-analysis"><div className="nsc-section-heading"><div><span className="nsc-eyebrow">{t('live')}</span><h2>{t('smartChart')}</h2></div><ChartNoAxesCombined size={23}/></div><p>{t('smartSummary')}</p><div className="nsc-smart-stats">{([['committed',m.committed],['paid',m.paid],['payable',m.payable],['sales',m.sales],['receivable',m.receivable],['profit',m.profit],['wip',m.wip],['remaining',budget-m.committed]] as const).filter(([key])=>!['sales','receivable'].includes(key)||canAccess(a.currentUser,pid,'apartments','view',a.appSettings)).map(([key,value])=><div key={key}><span>{t(key)}</span><strong className={value<0?'negative':''}>{money(value)}</strong></div>)}</div><div className="nsc-smart-progress"><div><span>{t('physicalProgress')}</span><strong>{typeof progress==='number'?a.formatNumber(progress,1)+'%':'—'}</strong><progress max={100} value={progress||0}/></div><div><span>{t('budgetUsage')}</span><strong>{budget?a.formatNumber(m.committed/budget*100,1)+'%':'—'}</strong><progress max={100} value={budget?Math.min(100,m.committed/budget*100):0}/></div></div><small>{t('progressHint')}</small><div className="nsc-smart-timeline" aria-label={t('wip')}>{values.map(([month,value])=><div key={month}><span>{displayDate(a.language,month+'-01')}</span><div className="nsc-bar-track"><div style={{width:Math.abs(value)/max*100+'%',background:value<0?'#b45309':'var(--nsc-accent)'}}/></div><strong>{money(value)}</strong></div>)}</div></div>;
}
