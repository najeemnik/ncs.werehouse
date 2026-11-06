import {useEffect,useState,useCallback} from 'react';
export function useInstall(){
 const [prompt,setPrompt]=useState<any>(null),[installed,setInstalled]=useState(()=>window.matchMedia('(display-mode: standalone)').matches||!!(navigator as any).standalone),[updateReady,setUpdateReady]=useState(false);
 useEffect(()=>{const ready=(e:Event)=>{e.preventDefault();setPrompt(e);};const onInstalled=()=>{setPrompt(null);setInstalled(true);};window.addEventListener('beforeinstallprompt',ready);window.addEventListener('appinstalled',onInstalled);
 if('serviceWorker'in navigator&&window.location.protocol!=='file:')navigator.serviceWorker.register('/sw.js').then(reg=>{if(reg.waiting)setUpdateReady(true);reg.addEventListener('updatefound',()=>{reg.installing?.addEventListener('statechange',()=>{if(reg.installing?.state==='installed'&&navigator.serviceWorker.controller)setUpdateReady(true);});});}).catch(()=>{});
 return()=>{window.removeEventListener('beforeinstallprompt',ready);window.removeEventListener('appinstalled',onInstalled);};},[]);
 const install=useCallback(async()=>{if(prompt){await prompt.prompt();setPrompt(null);}else window.dispatchEvent(new Event('nsc-install-guide'));},[prompt]);
 const activateUpdate=async()=>{const reg=await navigator.serviceWorker.getRegistration();if(reg?.waiting){navigator.serviceWorker.addEventListener('controllerchange',()=>window.location.reload(),{once:true});reg.waiting.postMessage({type:'SKIP_WAITING'});}else window.location.reload();};
 return {available:!installed&&window.location.protocol!=='file:',install,updateReady,activateUpdate};
}
