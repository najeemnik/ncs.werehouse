/** Google Drive OAuth is independent of NSC login. Tokens stay in memory. */
type DriveUser={uid:string;email:string|null};
type Listener={success?:(user:DriveUser,token:string)=>void;failure?:()=>void};
let token:string|null=null,expires=0,loading:Promise<void>|null=null;
const listeners=new Set<Listener>();
const user:DriveUser={uid:'google-drive',email:null};
export function clearDriveToken(){token=null;expires=0;for(const listener of listeners)listener.failure?.();}
export function initAuth(success?:Listener['success'],failure?:Listener['failure']){const listener={success,failure};listeners.add(listener);if(token&&Date.now()<expires)success?.(user,token);else failure?.();return()=>{listeners.delete(listener);};}
async function loadSDK(){
 if((window as any).google?.accounts?.oauth2)return;
 if(!loading)loading=new Promise<void>((resolve,reject)=>{const script=document.createElement('script');script.src='https://accounts.google.com/gsi/client';script.async=true;const timer=setTimeout(()=>{script.remove();loading=null;reject(new Error('Google authentication timed out.'));},15000);script.onload=()=>{clearTimeout(timer);resolve();};script.onerror=()=>{clearTimeout(timer);loading=null;reject(new Error('Google authentication could not load.'));};document.head.append(script);});
 await loading;
}
export async function googleSignIn():Promise<{user:DriveUser;accessToken:string}>{
 const clientId=import.meta.env.VITE_GOOGLE_CLIENT_ID;
 if(!clientId)throw new Error('Configure VITE_GOOGLE_CLIENT_ID on your host first.');
 await loadSDK();
 return new Promise((resolve,reject)=>{const client=(window as any).google.accounts.oauth2.initTokenClient({client_id:clientId,scope:'https://www.googleapis.com/auth/drive.file',callback:(response:any)=>{if(response.error||!response.access_token){reject(new Error('Google Drive authorization was declined.'));return;}token=response.access_token;expires=Date.now()+Math.max(0,(Number(response.expires_in)||3600)-60)*1000;for(const listener of listeners)listener.success?.(user,token!);resolve({user,accessToken:token!});},error_callback:()=>reject(new Error('Google Drive authorization was closed.'))});client.requestAccessToken({prompt:'consent'});});
}
export async function getAccessToken(){if(Date.now()>=expires)clearDriveToken();return token;}
export async function googleSignOut(){const previous=token;clearDriveToken();if(previous)(window as any).google?.accounts?.oauth2?.revoke(previous,()=>{});}
