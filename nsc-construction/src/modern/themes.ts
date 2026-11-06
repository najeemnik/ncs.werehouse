import {useEffect,useState} from 'react';
import type {Language} from '../types';

export const themes = [
 {id:'skeuomorphism',names:['Skeuomorphism','طرح طبیعی','طبیعي بڼه']},
 {id:'bento',names:['Bento Grid Layouts','چیدمان بنتو','د بنتو ترتیب']},
 {id:'aurora',names:['Auroramorphic / Mesh Gradients','گرادیانت مش و شفق','د شفق رنګونه']},
 {id:'handdrawn',names:['Hand-Drawn / Imperfect UI','طرح دست‌کشیده','لاس کښل شوې بڼه']},
 {id:'figma',names:['Figma tips','طرح فیگما','د فیګما بڼه']},
 {id:'glassmorphism',names:['Glassmorphism','طرح شیشه‌یی','ښیښه‌ییزه بڼه']},
 {id:'neumorphism',names:['Neumorphism (Soft UI)','طرح نرم','نرمه بڼه']},
 {id:'claymorphism',names:['Claymorphism','طرح گِلی','خټینه بڼه']},
] as const;
export type ThemeId=typeof themes[number]['id'];
export const defaultThemeChoices:ThemeId[]=['glassmorphism','bento','aurora','neumorphism'];
export const themeName=(id:string,language:Language)=>themes.find(t=>t.id===id)?.names[language==='en'?0:language==='fa'?1:2]||id;
export function themeChoices(settings:any):ThemeId[]{const raw=settings?.dashboardDesign?.themeChoices;return Array.isArray(raw)&&raw.length===4&&new Set(raw).size===4&&raw.every(id=>themes.some(t=>t.id===id))?raw:defaultThemeChoices;}
type Appearance={theme?:ThemeId;mode?:'light'|'dark'|'auto'};
export function usePersonalAppearance(userId:string|undefined,settings:any){
 const key='nsc_personal_appearance:'+(userId||'signed-out');
 const [preference,setPreference]=useState<Appearance>({}),[now,setNow]=useState(Date.now());
 useEffect(()=>{try{setPreference(JSON.parse(localStorage.getItem(key)||'{}'));}catch{setPreference({});}},[key]);
 useEffect(()=>{const timer=setInterval(()=>setNow(Date.now()),60000);return()=>clearInterval(timer);},[]);
 const change=(patch:Appearance)=>setPreference(previous=>{const next={...previous,...patch};try{localStorage.setItem(key,JSON.stringify(next));}catch{}return next;});
 const choices=themeChoices(settings),fallback=choices.includes(settings?.dashboardDesign?.defaultTheme)?settings.dashboardDesign.defaultTheme:choices[0];
 const theme:ThemeId=choices.includes(preference?.theme as ThemeId)?preference.theme!:fallback;
 const mode:'auto'|'dark'|'light'=preference?.mode==='auto'?'auto':preference?.mode==='dark'?'dark':'light';
 const hour=new Date(now).getHours(),dark=mode==='dark'||mode==='auto'&&(hour<6||hour>=18);
 return {theme,mode,dark,change};
}

export function themePalette(id:string):Record<string,string>{
 const base={bg:'#e9eef7',white:'#ffffff',ink:'#14263d',muted:'#52657c',line:'#d5dfed',sidebar:'#f8fafc',sidebarText:'#334155'};
 const palettes:Record<string,Partial<typeof base>>={glassmorphism:{bg:'#e9eef7',white:'#f8fbff',sidebar:'#f8fbff'},bento:{bg:'#eff2f6',white:'#ffffff',sidebar:'#ffffff'},aurora:{bg:'#eef2ff',white:'#f8f7ff',sidebar:'#f0edff'},handdrawn:{bg:'#fff7e5',white:'#fffdf6',line:'#a89471',sidebar:'#fff3d6'},figma:{bg:'#f0edf8',white:'#ffffff',sidebar:'#f7f4ff'},neumorphism:{bg:'#e6edf8',white:'#e6edf8',sidebar:'#e6edf8',line:'#d0d9e8'},skeuomorphism:{bg:'#e8e0d3',white:'#f9f2e8',sidebar:'#efe5d5',line:'#c5b79f'},claymorphism:{bg:'#eae9f8',white:'#f5f2ff',sidebar:'#efebfc'}};
 return {...base,...palettes[id]};
}
