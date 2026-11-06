import {readFileSync,writeFileSync} from 'node:fs';
import {initialUsers} from '../src/data/seedData';
const root='previews';const master=initialUsers.find(u=>u.isMasterSuperAdmin)!;
const bootstrap=`<script>try{localStorage.setItem('nsc_preview_currentUser',${JSON.stringify(JSON.stringify(master))});}catch{}location.hash='#/office7/preview-private';</script>`;
writeFileSync(root+'/NSC-Office7-Preview.html',readFileSync(root+'/NSC-Updated-Preview.html','utf8').replace('<head>','<head>'+bootstrap));
console.log('Separate Office Seven sample preview prepared; production entry is assigned server-side.');
