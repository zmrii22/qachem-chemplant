(() => {
 'use strict';
 const config=window.QACHEM_REFERENCE,q=s=>document.querySelector(s),esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 let metadata=null,record=null,busy=false;
 const feedbackTimers=new WeakMap();
 function resetFeedback(el){
  (feedbackTimers.get(el)||[]).forEach(clearTimeout);feedbackTimers.delete(el);
  el.classList.remove('reference-notice-enter','reference-notice-exit');
 }
 function briefFeedback(el,hide){
  resetFeedback(el);void el.offsetWidth;el.classList.add('reference-notice-enter');
  const timers=[];feedbackTimers.set(el,timers);
  timers.push(setTimeout(()=>{
   el.classList.remove('reference-notice-enter');el.classList.add('reference-notice-exit');
   timers.push(setTimeout(()=>{hide();resetFeedback(el);},200));
  },2800));
 }
 const rid=id=>'R'+String(id).padStart(3,'0');
 async function request(data=null,id=null){const response=await fetch('api/reference.php'+(id?'?id='+id:''),data?{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':window.QACHEM_CSRF},body:JSON.stringify(data)}:{});const result=await response.json();if(!response.ok||!result.success)throw Error(result.message||'Request failed.');return result;}
 function state(id,text,error=false){const el=q('#'+id);if(id==='productState')resetFeedback(el);el.textContent=text;el.classList.toggle('reference-error',error);el.classList.toggle('reference-success',!!text&&!error);if(id==='productState'&&error)briefFeedback(el,()=>{el.textContent='';el.classList.remove('reference-error','reference-success');});}
 function lock(form,value){busy=value;form.querySelectorAll('button').forEach(el=>el.disabled=value);}
 document.querySelectorAll('[data-close]').forEach(button=>button.addEventListener('click',()=>{if(!busy)q('#'+button.dataset.close).close();}));
 document.querySelectorAll('.reference-dialog').forEach(dialog=>dialog.addEventListener('cancel',event=>{if(busy)event.preventDefault();}));
 async function loadRemarks(){metadata=await request();if(q('#newRemark'))q('#newRemark').disabled=false;q('#remarkRows').innerHTML=metadata.remarks.map(r=>`<tr><td><strong>${rid(r.id)}</strong></td><td>${esc(r.label)}</td><td class="reference-row-text">${esc(r.text)}</td><td>${r.compulsory?'Yes':'No'}</td><td><button class="btn small" data-remark="${r.id}">${config.canEdit?'Edit':'View'}</button></td></tr>`).join('')||'<tr><td colspan="5" class="empty">No remarks.</td></tr>';}
 function openRemark(id=null){record=metadata.remarks.find(r=>r.id===id)||null;q('#remarkForm').reset();q('#remarkTitle').textContent=record?rid(record.id)+' · COA Remark':'New COA Remark';q('#remarkLabel').value=record?.label||'';q('#remarkText').value=record?.text||'';q('#remarkCompulsory').checked=!!record?.compulsory;q('#remarkForm').querySelectorAll('input,textarea').forEach(el=>el.disabled=!config.canEdit);if(q('#deleteRemark'))q('#deleteRemark').hidden=!record;state('remarkState','');q('#remarkDialog').showModal();}
 if(config.mode==='remarks'){
  if(q('#newRemark'))q('#newRemark').disabled=true;
  q('#newRemark')?.addEventListener('click',()=>openRemark());q('#remarkRows').addEventListener('click',e=>{const button=e.target.closest('[data-remark]');if(button)openRemark(Number(button.dataset.remark));});
  q('#remarkForm').addEventListener('submit',async e=>{e.preventDefault();if(!config.canEdit||busy)return;lock(e.target,true);state('remarkState','Saving…');try{await request({action:'remark_save',id:record?.id,label:q('#remarkLabel').value,text:q('#remarkText').value,compulsory:q('#remarkCompulsory').checked});q('#remarkDialog').close();await loadRemarks();state('referenceState','Remark saved.');QACHEM_UI.notify('COA remark saved.');}catch(error){state('remarkState','Save failed. '+error.message+' Please retry.',true);}finally{lock(e.target,false);}});
  q('#deleteRemark')?.addEventListener('click',async()=>{if(!record||busy||!confirm('Delete '+rid(record.id)+'? Existing saved data is retained.'))return;lock(q('#remarkForm'),true);try{await request({action:'remark_delete',id:record.id});q('#remarkDialog').close();await loadRemarks();state('referenceState','Remark deleted.');}catch(error){state('remarkState','Save failed. '+error.message+' Please retry.',true);}finally{lock(q('#remarkForm'),false);}});
  loadRemarks().catch(e=>state('referenceState',e.message,true));return;
 }
 let productId=0;
 function productComplete(action,id){
  const url=new URL(location.href);url.searchParams.set('spec_notice',action);url.searchParams.set('spec_notice_id',String(id));location.assign(url.href);
 }
 const noticeUrl=new URL(location.href),notice=noticeUrl.searchParams.get('spec_notice'),noticeId=noticeUrl.searchParams.get('spec_notice_id');
 if(['added','updated','deleted'].includes(notice)&&/^\d+$/.test(noticeId||'')){
  q('#productFeedbackText').textContent='Specification #'+noticeId+' '+notice+' successfully.';q('#productFeedback').hidden=false;
  briefFeedback(q('#productFeedback'),()=>q('#productFeedback').hidden=true);
 }
 if(noticeUrl.searchParams.has('spec_notice')||noticeUrl.searchParams.has('spec_notice_id')){
  noticeUrl.searchParams.delete('spec_notice');noticeUrl.searchParams.delete('spec_notice_id');history.replaceState(history.state,'',noticeUrl.href);
 }
 q('#dismissProductFeedback')?.addEventListener('click',()=>{resetFeedback(q('#productFeedback'));q('#productFeedback').hidden=true;});
 function productFields(p){productId=Number(p?.id||0);const fields={refProductType:p?.product_type,refProductName:p?.product_name,refBatch:p?.batch_code,refShelf:p?.shelf_life_value,refShelfUnit:p?.shelf_life_unit||'MONTH',refAppearance:p?.appearance};for(const [id,value] of Object.entries(fields))q('#'+id).value=value??'';}
 function remarkPreview(){const selected=new Set([...q('#productRemarkChoices').querySelectorAll('input:checked')].map(i=>Number(i.value)));q('#productRemarkPreview').innerHTML=metadata.remarks.filter(r=>selected.has(r.id)).map(r=>`<div class="reference-remark-preview"><strong>${rid(r.id)} · ${esc(r.label)}</strong>${esc(r.text)}</div>`).join('')||'<p class="muted">No remarks selected.</p>';}
 function limits(){q('#referenceParameters').querySelectorAll('tr').forEach(row=>{const enabled=row.querySelector('[data-use]').checked;row.querySelectorAll('input:not([data-use]),select').forEach(el=>el.disabled=!config.canEdit||!enabled);});}
 async function openProduct(id=null){q('#productForm').reset();record=null;productId=0;state('productState','Loading…');q('#productDialog').showModal();lock(q('#productForm'),true);try{
  metadata=await request();record=id?(await request(null,id)).record:null;
  q('#productTitle').textContent=record?'Specification #'+record.id:'New Product Specification';productFields(record?.product);
  q('#existingProductLabel').hidden=!!record;q('#existingProduct').innerHTML='<option value="0">New product</option>'+metadata.products.map(p=>`<option value="${p.id}">${esc(p.product_name+' · '+p.batch_code)}</option>`).join('');
  q('#refScope').value=record?.factory_scope||'ALL';q('#refSpecType').value=record?.spec_type||'IQS';
  const rules=new Map((record?.rules||[]).map(r=>[r.parameter_code,r]));
  q('#referenceParameters').innerHTML=Object.entries(metadata.parameters).map(([code,d])=>{const r=rules.get(code),use=!!r,methods='<option value="0">None</option>'+metadata.methods.map(m=>`<option value="${m.method_key}" ${Number(r?.method_key??d[2])===Number(m.method_key)?'selected':''}>${esc(m.method_key+' · '+m.method_name)}</option>`).join('');return `<tr data-code="${code}" data-target="${esc(r?.target_text||'')}"><td><input type="checkbox" data-use ${use?'checked':''} aria-label="Enable ${esc(d[0])}"></td><td>${esc(d[0])}</td><td><select data-operator>${['RANGE','MIN','MAX','GT','LT','EQ','OBSERVE','INFO'].map(op=>`<option ${op===(r?.operator||'RANGE')||op==='MIN'&&r?.operator==='GTE'||op==='MAX'&&r?.operator==='LTE'?'selected':''}>${op}</option>`).join('')}</select></td><td><input type="number" step="any" data-low value="${esc(r?.low_value??'')}" aria-label="${code} lower limit"></td><td><input type="number" step="any" data-high value="${esc(r?.high_value??'')}" aria-label="${code} upper limit"></td><td><select data-method>${methods}</select></td><td><input type="number" data-samples min="1" max="3" value="${r?.sample_count||d[3]}" aria-label="${code} samples"></td></tr>`;}).join('');
  const selected=new Set(record?.remark_ids||[]);q('#productRemarkChoices').innerHTML=metadata.remarks.map(r=>`<label class="reference-remark-choice"><input type="checkbox" value="${r.id}" ${selected.has(r.id)||r.compulsory?'checked':''} ${r.compulsory?'data-compulsory="true"':''}><span><strong>${rid(r.id)} · ${esc(r.label)}</strong>${r.compulsory?'<small>Always included</small>':''}</span></label>`).join('');
  q('#productForm').querySelectorAll('input,select,textarea').forEach(el=>el.disabled=!config.canEdit||el.dataset.compulsory==='true');limits();remarkPreview();if(q('#deleteProductSpec'))q('#deleteProductSpec').hidden=!record;state('productState','');
 }catch(error){state('productState',error.message,true);}finally{lock(q('#productForm'),false);}}
 document.querySelectorAll('[data-open-spec]').forEach(button=>button.addEventListener('click',()=>openProduct(Number(button.dataset.openSpec))));q('#newProductSpec')?.addEventListener('click',()=>openProduct());
 q('#existingProduct').addEventListener('change',()=>{const product=metadata.products.find(p=>Number(p.id)===Number(q('#existingProduct').value));productFields(product);const selected=new Set(product?.remark_ids||[]);q('#productRemarkChoices').querySelectorAll('input').forEach(input=>input.checked=input.dataset.compulsory==='true'||selected.has(Number(input.value)));remarkPreview();});q('#referenceParameters').addEventListener('change',limits);q('#productRemarkChoices').addEventListener('change',remarkPreview);
 q('#productForm').addEventListener('submit',async e=>{
  e.preventDefault();if(!config.canEdit||busy)return;
  if(record&&!confirm('Save changes to specification #'+record.id+' for '+record.product.product_name+'?'))return;
  const parameters={};q('#referenceParameters').querySelectorAll('tr').forEach(row=>parameters[row.dataset.code]={enabled:row.querySelector('[data-use]').checked,operator:row.querySelector('[data-operator]').value,low_value:row.querySelector('[data-low]').value,high_value:row.querySelector('[data-high]').value,method_key:row.querySelector('[data-method]').value,sample_count:row.querySelector('[data-samples]').value,target_text:row.dataset.target});
  const data={action:'spec_save',id:record?.id,product:{id:productId,product_type:q('#refProductType').value,product_name:q('#refProductName').value,batch_code:q('#refBatch').value,shelf_life_value:q('#refShelf').value,shelf_life_unit:q('#refShelfUnit').value,appearance:q('#refAppearance').value},spec_type:q('#refSpecType').value,factory_scope:q('#refScope').value,parameters,remark_ids:[...q('#productRemarkChoices').querySelectorAll('input:checked')].map(el=>Number(el.value))};
  lock(e.target,true);state('productState','Saving…');
  try{const result=await request(data);productComplete(record?'updated':'added',result.id);}
  catch(error){state('productState','Save failed. '+error.message+' Please retry.',true);lock(e.target,false);}
 });
 q('#deleteProductSpec')?.addEventListener('click',async()=>{
  if(!record||busy||!confirm('Delete specification #'+record.id+' for '+record.product.product_name+'? Saved inspections are retained.'))return;
  lock(q('#productForm'),true);state('productState','Deleting…');
  try{await request({action:'spec_delete',id:record.id});productComplete('deleted',record.id);}
  catch(error){state('productState','Delete failed. '+error.message,true);lock(q('#productForm'),false);}
 });
})();
