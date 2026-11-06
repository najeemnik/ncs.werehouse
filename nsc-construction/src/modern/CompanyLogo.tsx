import React,{useState} from 'react';
import {useApp} from '../context/AppContext';
import {useText} from './EntityView';
import {rasterImage} from './rasterImage';
import {canAccess} from './model';
export function companyLogo(settings:any){const value=settings?.logoUrl;return typeof value==='string'&&(/^data:image\/(png|jpeg|webp);base64,/.test(value)||value.startsWith('/api/'))?value:'';}
export function CompanyLogo(){
 const a=useApp(),t=useText(),[busy,setBusy]=useState(false),[error,setError]=useState('');
 const say=(en:string,fa:string,ps:string)=>a.language==='en'?en:a.language==='ps'?ps:fa;
 if(!canAccess(a.currentUser,a.currentProject?.id||'','settings','edit',a.appSettings))return null;
 return <div className="nsc-card nsc-settings-card"><h2>{say('Your company logo','لوگوی شرکت شما','ستاسو د شرکت نښان')}</h2><label><span>{t('logo')}</span><input aria-label={say('Your company logo','لوگوی شرکت شما','ستاسو د شرکت نښان')} type="file" accept="image/png,image/jpeg,image/webp" disabled={busy} onChange={async e=>{const file=e.target.files?.[0];if(!file)return;setBusy(true);setError('');try{const logo=await rasterImage(file,true);a.updateAppSettings({logoUrl:logo});}catch{setError(t('scanInvalidImage'));}finally{setBusy(false);e.target.value='';}}}/></label>{companyLogo(a.appSettings)&&<><img className="nsc-logo-preview" src={companyLogo(a.appSettings)} alt={t('logo')}/><button className="nsc-text-button" disabled={busy} onClick={()=>a.updateAppSettings({logoUrl:'/assets/logo.png'})}>{t('delete')}</button></>}{error&&<p role="alert" className="nsc-error">{error}</p>}</div>;
}
