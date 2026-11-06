import {readFile,readdir,writeFile,mkdir,cp,rm} from 'node:fs/promises';
const root='previews';await mkdir(root,{recursive:true});let html=await readFile('dist/index.html','utf8');
for(const f of await readdir('dist/assets')){
 if(f.endsWith('.js')&&html.includes('/assets/'+f)){const js=(await readFile('dist/assets/'+f,'utf8')).replace(/<\/script/gi,'<\\/script');html=html.replace(new RegExp('<script type="module"[^>]*src="/assets/'+f.replaceAll('.','\\.')+'"[^>]*></script>'),()=>'<script type="module">'+js+'</script>');}
 if(f.endsWith('.css')&&html.includes('/assets/'+f)){const css=await readFile('dist/assets/'+f,'utf8');html=html.replace(new RegExp('<link[^>]*href="/assets/'+f.replaceAll('.','\\.')+'"[^>]*>'),()=>'<style>'+css+'</style>');}
}
for(const path of ['assets/logo.png','assets/creator-logo.png','icons/favicon-32.png','icons/favicon-64.png','icons/icon-180.png']){const data='data:image/png;base64,'+(await readFile('public/'+path)).toString('base64');html=html.replaceAll('/'+path,data);}html=html.replace(/<link[^>]*rel="manifest"[^>]*>/g,'');
await writeFile(root+'/NSC-Updated-Preview.html',html);await rm(root+'/preview-site',{recursive:true,force:true});await cp('dist',root+'/preview-site',{recursive:true});console.log('Updated portable preview prepared. PWA installation requires serving the preview site over HTTPS.');
