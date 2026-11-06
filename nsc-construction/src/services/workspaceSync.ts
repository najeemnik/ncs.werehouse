import {blankTestMode} from '../config/testMode';
import {previewCredit} from '../utils/previewCredits';
import {adjustedGross,adjustedInvoiceAmounts} from '../utils/credits';
import {inventoryUnits} from '../utils/apartmentFinance';
import {computeSellableAreaM2} from '../utils/wipAllocation';
import {buildJournal} from '../utils/journalEngine';
import {effectiveAmount,roundMoney} from '../utils/partialReversals';
import {payrollAmounts} from '../utils/payroll';
import {saleDate,installmentBalances,paymentInUnitCurrency} from '../utils/apartmentFinance';
import {todayISO} from '../modern/presentation';
import { useEffect, useRef, useState } from 'react';
import {reversibleCollections} from '../utils/approval';
import { backendApi } from './backendApi';
export type WorkspaceStatus = 'demo' | 'signed-out' | 'loading' | 'saving' | 'saved' | 'pending' | 'conflict' | 'error';
export function useWorkspaceSync(userId: string | undefined, document: Record<string, unknown>, hydrate: (doc: Record<string, any> | null) => Record<string, any>, actor?: {id:string;name:string}) {
  const preview = import.meta.env.VITE_PREVIEW === 'true';
  const [status, setStatus] = useState<WorkspaceStatus>(preview ? 'demo' : 'signed-out');
  const [error, setError] = useState('');
  const [tick, setTick] = useState(0);
  const state = useRef({ generation: 0, revision: 0, ready: false, busy: false, saved: '', blocked: false, scope:undefined as string|undefined });
  const latest = useRef(document); latest.current = document;
  const hydrator = useRef(hydrate); hydrator.current = hydrate;
  useEffect(() => {
    if (preview) return;
    const generation = ++state.current.generation;
    state.current.scope=userId;state.current.ready = false; state.current.busy = false; state.current.blocked = false;
    if (!userId) { hydrator.current(null);setStatus('signed-out'); return; }
    setStatus('loading');setError('');
    backendApi.loadWorkspace().then(res => {
      if (state.current.generation !== generation) return;
      const normalized=hydrator.current(res.document);
      state.current.revision = res.revision;
      state.current.saved = JSON.stringify(normalized);
      state.current.ready = true;
      setStatus('saved'); setTick(t => t + 1);
    }).catch(e => { if(state.current.generation === generation) { setStatus('error');setError(e.message); } });
    return () => { if(state.current.generation===generation) {state.current.generation++;state.current.ready=false;} };
  }, [userId, preview]);
  useEffect(() => {
    if(preview || !userId || !state.current.ready || state.current.blocked || state.current.busy) return;
    const serialized = JSON.stringify(document);
    if(serialized === state.current.saved) return;
    setStatus('pending');
    const generation=state.current.generation;
    const timer=setTimeout(async()=> {
      if(state.current.busy || state.current.generation!==generation) return;
      state.current.busy=true;setStatus('saving');
      try {
        const res=await backendApi.saveWorkspace(JSON.parse(serialized),state.current.revision);
        if(state.current.generation!==generation)return;
        state.current.revision=res.revision;state.current.saved=serialized;
        if(JSON.stringify(latest.current)===serialized && res.document && JSON.stringify(res.document)!==serialized){state.current.saved=JSON.stringify(hydrator.current(res.document));}
        setStatus('saved');setError('');
      } catch(e: any) {
        if(state.current.generation!==generation)return;
        state.current.blocked=true;setStatus(e.status===409?'conflict':'error');setError(e.message);
      } finally {
        if(state.current.generation===generation) {state.current.busy=false;setTick(t=>t+1);}
      }
    },700);
    return()=>clearTimeout(timer);
  }, [document, tick, userId, preview]);
  const retry=()=> { if(!state.current.ready) {window.location.reload();return;} state.current.blocked=false;setTick(t=>t+1); };
  const transition=async(collection:string,id:string,target:string,details?:{date:string;reason:string;amount?:number})=>{
    if(blankTestMode&&(collection==='apartments'||collection==='payments'&&(latest.current.payments as any[])?.some(p=>p.id===id&&p.relatedType==='apartment_sale')))throw new Error('Sales are locked in this test.');
    const generation=state.current.generation;
    if(!preview && (!state.current.ready || state.current.busy || state.current.blocked || JSON.stringify(latest.current)!==state.current.saved))throw new Error('Wait until your changes are saved, then retry.');
    const serialized=JSON.stringify(latest.current);state.current.busy=true;setStatus('saving');
    try {
      let res:{revision:number;document:Record<string,any>};
      if(preview){
        const doc=JSON.parse(serialized),row=doc[collection]?.find((r:any)=>r.id===id);if(!row)throw new Error('Document unavailable.');
        const fail=(code:string)=>{throw Object.assign(new Error(code),{code});};
        const materialCheck=(pid:string,date:string,transfer:any)=>{if((doc.apartments||[]).some((u:any)=>u.projectId===pid&&u.status==='sold'&&!u.reversal&&(!u.approvalStatus||u.approvalStatus==='posted')))fail('transferStockLimit');const journal=buildJournal({...doc,steelRecords:doc.steel||[],concreteRecords:doc.concrete||[],laborRecords:doc.labor||[],expenses:doc.expenses||[],payments:doc.payments||[],apartments:doc.apartments||[],projectInvestments:doc.projectInvestments||[]});const value=transfer.currency==='AFN'?roundMoney(transfer.amount/transfer.exchangeRate):transfer.amount;for(const point of [date,todayISO(),...(doc.projectTransfers||[]).filter((t:any)=>t.date>=date).map((t:any)=>t.date)]){const available=roundMoney(journal.filter(e=>e.projectId===pid&&e.date<=point).reduce((sum,e)=>sum+e.lines.filter(l=>l.accountCode==='1500'&&l.costAccountCode===transfer.accountCode).reduce((s,l)=>s+l.debitUSD-l.creditUSD,0),0));if(available+0.001<value)fail('transferStockLimit');}};
        if(target==='reversed'){
          if(!reversibleCollections.includes(collection)||row.approvalStatus!=='posted'||row.reversal)fail('reverseUnavailable');
          const date=details?.date||'',reason=details?.reason.trim()||'';
          const today=todayISO();
          if(reason.length<5||reason.length>1000)fail('reversalReasonHint');
          if(!/^\d{4}-\d{2}-\d{2}$/.test(date)||date<(collection==='apartments'?saleDate(row):(row.date||''))||date>today)fail('reverseInvalidDate');
          if(date<=(doc.appSettings?.closedThrough||''))fail('reverseClosed');
          if(collection==='labor'&&row.paymentStatus==='paid'&&row.paidDate&&date<row.paidDate)fail('reverseInvalidDate');
          if(['expenses','steel','concrete','apartments','labor'].includes(collection)){
            if(collection==='apartments'&&row.status!=='sold')fail('reverseUnavailable');
            const types=({expenses:['expense','retention_release','tax_remittance'],steel:['material_steel','steel'],concrete:['material_concrete','concrete'],apartments:['apartment_sale'],labor:['payroll']} as Record<string,string[]>)[collection];
            const related=doc.payments?.filter((p:any)=>p.relatedId===id&&p.projectId===row.projectId&&types.includes(p.relatedType)&&(!p.approvalStatus||p.approvalStatus==='posted'))||[];
            if(related.some((p:any)=>!p.reversal))fail('reverseNeedsPayments');
            if(related.some((p:any)=>p.reversal.date>date))fail('reverseInvalidDate');
          }
          for(const n of doc.creditNotes||[])if(n.sourceCollection===collection&&n.sourceId===row.id&&n.projectId===row.projectId&&n.approvalStatus==='posted'){if(!n.reversal)fail('creditLatest');if(n.reversal.date>date)fail('reverseInvalidDate');}
          const event={id:crypto.randomUUID(),date,reason,actorId:actor?.id,actorName:actor?.name,at:new Date().toISOString()};
          if(['payments','projectInvestments'].includes(collection)){const amount=details?.amount??effectiveAmount(row);if(!Number.isFinite(amount)||amount<=0||roundMoney(amount)!==amount||amount>effectiveAmount(row))fail('partialLimit');if((row.partialReversals||[]).some((p:any)=>p.date>date))fail('reverseInvalidDate');if(amount<effectiveAmount(row))row.partialReversals=[...(row.partialReversals||[]),{...event,amount}];else row.reversal={...event,...(row.partialReversals?.length?{amount}:{})};}
          else{if(collection==='creditNotes')previewCredit(doc,row,actor,date,true,reason);if(collection==='projectTransfers'&&row.kind==='material_transfer')materialCheck(row.toProjectId,date,row);if(collection==='projectTransfers'&&row.kind==='ownership_transfer'){const project=doc.projects.find((p:any)=>p.id===row.projectId);if(project.currentOwner!==row.newOwner||project.transferHistory?.at(-1)?.id!==row.id)fail('ownershipConflict');project.currentOwner=row.previousOwner;project.clientOwner=row.previousOwner;project.transferHistory.pop();}row.reversal=event;}
        }else{
          const from=row.approvalStatus;const transitions:Record<string,string[]>={draft:['submitted'],submitted:['approved','draft'],approved:['posted','draft']};
          if(row.reversal||!transitions[from]?.includes(target))throw new Error('Invalid approval transition.');
          if(target!=='draft'&&collection!=='apartments'){const total=collection==='labor'?row.daysWorked*row.dailyRate:(row.amount??row.totalAmount??0);if(!row.date||!(collection==='projectTransfers'&&row.kind==='ownership_transfer')&&Math.round(total*100)<=0)fail('validation');}
          if(collection==='projectInvestments'&&target!=='draft'&&!(doc.projectPartners||[]).some((p:any)=>p.id===row.partnerId&&p.projectId===row.projectId))fail('investmentPartnerInvalid');
          if(collection==='labor'&&target!=='draft'&&!['paid','pending'].includes(row.paymentStatus||'pending'))fail('validation');
          if(collection==='apartments'&&target!=='draft'&&row.status==='sold'&&(!(row.salePrice>0)||!saleDate(row)))fail('validation');
          if(collection==='payments'&&target==='posted'){
            const related=({expense:'expenses',retention_release:'expenses',tax_remittance:'expenses',material_steel:'steel',material_concrete:'concrete',apartment_sale:'apartments',payroll:'labor'} as Record<string,string>)[row.relatedType];
            const parent=doc[related]?.find((r:any)=>r.id===row.relatedId&&r.projectId===row.projectId);if(related&&(!parent||parent.reversal||parent.approvalStatus&&parent.approvalStatus!=='posted'))fail('receiptNeedsSale');
            for(const e of parent?.creditAdjustments||[])if(row.date<e.date)fail('receiptInvalidDate');
            if(related==='labor'){if(parent.paymentStatus==='paid'||row.currency!==(parent.currency||'AFN')||row.exchangeRate!==(parent.exchangeRate||70)||row.date<parent.date)fail('validation');const paid=(doc.payments||[]).filter((p:any)=>p.id!==row.id&&p.relatedType==='payroll'&&p.relatedId===parent.id&&p.projectId===parent.projectId&&!p.reversal&&(!p.approvalStatus||p.approvalStatus==='posted')).reduce((s:number,p:any)=>s+effectiveAmount(p),row.amount);if(roundMoney(paid)>Math.max(0,adjustedGross(parent,'labor')-(parent.advanceDeduction||0)))fail('receiptLimit');}
            if(['expenses','steel','concrete'].includes(related)){const a=adjustedInvoiceAmounts(parent),limit=related==='expenses'?(row.relatedType==='retention_release'?a.retention:row.relatedType==='tax_remittance'?a.tax:a.net):adjustedGross(parent);const paid=(doc.payments||[]).filter((p:any)=>p.id===row.id||p.projectId===row.projectId&&p.relatedId===parent.id&&!p.reversal&&(!p.approvalStatus||p.approvalStatus==='posted')&&(related!=='expenses'||p.relatedType===row.relatedType)).reduce((sum:number,p:any)=>sum+(p.currency==='AFN'?effectiveAmount(p)/(p.exchangeRate||70):effectiveAmount(p))*(parent.currency==='AFN'?(parent.exchangeRate||70):1),0);if(roundMoney(paid)>limit)fail('receiptLimit');}
            if(related==='apartments'){
              if(!['sold','reserved'].includes(parent.status))fail('receiptNeedsSale');
              if(row.date<saleDate(parent))fail('receiptInvalidDate');
              const others=(doc.payments||[]).filter((p:any)=>p.id!==row.id);
              if(row.installmentId){const inst=installmentBalances(parent,others).find(i=>i.id===row.installmentId);if(!inst||row.currency!==parent.currency)fail('installmentInvalid');if(paymentInUnitCurrency(parent,row)>inst!.remainingAmount)fail('receiptLimit');}
              const paid=others.filter((p:any)=>p.relatedType==='apartment_sale'&&p.relatedId===parent.id&&p.projectId===parent.projectId&&!p.reversal&&(!p.approvalStatus||p.approvalStatus==='posted')).reduce((sum:number,p:any)=>sum+paymentInUnitCurrency(parent,p),paymentInUnitCurrency(parent,row));if(Math.round(paid*100)>Math.round(adjustedGross(parent)*100))fail('receiptLimit');
            }
          }
          if(collection==='creditNotes'&&target==='posted'){if(row.date>todayISO()||row.date<=(doc.appSettings?.closedThrough||''))fail('reverseInvalidDate');previewCredit(doc,row,actor,row.date);}
          if(collection==='apartments'&&target==='posted'&&row.status==='sold'){const project=doc.projects.find((p:any)=>p.id===row.projectId);project.inventoryAreaHistory=project.inventoryAreaHistory?.length?project.inventoryAreaHistory:[{date:'0001-01-01',totalAreaM2:computeSellableAreaM2(inventoryUnits(doc.apartments||[]).filter(u=>u.projectId===row.projectId),project)}];if(project.inventoryAreaHistory.at(-1).date>saleDate(row))fail('geometryDate');}
          if(collection==='projectTransfers'&&target==='posted'){if(row.date>todayISO())fail('validation');if(row.kind==='material_transfer')materialCheck(row.projectId,row.date,row);}
          if(collection==='projectTransfers'&&target==='posted'&&row.kind==='ownership_transfer'){const project=doc.projects.find((p:any)=>p.id===row.projectId);const owner=project.currentOwner||project.clientOwner||'';if((project.transferHistory||[]).at(-1)?.date>row.date)fail('ownershipConflict');if(owner&&owner!==row.previousOwner)fail('ownershipConflict');project.initialOwner=project.initialOwner||owner||row.previousOwner;project.currentOwner=row.newOwner;project.clientOwner=row.newOwner;project.transferHistory=[...(project.transferHistory||[]),{id:row.id,date:row.date,previousOwner:row.previousOwner,newOwner:row.newOwner}];}
          row.approvalStatus=target;row.approvalHistory=[...(row.approvalHistory||[]),{from,to:target,actorId:actor?.id,actorName:actor?.name,at:new Date().toISOString()}];
        }
        res={revision:0,document:doc};
      }else res=await backendApi.transitionApproval(collection,id,target,state.current.revision,details);
      if(state.current.generation!==generation)return;
      state.current.revision=res.revision;
      if(JSON.stringify(latest.current)!==serialized){state.current.blocked=true;setStatus('conflict');setError('The document changed during approval. Reload to see the server result.');return;}
      state.current.saved=JSON.stringify(hydrator.current(res.document));setStatus(preview?'demo':'saved');setError('');
    }catch(e:any){if(state.current.generation===generation){setStatus(preview?'demo':e.status===409&&!e.code?'conflict':'saved');setError(e.message);if(e.status===409&&!e.code)state.current.blocked=true;}throw e;}
    finally{if(state.current.generation===generation){state.current.busy=false;setTick(t=>t+1);}}
  };
  const publishStudio=async(payload:any)=>{
    const generation=state.current.generation,serialized=JSON.stringify(latest.current);
    if(!state.current.ready||state.current.busy||state.current.blocked||serialized!==state.current.saved)throw Error('Save pending workspace changes before publishing the original website.');
    state.current.busy=true;setStatus('saving');
    try{const result=(await backendApi.studio('publish',payload)).data;if(state.current.generation!==generation)throw Error('Workspace changed while publishing.');
      if(result.workspace){state.current.revision=result.workspace.revision;if(JSON.stringify(latest.current)!==serialized){state.current.blocked=true;setStatus('conflict');setError('The workspace changed while publishing. Reload to see the saved website.');return result;}state.current.saved=JSON.stringify(hydrator.current(result.workspace.document));}
      setStatus('saved');setError('');return result;
    }catch(e:any){if(state.current.generation===generation&&!state.current.blocked)setStatus('saved');throw e;}
    finally{if(state.current.generation===generation){state.current.busy=false;setTick(t=>t+1);}}
  };
  const visibleStatus:WorkspaceStatus=!preview&&userId!==state.current.scope?'loading':!preview&&status==='saved'&&state.current.ready&&JSON.stringify(document)!==state.current.saved?'pending':status;
  return {status:visibleStatus,error,retry,transition,publishStudio};
}
