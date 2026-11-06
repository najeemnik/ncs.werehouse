import React from 'react';
import {useApp,AppDesignPreview} from '../../context/AppContext';
import {Dashboard} from '../Dashboard';
import {EntityView} from '../EntityView';
import {Reports} from '../Reports';
import {Settings} from '../Settings';
import {Staff} from '../Staff';
import {About} from '../About';
import {Services,ActivityHistory} from '../Services';
import {Notifications} from '../Notifications';
import {getDesign} from '../designModel';
import {modules} from '../model';
import {Canvas,BoundMetric,BusinessView} from './Runtime';
import type {StudioConfig,Page,Device} from './model';
export function CorePage({cfg,page,device,navigate,readOnly=false,canvasProps={},initialCreate=false,initialValues={}}:{cfg:StudioConfig;page:Page;device:Device;navigate:(id:string,create?:boolean,initial?:any)=>void;readOnly?:boolean;canvasProps?:Partial<React.ComponentProps<typeof Canvas>>;initialCreate?:boolean;initialValues?:any}){
 const a=useApp(),route=page.route||'',render=(parts:Record<string,React.ReactNode>)=><Canvas cfg={cfg} page={page} device={device} language={a.language} navigate={navigate} renderModule={readOnly?undefined:(id,kind,field)=>kind==='metric'?<BoundMetric cfg={cfg} moduleId={id} field={field}/>:<BusinessView key={id+(a.currentProject?.id||'')} cfg={cfg} moduleId={id} compact/>} {...canvasProps} renderCore={source=><div className="studio-core-slot" inert={readOnly}>{parts[source.split(':')[1]]||null}</div>}/>;
 let body:React.ReactNode;
 if(route==='dashboard')body=<Dashboard navigate={navigate} renderLayout={render}/>;
 else if(modules.some(m=>m.id===route))body=<EntityView moduleId={route} initialCreate={!readOnly&&initialCreate} initialValues={initialValues} onNavigate={navigate} renderLayout={render}/>;
 else{const original=route==='reports'||route==='accounting'?<Reports accounting={route==='accounting'}/>:route==='settings'?<Settings/>:route==='staff'?<Staff/>:route==='about'?<About/>:route==='audit_logs'?<ActivityHistory/>:route==='services'?<Services navigate={navigate}/>:route==='notifications'?<Notifications navigate={navigate}/>:null;body=render({page:original});}
 return <div className="studio-existing-page" data-core-route={route}>{body}</div>;
}
export function CorePreview({cfg,page,device,language,...props}:{cfg:StudioConfig;page:Page;device:Device;language:'en'|'fa'|'ps';canvasProps?:Partial<React.ComponentProps<typeof Canvas>>;navigate:(id:string)=>void}){const a=useApp(),d=cfg.siteDesign||getDesign(a.appSettings);const style:any={'--nsc-white':d.palette.white,'--nsc-bg':d.palette.bg,'--nsc-ink':d.palette.ink,'--nsc-muted':d.palette.muted,'--nsc-line':d.palette.line,'--nsc-accent':d.accentColor,'--nsc-scale':d.fontScale/100};return <AppDesignPreview design={cfg.siteDesign} language={language}><div style={style}><CorePage cfg={cfg} page={page} device={device} readOnly {...props}/></div></AppDesignPreview>;}
