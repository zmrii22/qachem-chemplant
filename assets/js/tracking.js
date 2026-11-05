(() => {
  'use strict';
  const config=window.QACHEM_TRACKING,q=s=>document.querySelector(s);
  const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const date=v=>v?String(v).slice(0,10).split('-').reverse().join('/'):'—';
  let sources=[],record=null,dirty=false,printDirty=false,busy=false,refreshing=false,previewVersion=0,previewTimer;
  let specificationContext='',specificationVersion=0,pendingSpecification='',specificationLoading=false;
  async function request(url,data){
    const response=await fetch(url,data?{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':window.QACHEM_CSRF},body:JSON.stringify(data)}:{headers:{Accept:'application/json'}});
    const result=await response.json();if(!response.ok||!result.success)throw new Error(result.message||'Request failed.');return result;
  }
  function state(text,error=false,success=false){const field=q('#trackingFormState');field.textContent=text;field.classList.toggle('error',error);field.classList.toggle('success',success);}
  function source(){return sources.find(source=>source.key===q('#sourceKey').value);}
  function data(){return {id:record?.id||null,source_key:q('#sourceKey').value,batch_no:source()?.batch_no||q('#sourceSearch').value.trim(),manufacturing_date:q('#manufacturingDate').value,factory:q('#trackingFactory').value,coa_spec_profile_id:q('#trackingCoaSpec').value||pendingSpecification||'',po_no:q('#trackingPO').value,packaging_no:q('#packagingNo').value,signatory:q('#trackingSignatory').value,upload_date:q('#trackingUploadDate').value,remark:q('#trackingRemark').value};}
  function resetSpecifications(selected=''){
    ++specificationVersion;specificationContext='';specificationLoading=false;pendingSpecification=String(selected||'');
    q('#trackingCoaSpec').innerHTML='<option value="">Select specification</option>';
  }
  async function loadSpecifications(force=false){
    const selected=source();
    if(!selected){resetSpecifications();updateActions();return;}
    const key=selected.key;
    if(!force&&specificationContext===key)return;
    const wanted=q('#trackingCoaSpec').value||pendingSpecification,version=++specificationVersion;
    specificationContext=key;specificationLoading=true;updateActions();
    try{
      const result=await request('api/tracking.php?'+new URLSearchParams({action:'specifications',source_key:selected.key}));
      if(version!==specificationVersion)return;
      q('#trackingCoaSpec').innerHTML='<option value="">Select specification</option>'+result.specifications.map(spec=>`<option value="${spec.id}">${esc(spec.label)}</option>`).join('');
      if(wanted&&!result.specifications.some(spec=>String(spec.id)===wanted))q('#trackingCoaSpec').insertAdjacentHTML('beforeend',`<option value="${esc(wanted)}" disabled>Unavailable specification - choose another</option>`);
      q('#trackingCoaSpec').value=wanted||String(result.default_profile_id||'');pendingSpecification='';
    }catch(error){if(version===specificationVersion)specificationContext='';throw error;}
    finally{if(version===specificationVersion){specificationLoading=false;updateActions();}}
  }
  function renderCoaRemarks(remarks=[],linked=false){
    q('#trackingCoaRemarks').innerHTML=remarks.length?`<ol>${remarks.map(remark=>`<li><span class="tracking-remark-id">${remark.id?'R'+String(remark.id).padStart(3,'0'):''}</span><span>${esc(remark.text)}</span></li>`).join('')}</ol>`:`<p>${linked?'No COA remarks configured for this product.':'Link an Inprocess run to load product remarks.'}</p>`;
  }
  function lock(value){busy=value;q('#newTracking').disabled=value||!config.canPlan;q('#trackingForm').querySelectorAll('button,input,select').forEach(field=>field.disabled=value||(!config.canPlan&&field.matches('input,select')));updateActions();}
  function updateActions(){
    q('#saveTracking').disabled=busy||specificationLoading||!config.canPlan;
    q('#generateTracking').disabled=busy||specificationLoading||!config.canEdit;
    q('#trackingCoaSpec').disabled=busy||specificationLoading||!config.canPlan||!source();
    q('#trackingFactory').required=!!source();q('#trackingPO').required=!!source();
    q('#trackingUploadDate').disabled=busy||!config.canEdit||!record?.coa_generated_at||printDirty;
  }
  const uploadLabel=row=>!row.coa_generated_at?'Generate COA first':!row.upload_date?'Not sent':row.upload_date>config.today?'Scheduled':'Sent';
  const modified=row=>`<strong>${esc(row.modified_by_name||row.created_by_name||'—')}</strong><small>${date(row.updated_at)} ${esc(row.updated_at?.slice(11,19)||'')}</small>`;
  function uploadCell(row){
    if(!config.canEdit)return `<span>${date(row.upload_date)}</span><small>${uploadLabel(row)}</small>`;
    return `<div class="tracking-upload ${row.upload_date?'is-sent':'is-unsent'}" data-upload-row="${row.id}"><div class="tracking-upload-controls"><input type="date" aria-label="Upload Date for ${esc(row.batch_no||row.tracking_no)}" data-upload-date="${row.id}" data-saved-date="${esc(row.upload_date||'')}" min="${esc(config.today)}" value="${esc(row.upload_date||'')}" ${row.coa_generated_at?'':'disabled'}><button type="button" class="btn small tracking-upload-save" data-save-upload="${row.id}" data-updated-at="${esc(row.updated_at)}" title="Save Upload Date" aria-label="Save Upload Date" disabled><svg viewBox="0 0 20 20" aria-hidden="true"><path d="M4 3h10l3 3v11H3V3h1Zm2 0v5h7V3M6 17v-6h8v6"/></svg></button></div><small class="tracking-upload-feedback" role="status" aria-live="polite">${uploadLabel(row)}</small></div>`;
  }
  async function list(){
    q('#trackingState').textContent='Loading…';
    try{
      const params=new URLSearchParams(new FormData(q('#trackingFilters'))),result=await request('api/tracking.php?'+params);
      q('#trackingRows').innerHTML=result.rows.map(row=>`<tr><td><button class="btn small tracking-edit" data-edit-tracking="${row.id}" aria-label="Open tracking for ${esc(row.batch_no||row.tracking_no)}" title="Open tracking"><svg viewBox="0 0 20 20" aria-hidden="true"><path d="m12.5 3.5 4 4M3 17l4.5-1 10-10a1.4 1.4 0 0 0 0-2l-1-1a1.4 1.4 0 0 0-2 0l-10 10L3 17Z"/></svg></button></td><td>${date(row.manufacturing_date)}</td><td>${esc(row.chemical_name||'Awaiting Inprocess')}<small>${esc(row.plant||'Planned')}</small></td><td>${esc(row.batch_no)}</td><td>${esc(row.packaging_no||row.source_tank)}</td><td>${esc(row.factory)}</td><td>${esc(row.po_no)}</td><td>${esc(row.remark)}</td><td>${date(row.testing_date)}</td><td>${row.coa_generated_at?`<a class="badge pass tracking-coa-link" href="COAForm.php?tracking_id=${row.id}" target="_blank" rel="noopener" aria-label="Open COA for ${esc(row.batch_no)}" title="Open COA">GENERATED</a><small class="tracking-generated-at">${date(row.coa_generated_at)} ${esc(row.coa_generated_at.slice(11,19))}</small>`:`<span class="badge pending">${row.planned?'DRAFT':'NOT GENERATED'}</span>`}</td><td>${uploadCell(row)}</td><td data-last-modified="${row.id}">${modified(row)}</td></tr>`).join('')||'<tr><td colspan="12" class="empty">No records.</td></tr>';
      q('#trackingState').textContent=result.rows.length+' tracking record(s).';
    }catch(error){q('#trackingState').textContent=error.message;}
  }
  function renderSourceOptions(selectedKey=''){
    const query=q('#sourceSearch').value.trim().toLowerCase();
    const matches=sources.filter(s=>s.batch_no.toLowerCase().includes(query));
    q('#sourceKey').innerHTML='<option value="">Link later</option>'+matches.map(s=>`<option value="${esc(s.key)}">${esc(s.batch_no+' · '+s.product_name+' · '+s.plant+' · '+s.tank+' · '+date(s.inspection_at)+' '+(s.inspection_at.slice(11,13)==='20'?'Night':'Day')+(s.run_label?' · '+s.run_label+(s.saved_at?' '+s.saved_at.slice(11):''):'')+' · '+s.author)}</option>`).join('');
    if(matches.some(s=>s.key===selectedKey))q('#sourceKey').value=selectedKey;
    q('#sourceSearchState').textContent=query?(matches.length?`${matches.length} run(s). Select one for COA.`:'No run yet. Save as draft.'):'Enter a batch.';
  }
  async function loadSources(){
    const result=await request('api/tracking.php?action=sources');sources=result.sources;renderSourceOptions(q('#sourceKey').value);
  }
  function expiryDate(selected,manufacturing){
    const value=Number(selected?.shelf_life_value);
    if(!selected||!manufacturing||!value)return '';
    const [year,month,day]=manufacturing.split('-').map(Number);
    const unit=String(selected.shelf_life_unit||'MONTH').toUpperCase();
    let target;
    if(['MONTH','MONTHS','YEAR','YEARS'].includes(unit)){
      const months=Math.round(value*(unit.startsWith('YEAR')?12:1));
      const lastDay=new Date(Date.UTC(year,month+months,0));
      target=new Date(Date.UTC(lastDay.getUTCFullYear(),lastDay.getUTCMonth(),Math.min(day,lastDay.getUTCDate())));
    }else target=new Date(Date.UTC(year,month-1,day+Math.round(value*(unit.startsWith('WEEK')?7:1))));
    return target.toISOString().slice(0,10);
  }
  function syncAutomaticFields(){
    const selected=source();
    q('#trackingChemical').value=selected?.product_name||'';
    q('#trackingBatch').value=selected?.batch_no||q('#sourceSearch').value.trim();
    q('#trackingSourceContext').value=selected?selected.plant+' / '+selected.tank:'';
    q('#testingDate').value=selected?.inspection_at.slice(0,10)||'';
    q('#trackingExpiry').value=expiryDate(selected,q('#manufacturingDate').value);
    const factory=config.reference.factories.find(f=>f.code===q('#trackingFactory').value);
    q('#trackingCustomer').value=factory?.name||'';
    renderCoaRemarks(selected?.coa_remarks||[],!!selected);
  }
  function sourceChanged(){
    const selected=source();
    q('#sourceInfo').textContent=selected?selected.product_name+' · '+selected.plant+' · '+selected.tank+' · '+selected.inspection_at+(selected.run_label?' · '+selected.run_label+(selected.saved_at?' '+selected.saved_at.slice(11):''):'')+' · '+selected.author:'';
    syncAutomaticFields();
  }
  function updateLiveSource(selected){
    const index=sources.findIndex(item=>item.key===selected.key);
    if(index<0)sources.push(selected);else sources[index]=selected;
    q('#sourceSearch').value=selected.batch_no;
    renderSourceOptions(selected.key);sourceChanged();
  }
  async function preview(forceSpecifications=false){
    const version=++previewVersion;
    syncAutomaticFields();
    const factory=config.reference.factories.find(f=>f.code===q('#trackingFactory').value);
    if(!source()){resetSpecifications();updateActions();q('#trackingPreview').innerHTML='';q('#trackingPreviewState').textContent='Link an Inprocess run for COA.';return;}
    q('#trackingPreviewState').textContent='Loading…';
    try{
      await loadSpecifications(forceSpecifications);if(version!==previewVersion)return;
      if(!factory||!q('#manufacturingDate').value){q('#trackingPreview').innerHTML='';q('#trackingPreviewState').textContent='Select date and factory.';return;}
      if(!q('#trackingCoaSpec').value){q('#trackingPreview').innerHTML='';q('#trackingPreviewState').textContent=q('#trackingCoaSpec').options.length>1?'Select a specification.':'No specification is configured for this product.';return;}
      const result=await request('api/tracking.php',{action:'preview',...data()});if(version!==previewVersion)return;
      if(result.source)updateLiveSource({...result.source,coa_remarks:result.document.coa_remarks||result.source.coa_remarks});
      const doc=result.document;
      q('#trackingExpiry').value=doc.expiry_date||'';
      q('#testingDate').value=doc.testing_date||'';
      q('#trackingPreviewState').textContent=doc.problems.length?'Complete the required fields.':'Ready.';
      q('#trackingPreview').innerHTML=`<div class="tracking-source-context">${esc(doc.batch)} · ${esc(doc.source_tank)}<br>${esc(doc.source_at)}</div>${doc.coa_spec_label?`<p class="tracking-spec-used">Specification: ${esc(doc.coa_spec_label)}</p>`:''}<table class="tracking-preview-table"><thead><tr><th>Test</th><th>Result</th><th>Specification</th></tr></thead><tbody>${doc.results.map(r=>`<tr><td>${esc(r.name)}</td><td class="${r.disposition==='FAIL'?'error':''}">${esc(r.result)} ${esc(r.unit)}</td><td>${esc(r.specification)}</td></tr>`).join('')}</tbody></table>${doc.problems.length?`<ul class="tracking-problems">${doc.problems.map(p=>`<li>${esc(p)}</li>`).join('')}</ul>`:''}`;
    }catch(error){if(version===previewVersion)q('#trackingPreviewState').textContent=error.message;}
  }
  async function refreshLive(){
    if(busy||refreshing||document.hidden||!q('#trackingDialog').open||!source())return;
    const key=q('#sourceKey').value,version=previewVersion;refreshing=true;
    try{
      const result=await request('api/tracking.php?action=source&key='+encodeURIComponent(key));
      if(busy||version!==previewVersion||key!==q('#sourceKey').value||!q('#trackingDialog').open)return;
      updateLiveSource(result.source);await preview(true);
    }catch(error){
      if(version===previewVersion&&key===q('#sourceKey').value){
        q('#trackingPreview').innerHTML='';q('#trackingPreviewState').textContent='Live source unavailable: '+error.message;
      }
    }finally{refreshing=false;}
  }
  function restoreFactory(value=''){
    const select=q('#trackingFactory');select.querySelectorAll('[data-existing-factory]').forEach(option=>option.remove());
    const factory=config.reference.factories.find(factory=>factory.code===value);
    if(factory&&![...select.options].some(option=>option.value===value)){
      const option=new Option(factory.code+' — '+factory.name,factory.code);
      option.hidden=true;option.disabled=true;option.dataset.existingFactory='';select.add(option);
    }
    select.value=value;
  }
  async function open(id=null){
    if(busy||(!id&&!config.canPlan))return;
    q('#trackingForm').reset();restoreFactory();resetSpecifications();record=null;dirty=false;printDirty=false;++previewVersion;clearTimeout(previewTimer);q('#trackingPreview').innerHTML='';renderCoaRemarks();
    q('#trackingNumber').textContent='';q('#trackingFormTitle').textContent=id?'Tracking':'New Tracking';q('#manufacturingDate').value=config.today;
    q('#sourceInfo').textContent='';q('#sourceSearchState').textContent='Loading saved batches…';q('#trackingInspector').value=config.user.name;state('');q('#trackingDialog').showModal();lock(true);
    try{
      await loadSources();
      if(id){
        const result=await request('api/tracking.php?id='+id);record=result.record;const d=record.details;resetSpecifications(d.coa_spec_profile_id||'');
        if(!record.source_missing&&!sources.some(s=>s.key===d.source_key)){
          sources.push({key:d.source_key,product_id:record.product_id,product_name:record.chemical_name,plant:record.plant,tank:record.source_tank,batch_no:record.batch_no,inspection_at:record.source_inspection_at,author:record.created_by_name,run_label:record.run_label,saved_at:record.source_saved_at,coa_remarks:record.coa_remarks||result.document?.coa_remarks||[],shelf_life_value:result.document?.shelf_life_value,shelf_life_unit:result.document?.shelf_life_unit});
        }
        q('#sourceSearch').value=record.batch_no||'';renderSourceOptions(d.source_key);
        restoreFactory(record.factory||'');
        const fields={sourceKey:d.source_key,manufacturingDate:record.manufacturing_date,trackingPO:record.po_no,packagingNo:record.packaging_no,trackingInspector:d.inspector||config.user.name,trackingSignatory:d.signatory,trackingUploadDate:record.upload_date};
        Object.entries(fields).forEach(([id,value])=>q('#'+id).value=value||'');
        // Older records already combine the former category and details in remark.
        q('#trackingRemark').value=record.remark??[d.tracking_remark,d.custom_remark].filter(Boolean).join(' — ');
        q('#trackingNumber').textContent=record.tracking_no;
      }
      sourceChanged();await preview();dirty=false;
      if(record?.planned)state('Draft. Link a run for COA.');
      else if(record?.source_missing)state('Inprocess run unavailable.',true);
    }catch(error){state(error.message,true);}finally{lock(false);q('.tracking-dialog-body').scrollTop=0;}
  }
  async function action(action){
    if(busy||!config.canPlan||(action==='generate'&&!config.canEdit))return;
    const required={sourceSearch:'Batch',manufacturingDate:'Manufacturing Date',};
    if(source())Object.assign(required,{trackingFactory:'Factory',trackingPO:'Customer PO No.'});
    if(action==='generate')Object.assign(required,{trackingFactory:'Factory',trackingPO:'Customer PO No.',sourceKey:'Inprocess run',trackingCoaSpec:'Specification',packagingNo:'Tote Tank / Drum No.',trackingSignatory:'COA signatory',trackingInspector:'Inspected by'});
    const missing=Object.entries(required).filter(([id])=>!q('#'+id).value.trim());
    if(missing.length){state('Fill: '+missing.map(([,name])=>name).join(', ')+'.',true);q('#'+missing[0][0]).focus();return;}
    const upload=q('#trackingUploadDate'),historical=upload.value===record?.upload_date&&upload.value<config.today;
    if(historical)upload.disabled=true;
    const valid=q('#trackingForm').reportValidity();updateActions();
    if(!valid){state('Check the highlighted fields.',true);return;}
    lock(true);state(action==='save'?'Saving…':'Generating…');
    let printWindow=null;
    if(action==='generate'){
      printWindow=window.open('about:blank','_blank');
      if(printWindow)printWindow.document.body.textContent='Preparing COA…';
    }
    try{
      const result=await request('api/tracking.php',{action,...data()});
      record=result.record;dirty=false;printDirty=false;q('#trackingNumber').textContent=record.tracking_no;q('#trackingUploadDate').value=record.upload_date||'';
      q('#trackingDialog').close();++previewVersion;clearTimeout(previewTimer);
      QACHEM_UI.notify(action==='generate'?'Tracking saved and COA generated.':record.planned?'Draft saved.':'Tracking saved.');
      if(action==='generate'){if(printWindow)printWindow.location.href=result.url;else location.href=result.url;}
      await list();
    }catch(error){printWindow?.close();state('Save failed. '+error.message+' Please retry.',true);}finally{lock(false);}
  }
  function close(){if(!busy){q('#trackingDialog').close();++previewVersion;clearTimeout(previewTimer);}}
  q('#exportTracking').addEventListener('click',async()=>{
    const button=q('#exportTracking');button.disabled=true;button.textContent='Exporting…';
    q('#trackingState').textContent='Exporting…';
    try{
      const response=await fetch('api/tracking.php?action=export',{headers:{Accept:'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'}});
      if(!response.ok||!response.headers.get('Content-Type')?.includes('spreadsheetml.sheet')){
        const result=await response.json().catch(()=>null);throw new Error(result?.message||'Unable to export Excel. Please sign in again if your session expired.');
      }
      const file=await response.blob(),url=URL.createObjectURL(file),link=document.createElement('a');
      link.href=url;link.download=response.headers.get('Content-Disposition')?.match(/filename="([^"]+)"/)?.[1]||'Tracking_Record_QA.xlsx';
      document.body.append(link);link.click();link.remove();setTimeout(()=>URL.revokeObjectURL(url),10000);
      q('#trackingState').textContent='Excel downloaded.';
    }catch(error){q('#trackingState').textContent=error.message;}
    finally{button.disabled=false;button.textContent='Export Excel';}
  });
  q('#newTracking').addEventListener('click',()=>open());q('#closeTracking').addEventListener('click',close);
  q('#trackingFilters').addEventListener('submit',event=>{event.preventDefault();list();});
  q('#trackingRows').addEventListener('input',event=>{
    const input=event.target.closest('[data-upload-date]');if(!input||!config.canEdit)return;
    const cell=input.closest('[data-upload-row]'),button=cell.querySelector('[data-save-upload]');
    const valid=!input.value||input.value>=config.today;
    button.disabled=!valid||input.value===input.dataset.savedDate;
    cell.querySelector('[role=status]').textContent=!valid?'Choose today or a later date.':button.disabled?(input.value?'Saved':'Not sent yet'):'Unsaved date';
  });
  q('#trackingRows').addEventListener('click',async event=>{
    const edit=event.target.closest('[data-edit-tracking]');if(edit){open(Number(edit.dataset.editTracking));return;}
    const button=event.target.closest('[data-save-upload]');if(!button||button.disabled||!config.canEdit)return;
    const cell=button.closest('[data-upload-row]'),input=cell.querySelector('[data-upload-date]'),feedback=cell.querySelector('[role=status]'),icon=button.innerHTML;
    if(input.value&&!input.reportValidity())return;
    const id=Number(button.dataset.saveUpload);button.disabled=true;input.disabled=true;feedback.textContent='Saving…';button.classList.remove('save-error');
    try{
      const result=await request('api/tracking.php',{action:'upload_date',id,updated_at:button.dataset.updatedAt,upload_date:input.value}),row=result.record;
      input.dataset.savedDate=row.upload_date||'';input.value=row.upload_date||'';
      cell.classList.toggle('is-sent',!!row.upload_date);cell.classList.toggle('is-unsent',!row.upload_date);
      feedback.textContent='Saved · '+uploadLabel(row);button.innerHTML='✓';button.classList.add('save-success');button.dataset.updatedAt=row.updated_at;
      q(`[data-last-modified="${id}"]`).innerHTML=modified(row);
      setTimeout(()=>{if(button.isConnected){button.innerHTML=icon;button.classList.remove('save-success');}},2000);
    }catch(error){feedback.textContent=error.message;button.innerHTML='✕';button.classList.add('save-error');button.disabled=false;}
    finally{input.disabled=false;}
  });
  q('#trackingForm').addEventListener('submit',event=>event.preventDefault());
  q('#trackingForm').addEventListener('input',event=>{
    if(busy||!config.canPlan||event.target.readOnly)return;
    ++previewVersion;
    if(['sourceSearch','sourceKey'].includes(event.target.id))resetSpecifications();
    if(event.target.id==='sourceSearch'){renderSourceOptions();sourceChanged();}
    if(event.target.id!=='trackingUploadDate'){printDirty=true;q('#trackingUploadDate').value='';}
    syncAutomaticFields();dirty=true;updateActions();state('Unsaved changes');clearTimeout(previewTimer);previewTimer=setTimeout(preview,300);
  });
  q('#trackingForm').addEventListener('change',event=>{
    if(busy||!config.canPlan||event.target.id==='sourceSearch'||event.target.readOnly)return;
    if(event.target.id==='sourceKey')resetSpecifications();
    if(event.target.id==='sourceKey'){
      const selected=source();
      if(selected){q('#sourceSearch').value=selected.batch_no;renderSourceOptions(selected.key);}
      sourceChanged();
    }
    if(event.target.id!=='trackingUploadDate'){printDirty=true;q('#trackingUploadDate').value='';}
    syncAutomaticFields();dirty=true;updateActions();clearTimeout(previewTimer);preview();
  });
  q('#saveTracking').addEventListener('click',()=>action('save'));q('#generateTracking').addEventListener('click',()=>action('generate'));
  q('#trackingDialog').addEventListener('cancel',event=>{if(busy)event.preventDefault();});
  window.addEventListener('focus',()=>{if(q('#trackingDialog').open)refreshLive();else if(!busy)list();});
  document.addEventListener('visibilitychange',()=>{if(!document.hidden)refreshLive();});
  setInterval(refreshLive,15000);
  list().then(async()=>{
    if(config.id)await open(config.id);
    else if(config.inspectionId){
      const result=await request('api/inspection.php?id='+config.inspectionId);await open();
      const i=result.inspection,s=sources.find(s=>Number(s.product_id)===Number(i.entity_id)&&s.batch_no===i.batch_no&&s.inspection_at===i.inspection_at&&s.tank===i.sampling_location);
      if(s){q('#sourceSearch').value=s.batch_no;renderSourceOptions(s.key);sourceChanged();preview();}
    }
  }).catch(error=>{q('#trackingState').textContent=error.message;});
})();
