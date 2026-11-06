import React,{useEffect,useState} from 'react';
import {useApp} from '../context/AppContext';
import {displayDate,todayISO,timezoneLabel,clockTime} from './presentation';
import {locales} from './i18n';
import type {ClockConfig} from './DesignStudio';
export function ClockWidget({clock}:{clock:ClockConfig}){
 const style={fontSize:Math.round(14*(clock.size||100)/100)+'px','--nsc-clock-size':(clock.size||100)+'px'} as React.CSSProperties;
 const {language,appSettings}=useApp();const [now,setNow]=useState(new Date());useEffect(()=>{const id=setInterval(()=>setNow(new Date()),clock.kind===4?1000:1000);return()=>clearInterval(id);},[clock.kind]);
 const parts=new Intl.DateTimeFormat('en-GB',{timeZone:clock.timezone,hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:false}).formatToParts(now);const val=(name:string)=>Number(parts.find(p=>p.type===name)?.value||0);const h=val('hour')%12,m=val('minute'),s=val('second');
 if(clock.kind===3||clock.kind===4)return <div className="nsc-clock analog" style={style}><svg viewBox="0 0 120 120" aria-label={clockTime(language,now,clock.timezone,true,true,appSettings)}><circle cx="60" cy="60" r="55" fill="var(--nsc-white)" stroke="var(--nsc-line)" strokeWidth="2"/>{Array.from({length:12},(_,i)=><line key={i} x1="60" y1="9" x2="60" y2="15" stroke="var(--nsc-muted)" transform={`rotate(${i*30} 60 60)`}/>)}<line x1="60" y1="60" x2="60" y2="32" stroke="var(--nsc-ink)" strokeWidth="5" strokeLinecap="round" transform={`rotate(${h*30+m/2} 60 60)`}/><line x1="60" y1="60" x2="60" y2="20" stroke="var(--nsc-ink)" strokeWidth="3" strokeLinecap="round" transform={`rotate(${m*6+s/10} 60 60)`}/>{clock.kind===4&&<line x1="60" y1="69" x2="60" y2="16" stroke="var(--nsc-accent)" transform={`rotate(${s*6} 60 60)`}/>}<circle cx="60" cy="60" r="4" fill="var(--nsc-accent)"/></svg><small>{timezoneLabel(language,clock.timezone,appSettings)}</small></div>;
 return <div className={'nsc-clock digital clock-'+clock.kind} style={style}><time>{clockTime(language,now,clock.timezone,clock.kind===2,clock.kind===2,appSettings)}</time>{clock.kind===1&&<small>{displayDate(language,todayISO(clock.timezone))}</small>}{clock.kind===5&&<small>{timezoneLabel(language,clock.timezone,appSettings)}</small>}</div>;
}
