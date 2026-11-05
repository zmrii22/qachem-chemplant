/* Supplier-specific Incoming forms and their live reference panel. */
(() => {
  'use strict';
  const config=window.QACHEM_INCOMING;
  const q=selector=>document.querySelector(selector);
  const esc=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const number=QACHEM_UI.result;
  const statusClass=value=>String(value).toLowerCase().replace(/\s+/g,'-');
  const groups=new Map(),results=new Map();
  let material=null,profile=null,record=null,dirty=false,busy=false,version=0,calcRule=null;
  const templates=window.createIncomingTemplates({esc,user:config.user,verifiers:config.verifiers});
  const calcFields={
    1:{A:'Foil / dish weight (g)',B:'Wet sample weight (g)',C:'Dish + dry sample (g)'},
    12:{C:'Initial titrant (mL)',D:'Final titrant (mL)'},
    14:{A:'Petri dish weight (g)',B:'Sample weight (g)',C:'Dish + sample after heating (g)'},
    15:{A:'Empty plate weight (g)',B:'Sample weight (g)',C:'Plate + sample after sieve (g)'},
    16:{A:'Sample weight (g)',C:'Initial titrant (mL)',D:'Final titrant (mL)'},
    17:{A:'Sample weight (g)',C:'Initial titrant (mL)',D:'Final titrant (mL)'}
  };

  config.materials.forEach(item=>{
    const key=item.material_name.toLowerCase().trim();
    if(!groups.has(key))groups.set(key,{name:item.material_name,materials:[]});
    groups.get(key).materials.push(item);
  });
  function materialOptions(){
    const type=q('#incomingFormType').value;
    templates.mount(type);
    templates.render({rules:[]},results,type);
    q('#incomingReceived').value=config.today;q('#incomingInspected').value=config.now;
    q('#incomingMaterial').innerHTML='<option value="">Select material</option>'+[...groups].filter(([,group])=>group.materials.some(item=>item.material_type.toUpperCase()===type)).map(([key,group])=>`<option value="${esc(key)}">${esc(group.name)}</option>`).join('');
    q('#incomingMaterial').disabled=!type;
  }

  async function request(url,options={}) {
    const response=await fetch(url,options),text=await response.text();
    let data;
    try{data=JSON.parse(text);}catch(_){throw new Error('The server could not return form data. Check the server error log.');}
    if(!response.ok||!data.success)throw new Error(data.message||'Unable to complete the request.');
    return data;
  }
  const post=(url,data)=>request(url,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':window.QACHEM_CSRF},body:JSON.stringify(data)});
  function state(message,error=false){q('#incomingState').textContent=message;q('#incomingState').classList.toggle('error',error);}
  function editable(){return config.canEdit && record?.status!=='CANCELLED';}
  function markDirty(){if(editable()){dirty=true;state('Unsaved changes');}}
  function lockForm(){
    q('#incomingForm').querySelectorAll('input,select,textarea').forEach(field=>field.disabled=!editable()||busy);
    q('#incomingSupplier').disabled=!editable()||busy||!q('#incomingMaterial').value;
    q('#incomingMaterial').disabled=!editable()||busy||!q('#incomingFormType').value;
    q('#incomingFormType').disabled=!editable()||busy||!!record;
    q('#incomingForm').querySelectorAll('[data-optional-disabled="true"]').forEach(field=>field.disabled=true);
    q('#saveIncoming').hidden=!editable();
    q('#saveIncoming').disabled=busy||!profile;
    q('#closeIncoming').disabled=busy;
    q('#clearIncoming').disabled=busy||!editable();
    q('#incomingDocumentDetails').querySelectorAll('[data-calculator],[data-direct]').forEach(button=>button.disabled=busy||!editable());
  }
  function resetForm(){
    version++;q('#incomingForm').reset();q('#incomingFormType').value=config.formType;material=profile=record=null;results.clear();dirty=false;
    templates.reset();materialOptions();q('#incomingDocumentDetails').innerHTML='';
    q('#incomingSupplier').innerHTML='<option value="">Select supplier</option>';
    q('#incomingRecordNo').textContent='NEW RECORD';
    q('#incomingReferenceSelection').textContent='Select a chemical and supplier to see its reference.';
    q('#incomingSpecSource').textContent='';q('#materialDetails').innerHTML='<span>Material details appear after selecting a supplier.</span>';
    q('#overallPreview').textContent='PENDING';q('#overallPreview').className='badge pending';
    q('#incomingReceived').value=config.today;q('#incomingInspected').value=config.now;state('Ready');lockForm();
  }
  function suppliers(selectedId=''){
    const group=groups.get(q('#incomingMaterial').value);
    const applicable=(group?.materials||[]).filter(item=>item.material_type.toUpperCase()===q('#incomingFormType').value);
    q('#incomingSupplier').innerHTML='<option value="">Select supplier</option>'+applicable.map(item=>`<option value="${item.id}">${esc(item.supplier||'Not specified')}</option>`).join('');
    q('#incomingSupplier').disabled=!group;
    if(selectedId)q('#incomingSupplier').value=String(selectedId);
    else if(applicable.length===1)q('#incomingSupplier').value=String(applicable[0].id);
    updateSupplierLabel();
  }
  function updateSupplierLabel(){
    const supplier=q('#incomingSupplier'),label=q('.incoming-supplier-value');
    const text=supplier?.selectedOptions[0]?.textContent||'Select supplier';
    if(supplier)supplier.title=text;
    if(label)label.textContent=text;
  }
  async function loadSpec(profileId=null){
    updateSupplierLabel();
    const token=++version;profile=null;results.clear();
    templates.reset(q('#incomingFormType').value);q('#incomingDocumentDetails').innerHTML='';
    q('#incomingProposed').value='';
    q('#incomingPaper').querySelectorAll('[data-document-disposition]').forEach(input=>input.checked=false);
    updateResults();
    material=config.materials.find(item=>item.id===Number(q('#incomingSupplier').value))||null;
    lockForm();
    if(!material){q('#incomingSpecSource').textContent='';q('#materialDetails').innerHTML='<span>Select a supplier.</span>';q('#incomingReferenceSelection').textContent='Select a chemical and supplier to see its reference.';return;}
    q('#incomingReferenceSelection').textContent=material.material_name+' — '+material.supplier;
    q('#incomingDocumentDetails').innerHTML='<p class="incoming-reference-empty" role="status">Loading supplier specification…</p>';
    q('#incomingSpecSource').textContent='';
    const details=record?.snapshot||material;
    q('#materialDetails').innerHTML=[['Physical type',details.material_type],['Chemical type',details.chemical_type],['Shelf life',details.shelf_life_text]].map(([label,value])=>`<div>${label}<strong>${esc(value||'Not specified')}</strong></div>`).join('');
    try{
      const params={entity_type:'RAW_MATERIAL',entity_id:material.id,spec_type:'INCOMING',supplier:material.supplier};
      if(profileId)params.profile_id=profileId;
      const data=await request('api/spec.php?'+new URLSearchParams(params));
      if(token!==version)return;
      profile=data.profile;
      profile.rules.filter(rule=>rule.operator!=='INFO').forEach(rule=>results.set(String(rule.id),{samples:Array(Math.max(1,Number(rule.sample_count))).fill(''),text:'',raw:null}));
      q('#incomingSpecSource').textContent=`${profile.source_ref} · ${profile.source_row?'Sheet1 row '+profile.source_row+' · ':''}Revision ${profile.revision||'—'}`;
      renderTests();state('Specification loaded. Enter the observed results.');
    }catch(error){if(token!==version)return;q('#incomingDocumentDetails').innerHTML=`<p class="alert error">${esc(error.message)}</p>`;state(error.message,true);}
    lockForm();
  }
  function methodText(rule){
    const info=profile?.rules.find(item=>item.parameter_code===rule.parameter_code+'_METHOD');
    return info?.target_text||'';
  }
  function display(rule){return rule.source_display||rule.display||rule.target_text||'No controlled limit';}
  function referenceInput(rule){
    if(templates.isSection(rule))return '';
    const model=results.get(String(rule.id));let input;
    if(rule.data_type==='NUMERIC'){
      input=`<div class="incoming-samples">${model.samples.map((value,index)=>`<label>S${index+1}<input type="number" step="any" data-rule="${rule.id}" data-sample="${index}" value="${esc(value)}" aria-label="${esc(rule.parameter_name+' sample '+(index+1))}" ${model.raw?'readonly':''}></label>`).join('')}</div>`;
      if(calcFields[rule.method_key])input+=`<div class="actions"><button type="button" data-calculator="${rule.id}">Calculator</button>${model.raw?`<button type="button" data-direct="${rule.id}">Enter result directly</button>`:''}</div>`;
    }else{
      const other=model.text&&model.text!==rule.target_text;
      input=`<select data-observation="${rule.id}" aria-label="${esc(rule.parameter_name+' observation')}"><option value="">Select observation</option><option value="match" ${model.text===rule.target_text?'selected':''}>Matches reference</option><option value="other" ${other?'selected':''}>Different / describe</option></select><input class="incoming-observed-text" data-text="${rule.id}" placeholder="Describe the observation" value="${esc(other?model.text:'')}" ${other?'':'hidden'}>`;
    }
    return `<div class="incoming-reference-entry"><span>Enter reading</span>${input}</div>`;
  }
  function renderReference(){
    const rules=profile.rules.filter(rule=>rule.operator!=='INFO');
    const notes=profile.rules.filter(rule=>rule.operator==='INFO'&&!/_METHOD$/.test(rule.parameter_code));
    q('#incomingDocumentDetails').innerHTML=`<div class="incoming-reference-label">Supplier limits &amp; results</div><div class="incoming-reference-tests">${rules.map(rule=>`<article><div class="incoming-reference-test-head"><h4>${esc(rule.parameter_name)}</h4><span data-disposition="${rule.id}" class="badge pending">PENDING</span></div><p class="incoming-reference-limit">${esc(display(rule))}${rule.unit?` <small>${esc(rule.unit)}</small>`:''}</p>${methodText(rule)?`<p class="incoming-reference-method"><span>Method / preparation</span>${esc(methodText(rule))}</p>`:''}${referenceInput(rule)}<div class="incoming-reference-result"><span>${Number(rule.sample_count)>1?'Average result':'Observed result'}</span><span data-value="${rule.id}" class="incoming-result">—</span></div></article>`).join('')}</div>${notes.map(rule=>`<p class="incoming-reference-method"><span>${esc(rule.parameter_name)}</span>${esc(rule.target_text)}</p>`).join('')}`;
  }
  function modelValue(rule){
    const model=results.get(String(rule.id));
    if(rule.data_type!=='NUMERIC')return (model?.text||'').trim();
    const values=(model?.samples||[]).filter(value=>value!==''&&value!==null).map(Number).filter(Number.isFinite);
    return values.length?values.reduce((sum,value)=>sum+value,0)/values.length:null;
  }
  function evaluate(rule,value){
    if(value===null||value===''||value===undefined)return 'PENDING';
    if(rule.data_type!=='NUMERIC'){
      const normalize=text=>String(text).toLowerCase().replace(/[^a-z0-9]+/g,' ');
      const actual=normalize(value),expected=normalize(rule.target_text||'');
      return actual.includes(expected)||expected.includes(actual)?'PASS':'REVIEW';
    }
    const low=rule.low_value===null?null:Number(rule.low_value),high=rule.high_value===null?null:Number(rule.high_value);
    switch(rule.operator){
      case 'RANGE':return(low===null||value>=low)&&(high===null||value<=high)?'PASS':'FAIL';
      case 'MIN':case 'GTE':return value>=low?'PASS':'FAIL';case 'GT':return value>low?'PASS':'FAIL';
      case 'MAX':case 'LTE':return value<=high?'PASS':'FAIL';case 'LT':return value<high?'PASS':'FAIL';
      case 'EQ':return Math.abs(value-low)<0.000001?'PASS':'FAIL';case 'OBSERVE':return 'REVIEW';default:return 'PENDING';
    }
  }
  function renderTests(){
    templates.render(profile,results,q('#incomingFormType').value,record);renderReference();updateResults();lockForm();
  }
  function updateResults(authoritative=null){
    const dispositions=[];
    for(const rule of profile?.rules||[]){
      if(rule.operator==='INFO')continue;
      const server=authoritative?.find(item=>Number(item.spec_rule_id)===Number(rule.id));
      const value=server?(server.result_value??server.result_text):modelValue(rule),disp=server?.disposition||evaluate(rule,value);
      dispositions.push(disp);
      document.querySelectorAll(`[data-value="${rule.id}"]`).forEach(cell=>{cell.textContent=rule.data_type==='NUMERIC'?number(value):value?'Recorded':'—';cell.className='incoming-result '+statusClass(disp);});
      document.querySelectorAll(`[data-disposition="${rule.id}"]`).forEach(badge=>{badge.textContent=disp;badge.className='badge '+statusClass(disp);});
    }
    const overall=dispositions.includes('FAIL')?'FAIL':dispositions.some(d=>d==='REVIEW'||d==='PENDING')?'HOLD':dispositions.length?'PASS':'PENDING';
    q('#overallPreview').textContent=overall;q('#overallPreview').className='badge '+statusClass(overall);
  }
  async function openRecord(id){
    resetForm();state('Loading saved form…');busy=true;lockForm();
    try{
      const data=await request('api/inspection.php?id='+id);
      if(data.inspection.inspection_type!=='INCOMING')throw new Error('This record is not an Incoming form.');
      record=data.inspection;
      const stored=data.results.filter(item=>Number(item.attempt_no)===1);
      record.snapshot=stored.map(item=>{try{return JSON.parse(item.raw_input_json).incoming_snapshot;}catch(_){return null;}}).find(Boolean);
      const item=config.materials.find(m=>m.id===Number(record.entity_id));
      if(!item)throw new Error('The material for this saved form is unavailable.');
      q('#incomingFormType').value=item.material_type.toUpperCase();materialOptions();
      q('#incomingMaterial').value=item.material_name.toLowerCase().trim();suppliers(item.id);
      const fields={incomingBatch:'batch_no',incomingReceived:'received_date',incomingExpiry:'expiry_date',incomingPackaging:'packaging_condition',incomingProposed:'proposed_disposition'};
      Object.entries(fields).forEach(([field,key])=>q('#'+field).value=record[key]||'');
      q('#incomingInspected').value=record.inspection_at.replace(' ','T').slice(0,16);
      q('#incomingRecordNo').textContent=record.inspection_no;
      await loadSpec(record.spec_profile_id);
      if(!profile)throw new Error('Unable to load this record’s saved specification.');
      for(const result of stored){
        const model=results.get(String(result.spec_rule_id));if(!model)continue;
        let raw={};try{raw=JSON.parse(result.raw_input_json||'{}');}catch(_){}
        const rule=profile.rules.find(item=>String(item.id)===String(result.spec_rule_id));
        model.samples=Array.from({length:Math.max(1,Number(rule.sample_count))},(_,index)=>raw.samples?.[index]??'');model.text=result.result_text||'';model.raw=Array.isArray(raw.raw_samples)?raw.raw_samples:null;
      }
      const savedForm=stored.map(result=>{try{return JSON.parse(result.raw_input_json).incoming_form;}catch(_){return null;}}).find(Boolean);
      if(savedForm)templates.restore(savedForm);else templates.reset(q('#incomingFormType').value);
      renderTests();updateResults(stored);dirty=false;state(editable()?'Saved form loaded.':'Read only');
    }catch(error){state('Save failed. '+error.message+' Please retry.',true);}
    finally{busy=false;lockForm();}
  }
  function payload(action){
    let time=q('#incomingInspected').value.replace('T',' ');if(time.length===16)time+=':00';
    return {action,source:'INCOMING_FORM',incoming_form:templates.collect(),id:record?.id||null,inspection_type:'INCOMING',entity_type:'RAW_MATERIAL',entity_id:material.id,spec_type:'INCOMING',batch_no:q('#incomingBatch').value.trim(),received_date:q('#incomingReceived').value,manufacturing_date:record?.manufacturing_date||'',expiry_date:q('#incomingExpiry').value,inspection_at:time,packaging_condition:q('#incomingPackaging').value,proposed_disposition:q('#incomingProposed').value,remarks:record?.remarks||'',results:profile.rules.filter(rule=>rule.operator!=='INFO').map(rule=>{
      const model=results.get(String(rule.id));
      return {rule_id:rule.id,samples:model.samples,text:model.text,raw_samples:model.raw};
    })};
  }
  async function save(){
    if(busy||!profile||!material||!editable())return;
    if(!q('#incomingForm').reportValidity())return;
    const data=payload('save');busy=true;state('Saving form…');lockForm();
    try{
      await post('api/inspection.php',data);
      dirty=false;QACHEM_UI.flash('Incoming form saved.');location.assign('Incoming.php');
    }catch(error){state('Save failed. '+error.message+' Please retry.',true);}
    finally{busy=false;lockForm();}
  }
  function calcFormula(key){
    const fraction=(top,bottom)=>`<mfrac><mrow>${top}</mrow><mrow>${bottom}</mrow></mfrac>`;
    const v='<mo>(</mo><mi>D</mi><mo>−</mo><mi>C</mi><mo>)</mo>';
    const body={1:fraction('<mi>C</mi><mo>−</mo><mi>A</mi>','<mi>B</mi>')+'<mo>×</mo><mn>100</mn>',7:fraction('<msub><mi>W</mi><mn>3</mn></msub><mo>−</mo><msub><mi>W</mi><mn>2</mn></msub>','<msub><mi>W</mi><mn>1</mn></msub>')+'<mo>×</mo><mn>100</mn>',12:v+'<mo>×</mo><mn>25</mn><mo>×</mo><mn>0.0142</mn>',14:fraction('<mo>(</mo><mi>A</mi><mo>+</mo><mi>B</mi><mo>)</mo><mo>−</mo><mi>C</mi>','<mi>A</mi><mo>+</mo><mi>B</mi>')+'<mo>×</mo><mn>100</mn>',15:fraction('<mi>C</mi><mo>−</mo><mi>A</mi>','<mi>B</mi>')+'<mo>×</mo><mn>100</mn>',16:fraction(v+'<mo>×</mo><mn>3.72</mn>','<mn>2</mn><mo>×</mo><mi>A</mi>'),17:fraction(v+'<mo>×</mo><mn>4</mn>','<mi>A</mi>')}[key];
    return `<math xmlns="http://www.w3.org/1998/Math/MathML"><mrow><mtext>Result</mtext><mo>=</mo>${body||''}</mrow></math>`;
  }
  function openCalculator(id){
    calcRule=profile.rules.find(rule=>String(rule.id)===String(id));if(!calcRule||!editable())return;
    const model=results.get(String(id)),fields=calcFields[calcRule.method_key];
    q('#incomingCalcTitle').textContent=calcRule.parameter_name+' calculator';q('#incomingCalcMethod').textContent=methodText(calcRule)||calcRule.method?.method_name||'';
    q('#incomingCalcFormula').innerHTML=calcFormula(Number(calcRule.method_key));
    q('#incomingCalcInputs').innerHTML=`<div class="incoming-test-scroll"><table><thead><tr><th>Sample</th>${Object.entries(fields).map(([key,label])=>`<th>${key}<small>${esc(label)}</small></th>`).join('')}</tr></thead><tbody>${Array.from({length:Math.max(1,Number(calcRule.sample_count))},(_,index)=>`<tr><td>S${index+1}</td>${Object.keys(fields).map(key=>`<td><input type="number" step="any" data-calc-key="${key}" data-calc-sample="${index}" value="${esc(model.raw?.[index]?.[key]??'')}" aria-label="${esc(key+' sample '+(index+1))}"></td>`).join('')}</tr>`).join('')}</tbody></table></div>`;
    q('#incomingCalcState').textContent='';q('#incomingCalc').showModal();
  }
  async function applyCalculator(){
    const raw=Array.from({length:Math.max(1,Number(calcRule.sample_count))},()=>({}));
    q('#incomingCalcInputs').querySelectorAll('input').forEach(field=>raw[Number(field.dataset.calcSample)][field.dataset.calcKey]=field.value);
    q('#applyIncomingCalc').disabled=true;
    try{
      const calculated=[];
      for(const inputs of raw){
        if(Object.values(inputs).every(value=>value==='')){calculated.push('');continue;}
        const data=await post('api/calculate.php',{method_key:Number(calcRule.method_key),inputs});calculated.push(data.calculation.result);
      }
      const model=results.get(String(calcRule.id));model.raw=raw;model.samples=calculated;renderTests();markDirty();q('#incomingCalc').close();
    }catch(error){q('#incomingCalcState').textContent=error.message;}
    finally{q('#applyIncomingCalc').disabled=false;}
  }
  function buildPrint(){
    if(!profile||!material){state('Select a material with a specification before printing.',true);return false;}
    templates.update();updateResults();
    q('#incomingPrint').innerHTML=templates.print();
    q('#incomingPrint').dataset.formType=q('#incomingFormType').value;
    let layout=q('#incomingPaperPrintLayout');
    if(!layout){layout=document.createElement('style');layout.id='incomingPaperPrintLayout';document.head.append(layout);}
    layout.textContent=`@media print{@page{size:Letter ${q('#incomingFormType').value==='POWDER'?'landscape':'portrait'};margin:0}}`;
    return true;
  }
  function printForm(){if(buildPrint()){document.body.classList.add('incoming-printing');window.print();}}
  window.addEventListener('afterprint',()=>document.body.classList.remove('incoming-printing'));
  function closeForm(){if(busy)return;if(dirty&&!window.confirm('Discard unsaved form changes?'))return;dirty=false;location.href='Incoming.php';}
  q('#closeIncoming').addEventListener('click',event=>{event.preventDefault();closeForm();});
  q('#clearIncoming').addEventListener('click',()=>{if(busy||!editable()||!window.confirm('Clear all entered values and start a new form?'))return;resetForm();const url=new URL(location.href);url.searchParams.delete('id');history.replaceState(null,'',url);});
  q('#incomingForm').addEventListener('change',event=>{if(event.target.id==='incomingMaterial'){suppliers();markDirty();loadSpec();}else if(event.target.id==='incomingSupplier'){markDirty();loadSpec();}});
  q('#incomingFormType').addEventListener('change',()=>{materialOptions();suppliers();markDirty();loadSpec();});
  q('#incomingForm').addEventListener('input',event=>{if(templates.handle(event.target)){updateResults();lockForm();}});
  q('#incomingForm').addEventListener('change',event=>{if(templates.handle(event.target)){updateResults();lockForm();}});
  q('#incomingForm').addEventListener('input',markDirty);q('#incomingForm').addEventListener('change',markDirty);
  q('#incomingForm').addEventListener('input',event=>{
    const field=event.target,model=results.get(field.dataset.rule||field.dataset.text);if(!model)return;
    if(field.dataset.rule)model.samples[Number(field.dataset.sample)]=field.value;else model.text=field.value;templates.update();updateResults();
  });
  q('#incomingForm').addEventListener('change',event=>{
    const id=event.target.dataset.observation;if(!id)return;
    const rule=profile.rules.find(item=>String(item.id)===id),model=results.get(id),input=q(`[data-text="${id}"]`);
    input.hidden=event.target.value!=='other';model.text=event.target.value==='match'?rule.target_text:event.target.value==='other'?input.value:'';templates.update();updateResults();
  });
  q('#incomingDocumentDetails').addEventListener('click',event=>{
    const calc=event.target.closest('[data-calculator]'),direct=event.target.closest('[data-direct]');
    if(calc)openCalculator(calc.dataset.calculator);
    if(direct){results.get(direct.dataset.direct).raw=null;renderTests();markDirty();}
  });
  q('#saveIncoming').addEventListener('click',save);q('#incomingForm').addEventListener('submit',event=>{event.preventDefault();save();});
  q('#printIncoming').addEventListener('click',printForm);q('#closeIncomingCalc').addEventListener('click',()=>q('#incomingCalc').close());q('#applyIncomingCalc').addEventListener('click',applyCalculator);
  const referenceToggle=q('#toggleIncomingReference'),referenceContent=q('#incomingReferenceContent');
  function setReferenceVisible(visible){referenceContent.hidden=!visible;referenceToggle.setAttribute('aria-expanded',String(visible));referenceToggle.textContent=visible?'Hide reference':'Show supplier reference';}
  referenceToggle.addEventListener('click',()=>setReferenceVisible(referenceContent.hidden));
  setReferenceVisible(!matchMedia('(max-width:650px)').matches);
  window.addEventListener('beforeunload',event=>{if(dirty){event.preventDefault();event.returnValue='';}});
  resetForm();const id=new URLSearchParams(location.search).get('id');if(id)openRecord(id);
})();
