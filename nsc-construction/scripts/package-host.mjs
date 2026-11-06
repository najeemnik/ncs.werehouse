import {mkdir,rm,cp,stat} from 'node:fs/promises';
if(await stat('dist/test-mode.json').catch(()=>null))throw new Error('This is a no-login test build. Use test_upload for tests; rebuild production before making a production hosting package.');
await rm('host_upload',{recursive:true,force:true});await mkdir('host_upload',{recursive:true});
await cp('dist','host_upload',{recursive:true});await cp('api','host_upload/api',{recursive:true});await cp('setup','host_upload/setup',{recursive:true});await cp('.htaccess','host_upload/.htaccess');
console.log('Public hosting package created. Database scripts and secrets are excluded.');
