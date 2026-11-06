import {readFile,writeFile,readdir} from 'node:fs/promises';
import {createHash} from 'node:crypto';
const html=await readFile('dist/index.html');
const assets=(await readdir('dist/assets')).filter(f=>!f.endsWith('.map')).map(f=>'/assets/'+f);
const shell=['/','/index.html','/offline.html','/manifest.webmanifest','/icons/icon-192.png','/icons/icon-512.png','/icons/icon-180.png','/icons/maskable-512.png','/icons/favicon-32.png','/icons/favicon-64.png',...assets];
const fingerprint=createHash('sha256').update(html);for(const path of shell.filter(p=>p!=='/'&&p!=='/index.html'))fingerprint.update(await readFile('dist'+path));const version=fingerprint.digest('hex').slice(0,16);
await writeFile('dist/sw.js',`const CACHE=${JSON.stringify('nsc-shell-'+version)};const SHELL=${JSON.stringify(shell)};const ASSETS=new Set(SHELL);
self.addEventListener('install',event=>event.waitUntil(caches.open(CACHE).then(cache=>cache.addAll(SHELL))));
self.addEventListener('activate',event=>event.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(k=>k.startsWith('nsc-shell-')&&k!==CACHE).map(k=>caches.delete(k)))).then(()=>self.clients.claim())));
self.addEventListener('message',event=>{if(event.data?.type==='SKIP_WAITING')self.skipWaiting();if(event.data?.type==='CLEAR_PRIVATE_DATA')event.waitUntil(Promise.resolve());});
self.addEventListener('fetch',event=>{const url=new URL(event.request.url);if(event.request.method!=='GET'||url.origin!==self.location.origin||url.pathname.startsWith('/api/'))return;
if(event.request.mode==='navigate'&&(url.pathname==='/'||url.pathname==='/index.html')){event.respondWith(fetch(event.request).then(response=>{if(response.ok){const clone=response.clone();caches.open(CACHE).then(cache=>cache.put('/index.html',clone));}return response;}).catch(()=>caches.match('/index.html').then(r=>r||caches.match('/offline.html'))));return;}
if(ASSETS.has(url.pathname))event.respondWith(caches.match(url.pathname).then(r=>r||fetch(event.request)));});
self.addEventListener('notificationclick',event=>{event.notification.close();event.waitUntil(self.clients.matchAll({type:'window'}).then(windows=>{if(windows.length)return windows[0].focus();return self.clients.openWindow('/');}));});
`);
console.log('Generated service worker: '+version+'; API responses are excluded.');
