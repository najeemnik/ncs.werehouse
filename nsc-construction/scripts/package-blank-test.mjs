import {mkdir,rm,cp,writeFile,readFile,readdir} from 'node:fs/promises';

// Only static files are shipped. No PHP API, real credentials, schema or setup endpoints.
await writeFile('dist/test-mode.json',JSON.stringify({mode:'blank-browser-test',authentication:'none',storage:'browser-local'})+'\n');
await rm('test_upload',{recursive:true,force:true});await mkdir('test_upload',{recursive:true});
await cp('dist','test_upload',{recursive:true});
await cp('.htaccess','test_upload/.htaccess');
const names=await readdir('test_upload/assets');
const script=await readFile('test_upload/assets/'+names.find(n=>n.endsWith('.js')),'utf8');
if(!script.includes('nsc_blank_test_v1_'))throw new Error('Expected blank test build; refusing to package another build.');
console.log('Empty no-login test package created in test_upload; browser-local records; no PHP API or database credentials.');
