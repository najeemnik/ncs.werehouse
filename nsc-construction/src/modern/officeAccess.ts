import type {User} from '../types';
export const previewOfficeLink='#/office7/preview-private';
export function ownsOffice(user:User|null,preview=import.meta.env.VITE_PREVIEW==='true'):boolean{return !!user&&(preview?!!user.isMasterSuperAdmin:user.isOffice7Owner===true);}
export function privateOfficeLink(user:User|null):string|null{return ownsOffice(user)?import.meta.env.VITE_PREVIEW==='true'?previewOfficeLink:user?.office7Link||null:null;}
export function atPrivateOffice(user:User|null):boolean {const link=privateOfficeLink(user);return !!link&&window.location.hash===link;}
export function absoluteOfficeLink(user:User|null):string {const link=privateOfficeLink(user);return link?window.location.href.split('#')[0]+link:'';}
