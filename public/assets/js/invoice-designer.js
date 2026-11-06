/* Safe declarative editor. User text is always assigned through textContent/value. */
(() => {
 'use strict';
 const node=document.getElementById('invoiceDesignerConfig');if(!node)return;
 const config=JSON.parse(node.textContent),layout=config.layout,labels=config.labels,form=document.getElementById('invoiceDesignerForm'),message=document.getElementById('designMessage');
 let drag=null,previewRequest=0;
 const el=(tag,text,cls)=>{const n=document.createElement(tag);if(text!==undefined)n.textContent=text;if(cls)n.className=cls;return n;};
 const sync=()=>{layout.font_size=Number(document.getElementById('designFontSize').value);layout.spacing=document.getElementById('designSpacing').value;document.getElementById('invoiceLayoutJson').value=JSON.stringify(layout);};
 const group=type=>type==='sections'?layout.sections:type==='columns'?layout.columns:layout.blocks;
 function reorder(type,from,to){const list=group(type);if(to<0||to>=list.length||to===from)return;const [item]=list.splice(from,1);list.splice(to,0,item);render();document.querySelector(`[data-move-group="${type}"][data-index="${to}"]`)?.focus();}
 function actions(row,type,index,removable){
  const list=group(type),tools=el('div',undefined,'design-row-actions');
  const handle=el('button','⠿','design-drag-handle');handle.type='button';handle.draggable=true;handle.setAttribute('aria-label',labels.move);handle.title=labels.move;
  handle.addEventListener('dragstart',event=>{drag={type,index};event.dataTransfer.effectAllowed='move';event.dataTransfer.setData('text/plain',type+':'+index);});handle.addEventListener('dragend',()=>{drag=null;});tools.append(handle);
  for(const [delta,label] of [[-1,labels.up],[1,labels.down]]){const b=el('button',delta===-1?'↑':'↓');b.type='button';b.title=label;b.setAttribute('aria-label',label);b.disabled=index+delta<0||index+delta>=list.length;b.dataset.moveGroup=type;b.dataset.index=index;b.addEventListener('click',()=>reorder(type,index,index+delta));tools.append(b);}
  if(removable){const b=el('button',labels.remove);b.type='button';b.addEventListener('click',()=>{list.splice(index,1);render();});tools.append(b);}
  row.addEventListener('dragover',e=>{if(drag?.type===type){e.preventDefault();e.dataTransfer.dropEffect='move';}});row.addEventListener('drop',e=>{if(drag?.type===type){e.preventDefault();reorder(type,drag.index,index);drag=null;}});row.append(tools);
 }
 function check(text,value,change){const label=el('label',undefined,'design-check'),input=el('input');input.type='checkbox';input.checked=value;input.addEventListener('change',()=>{change(input.checked);sync();});label.append(input,document.createTextNode(text));return label;}
 function select(text,options,value,change){const label=el('label',text),input=el('select',undefined,'form-control');for(const [v,t] of Object.entries(options)){const option=el('option',t);option.value=v;option.selected=String(value)===v;input.append(option);}input.addEventListener('change',()=>{change(input.value);sync();});label.append(input);return label;}
 function texts(row,values,long=false){const details=el('details');details.append(el('summary',labels.text));const grid=el('div',undefined,'design-label-grid');for(const [lang,name] of Object.entries(config.languages)){const label=el('label',name),input=el(long?'textarea':'input',undefined,'form-control');if(!long)input.type='text';input.maxLength=long?1000:80;input.value=values[lang]||'';input.dataset.labelLanguage=lang;input.addEventListener('input',()=>{values[lang]=input.value;sync();});label.append(input);grid.append(label);}details.append(grid);row.append(details);}
 function render(){
  const sections=document.getElementById('designSections'),columns=document.getElementById('designColumns'),blocks=document.getElementById('designBlocks');sections.replaceChildren();columns.replaceChildren();blocks.replaceChildren();
  layout.sections.forEach((section,index)=>{const row=el('div',undefined,'design-row');row.dataset.section=section.id;row.append(el('strong',config.sections[section.id]));const required=['header','customer','items','totals'].includes(section.id);const visible=check(labels.visible,section.visible,v=>section.visible=v);visible.querySelector('input').disabled=required;row.append(visible,select(labels.align,{start:labels.start,center:labels.center,end:labels.end},section.align,v=>section.align=v));actions(row,'sections',index,false);sections.append(row);});
  layout.columns.forEach((column,index)=>{const row=el('div',undefined,'design-row design-column');row.dataset.column=column.id;row.append(el('strong',config.sources[column.source]));row.append(select(labels.width,{1:'1',2:'2',3:'3',4:'4',5:'5'},column.width,v=>column.width=Number(v)));if(column.source==='custom')row.append(check(labels.required,column.required,v=>column.required=v));texts(row,column.labels);actions(row,'columns',index,!['product','quantity','unit_price','total'].includes(column.source));columns.append(row);});
  layout.blocks.forEach((block,index)=>{const row=el('div',undefined,'design-row');row.append(select(labels.after,config.sections,block.after,v=>block.after=v));texts(row,block.labels,true);actions(row,'blocks',index,true);blocks.append(row);});sync();
 }
 document.getElementById('addDesignColumn').addEventListener('click',()=>{if(layout.columns.length>=12||layout.columns.filter(c=>c.source==='custom').length>=6){message.textContent=labels.limit;return;}let n=1;while(layout.columns.some(c=>c.id==='custom_'+n))n++;layout.columns.push({id:'custom_'+n,source:'custom',labels:{en:'',da:'',ps:''},width:3,required:false});render();document.querySelector(`[data-column="custom_${n}"] details`).open=true;document.querySelector(`[data-column="custom_${n}"] input[type=text]`)?.focus();});
 document.getElementById('addDesignBlock').addEventListener('click',()=>{if(layout.blocks.length>=4){message.textContent=labels.limit;return;}layout.blocks.push({after:'notes',labels:{en:'',da:'',ps:''}});render();});
 async function preview(){
  sync();const serial=++previewRequest,button=document.getElementById('previewDesign');button.disabled=true;message.textContent=labels.loading;
  try{const data=new FormData(form);data.set('designer_action','preview');data.delete('shop_logo');const response=await fetch(form.action||location.href,{method:'POST',body:data,credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest'}});if(!response.ok){let error;try{error=(await response.json()).error;}catch(_){}throw new Error(error||labels.error);}const html=await response.text();if(serial!==previewRequest)return;if(!html.includes('data-format='))throw new Error(labels.error);document.getElementById('invoiceDesignPreview').srcdoc=html;message.textContent='';}catch(e){message.textContent=e.message||labels.error;}finally{if(serial===previewRequest)button.disabled=false;}
 }
 document.getElementById('previewDesign').addEventListener('click',preview);form.addEventListener('submit',sync);form.addEventListener('input',sync);form.addEventListener('change',sync);
 render();preview();
})();
const scopeSelector=document.querySelector('form[method=get] select[name=scope]');scopeSelector?.addEventListener('change',()=>{const url=new URL(location.href);url.searchParams.set('scope',scopeSelector.value);url.searchParams.delete('target_id');location.assign(url.href);});
