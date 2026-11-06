import {clearLocalFiles} from '../services/localFiles';
import type {User} from '../types';

// Anonymous access is confined to the explicitly selected, browser-local test build.
export const blankTestMode=import.meta.env?.VITE_PREVIEW==='true'&&import.meta.env?.VITE_BLANK_TEST==='true';
export const blankTestStoragePrefix='nsc_blank_test_v1_';
export const blankTestUser:User={
 id:'nsc-blank-test-owner-v1',username:'test',email:'test@example.invalid',
 name:'Test workspace',companyName:'Nik Smart Count',role:'admin',active:true,
 customEnabledModules:{apartments:false},verified:true,subscriptionPlan:'lifetime',createdAt:'2026-10-14T00:00:00Z',
};

export async function resetBlankTest():Promise<void>{
 if(!blankTestMode)return;
 await clearLocalFiles();
 const studioPrefix='nsc_studio_v1_'+blankTestUser.id;
 for(const key of Object.keys(localStorage))if(key.startsWith(blankTestStoragePrefix)||key.startsWith(studioPrefix)||key==='nsc_personal_appearance:'+blankTestUser.id)localStorage.removeItem(key);
 window.location.hash='';window.location.reload();
}
