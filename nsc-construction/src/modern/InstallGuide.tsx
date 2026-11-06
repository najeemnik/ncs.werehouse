import React,{useEffect,useRef,useState} from 'react';
import {useApp} from '../context/AppContext';
import {useText} from './EntityView';
export function InstallGuide(){
 const a=useApp(),t=useText(),[open,setOpen]=useState(false),close=useRef<HTMLButtonElement>(null),previous=useRef<HTMLElement|null>(null);
 useEffect(()=>{const show=()=>{previous.current=document.activeElement as HTMLElement;setOpen(true);};window.addEventListener('nsc-install-guide',show);return()=>window.removeEventListener('nsc-install-guide',show);},[]);
 useEffect(()=>{if(!open)return;close.current?.focus();const key=(e:KeyboardEvent)=>{if(e.key==='Escape')setOpen(false);if(e.key==='Tab'){e.preventDefault();close.current?.focus();}};window.addEventListener('keydown',key);return()=>{window.removeEventListener('keydown',key);previous.current?.focus();};},[open]);
 if(!open)return null;
 const say=(en:string,fa:string,ps:string)=>a.language==='en'?en:a.language==='ps'?ps:fa;
 const ios=/iPad|iPhone|iPod/.test(navigator.userAgent)||(navigator.platform==='MacIntel'&&navigator.maxTouchPoints>1);
 return <div className="nsc-modal-backdrop" onMouseDown={e=>{if(e.currentTarget===e.target)setOpen(false);}}><div className="nsc-modal" role="dialog" aria-modal="true" aria-label={t('install')}><div className="nsc-modal-heading"><h2>{t('install')}</h2><button ref={close} className="nsc-button secondary" onClick={()=>setOpen(false)}>{t('close')}</button></div><div style={{padding:24,lineHeight:1.9}}>{ios?<p>{say('Open this website in Safari. Tap Share, choose Add to Home Screen, then Add.','سایت را در Safari باز کن؛ Share، بعد Add to Home Screen و سپس Add را بزن.','سایټ په Safari کې پرانیزئ؛ Share، بیا Add to Home Screen او Add ووهئ.')}</p>:<p>{say('Open your browser menu and choose Install app or Add to Home screen. On iPhone, open Safari and use Share → Add to Home Screen.','از منوی مرورگر Install app یا Add to Home screen را انتخاب کن. در آیفون، Safari را باز کن و Share → Add to Home Screen را بزن.','د براوزر له مېنو Install app یا Add to Home screen وټاکئ. په آیفون کې Safari او Share → Add to Home Screen وکاروئ.')}</p>}<p>{say('Use the HTTPS website address. The available installation options depend on your browser.','آدرس HTTPS سایت را استفاده کن؛ گزینه‌های نصب به مرورگر بستگی دارد.','د سایټ HTTPS پته وکاروئ؛ د نصب اختیارونه په براوزر پورې تړلي دي.')}</p></div></div></div>;
}
