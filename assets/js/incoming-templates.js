/* Keep the paper form and its printed values in sync with the Incoming editor. */
window.createIncomingTemplates=({esc,user,verifiers=[]})=>{
  const q=selector=>document.querySelector(selector),paper=()=>q('#incomingPaper');
  let form={},context=null;
  function reset(type=''){form={type,disposition_checks:null,packaging_remark:'',viscosity:{},observations:{},particle_size:{},sample_labels:{},signoff:{verifier_badge:'',verification_date:''},sections:{}};context=null;}
  reset();
  function restore(stored){form={...form,...JSON.parse(JSON.stringify(stored))};form.observations??={};form.sample_labels??={};}
  function mount(type){
    const template=q(type==='POWDER'?'#incomingPowderTemplate':'#incomingLiquidTemplate');
    paper().replaceChildren(template.content.cloneNode(true));paper().hidden=!type;
    paper().querySelectorAll('.form-table').forEach(table=>{
      const viewport=document.createElement('div');viewport.className='incoming-table-viewport';
      viewport.tabIndex=0;viewport.setAttribute('role','region');viewport.setAttribute('aria-label','Form readings table; scroll sideways if needed');
      table.before(viewport);viewport.append(table);
    });
    if(type==='POWDER'){
      const hint=document.createElement('p');hint.className='incoming-table-hint screen-only';
      hint.textContent='Scroll tables sideways to see all sample fields.';
      paper().querySelector('.header').after(hint);
    }
  }
  const ruleFor=code=>context?.profile.rules.find(rule=>rule.parameter_code===code&&rule.operator!=='INFO');
  const modelFor=code=>{const rule=ruleFor(code);return rule?context.results.get(String(rule.id)):null;};
  const isSection=rule=>['APP','PH','VISC','SG','TSC'].includes(rule.parameter_code)||/^PURITY(?:_\d+)?$/.test(rule.parameter_code);
  const keysFor=method=>({1:['A','B','C'],7:['A','B','C'],12:['C','D'],14:['A','B','C'],15:['A','B','C'],16:['A','C','D'],17:['A','C','D']}[method]||[]);
  const methodFor=key=>({TSC:1,WET_SIEVE:7,MOISTURE:14,DRY_SIEVE:15}[key]||Number(ruleFor(key)?.method_key)||0);
  function initialize(profile,results,type){
    context={profile,results,type};form.type=type;
    const method=profile.rules.find(rule=>rule.parameter_code==='VISC_METHOD')?.target_text||'';
    if(!Object.keys(form.viscosity).length){
      const spindle=method.match(/\b(LV|RV)\s*(\d+)/i),speed=method.match(/([\d.]+)\s*rpm/i),time=method.match(/([\d.]+)\s*min/i);
      form.viscosity={setup:method,instrument:spindle?.[1]?.toUpperCase()||'',spindle:spindle?.[2]||'',speed_rpm:speed?.[1]||'',time_minutes:time?.[1]||'',temperature_c:''};
    }
    const keys=type==='POWDER'?['TSC','MOISTURE','WET_SIEVE','DRY_SIEVE']:['TSC','WET_SIEVE'];
    const purity=profile.rules.filter(rule=>/^PURITY(?:_\d+)?$/.test(rule.parameter_code)&&rule.operator!=='INFO').map(rule=>rule.parameter_code);
    keys.push(...(purity.length?purity:['PURITY']));
    for(const key of keys){
      if(form.sections[key])continue;
      const model=modelFor(key);
      form.sections[key]={enabled:!!model,mode:!methodFor(key)||model?.samples.some(v=>v!==''&&v!==null)&&!model.raw?'direct':'raw',inputs:Array.from({length:3},()=>({})),samples:model?[...model.samples]:['','',''],filter_size_um:''};
      if(model?.raw)model.raw.forEach((inputs,i)=>form.sections[key].inputs[i]=key.startsWith('PURITY')?{A:inputs.A??'',N:'',V:inputs.D!==''&&inputs.C!==''?Number(inputs.D)-Number(inputs.C):''}:{...inputs});
    }
    return keys;
  }
  function calculate(method,inputs){
    const required=keysFor(method);if(!required.length||required.some(key=>inputs[key]===''||inputs[key]===undefined||inputs[key]===null))return '';
    const {A,B,C,D}=Object.fromEntries(Object.entries(inputs).map(([key,value])=>[key,Number(value)]));let value;
    switch(method){case 1:case 15:value=(C-A)/B*100;break;case 7:value=(C-B)/A*100;break;case 12:value=(D-C)*25*0.0142;break;case 14:value=(A+B-C)/(A+B)*100;break;case 16:value=(D-C)*3.72/(2*A);break;case 17:value=(D-C)*4/A;break;}
    return Number.isFinite(value)?value:'';
  }
  function rawInputs(key,index){
    const inputs=form.sections[key].inputs[index]||{};
    if(key==='WET_SIEVE')return {A:inputs.W1??'',B:inputs.W2??'',C:inputs.W3??''};
    if(key.startsWith('PURITY'))return {A:inputs.A??'',C:0,D:inputs.V??''};
    return {A:inputs.A??'',B:inputs.B??'',C:inputs.C??''};
  }
  function values(key){
    const section=form.sections[key];if(!section)return ['','',''];
    return Array.from({length:3},(_,i)=>!section.enabled?'':section.mode==='direct'||!methodFor(key)?section.samples[i]??'':calculate(methodFor(key),rawInputs(key,i)));
  }
  const average=list=>{const valid=list.filter(v=>v!==''&&v!==null).map(Number);return valid.length?valid.reduce((a,b)=>a+b,0)/valid.length:null;};
  function fillObservation(code){
    const slot=paper().querySelector(`[data-document-observation="${code}"]`),rule=ruleFor(code),model=modelFor(code);if(!slot)return;
    if(!rule){slot.innerHTML=`<input class="cell-input" ${code==='APP'?'':'type="number" step="any"'} data-document-other="${code}" value="${esc(form.observations[code]??'')}">`;return;}
    if(rule.data_type==='NUMERIC'){
      slot.innerHTML=model.samples.map((value,i)=>`<input type="number" step="any" class="cell-input" data-rule="${rule.id}" data-sample="${i}" value="${esc(value)}" aria-label="${esc(rule.parameter_name+' S'+(i+1))}">`).join('');
    }else{
      const other=model.text&&model.text!==rule.target_text;
      slot.innerHTML=`<select class="cell-input" data-observation="${rule.id}" data-print-value="${esc(model.text)}"><option value="">Select...</option><option value="match" ${model.text===rule.target_text?'selected':''}>Matches reference</option><option value="other" ${other?'selected':''}>Different / describe</option></select><input class="cell-input" data-text="${rule.id}" value="${esc(other?model.text:'')}" ${other?'':'hidden'}>`;
    }
  }
  function render(profile,results,type,record=null){
    const keys=initialize(profile,results,type);
    ['APP','PH','VISC','SG'].forEach(fillObservation);
    if(q('#incomingPackagingRemark'))q('#incomingPackagingRemark').value=form.packaging_remark;
    q('#incomingViscositySetup').value=form.viscosity.setup??[form.viscosity.instrument&&form.viscosity.instrument+(form.viscosity.spindle||''),form.viscosity.speed_rpm!==''?form.viscosity.speed_rpm+' rpm':''].filter(Boolean).join(', ');
    if(q('#incomingFilterSize'))q('#incomingFilterSize').value=form.sections.DRY_SIEVE?.filter_size_um??'';
    paper().querySelectorAll('[data-document-label]').forEach(input=>{form.sample_labels[input.dataset.documentLabel]=['S1','S2','S3'];input.value='S'+(Number(input.dataset.documentSample)+1);});
    paper().querySelectorAll('[data-document-section]').forEach(input=>{
      const key=input.dataset.documentSection,index=Number(input.dataset.documentSample),field=input.dataset.documentKey;
      input.value=key==='PARTICLE'?form.particle_size[field]??'':form.sections[key]?.inputs[index]?.[field]??'';
    });
    const purity=keys.filter(key=>key.startsWith('PURITY'));
    paper().querySelectorAll('[data-document-purity]').forEach(slot=>{
      const field=slot.dataset.documentPurity,index=Number(slot.dataset.documentSample);
      if(field==='AVERAGE'){slot.innerHTML=purity.map(key=>`<input readonly class="cell-input" data-purity-average="${key}">`).join(' / ');return;}
      slot.innerHTML=purity.map(key=>`${purity.length>1?`<small>${esc(ruleFor(key)?.parameter_name)}: </small>`:''}<input class="cell-input" type="number" step="any" data-document-section="${key}" data-document-key="${field}" data-document-sample="${index}" value="${esc(field==='RESULT'?values(key)[index]:form.sections[key].inputs[index]?.[field]??'')}">`).join('<br>');
    });
    const signed=record?.verified_by_name;
    const signoff=form.signoff||{};
    const verifier=String(signed?record.verified_by_badge||'historical':signoff.verifier_badge||'');
    const verifierName=signed||signoff.verifier_name||'';
    paper().querySelectorAll('[data-document-signature]').forEach(slot=>{
      switch(slot.dataset.documentSignature){
        case 'tested':slot.value=!record||record.status==='DRAFT'?user:record.tested_by_name||signoff.tester_name||record.created_by_name||user;break;
        case 'verified':{
          const options=[...verifiers];
          if(verifier&&!options.some(option=>String(option.badge)===verifier))options.push({badge:verifier,name:verifierName||verifier});
          slot.innerHTML='<option value="">Select verifier</option>'+options.map(option=>`<option value="${esc(option.badge)}">${esc(option.name)}</option>`).join('');
          slot.value=verifier;break;
        }
        case 'date':slot.value=record?.verified_at?.slice(0,10)||signoff.verification_date||'';break;
      }
    });
    if(form.disposition_checks===null)form.disposition_checks=[...new Set([record?.overall_disposition,record?.final_disposition||record?.proposed_disposition].filter(value=>['PASS','FAIL','ACCEPT','ACCEPT WITH LOC','REJECT WITH LOC'].includes(value)))];
    syncDispositions();update();
  }
  const formatted=value=>value===null||value===''||value===undefined?'':Number(value).toFixed(2);
  function update(){
    if(!context)return;
    for(const [key,section] of Object.entries(form.sections)){
      const list=values(key),rule=ruleFor(key),model=modelFor(key);
      if(model){model.samples=list.slice(0,Math.max(1,Number(rule.sample_count)));model.raw=section.mode==='raw'&&methodFor(key)?model.samples.map((_,i)=>rawInputs(key,i)):null;}
      paper().querySelectorAll(`[data-document-section="${key}"][data-document-key="RESULT"]`).forEach(input=>{const v=list[Number(input.dataset.documentSample)];if(document.activeElement!==input)input.value=formatted(v);});
      paper().querySelectorAll(`[data-document-output="${key}"]`).forEach(output=>{
        const which=output.dataset.documentOutputKey;if(which==='result')return;
        const listForAverage=key==='WET_SIEVE'?list.map(v=>v===''?'':100-Number(v)):list;
        output.value=formatted(which==='fine'?(list[Number(output.dataset.documentSample)]===''?null:100-Number(list[Number(output.dataset.documentSample)])):average(listForAverage));
      });
      const purityAvg=paper().querySelector(`[data-purity-average="${key}"]`);if(purityAvg)purityAvg.value=formatted(average(list));
    }
    paper().querySelectorAll('[data-observation]').forEach(select=>select.dataset.printValue=context.results.get(select.dataset.observation)?.text||'');
  }
  function syncDispositions(){
    const checks=form.disposition_checks||[];
    paper().querySelectorAll('[data-document-disposition]').forEach(input=>input.checked=checks.includes(input.dataset.documentDisposition));
    q('#incomingProposed').value=checks.find(v=>['ACCEPT','ACCEPT WITH LOC','REJECT WITH LOC'].includes(v))||'';
  }
  function handle(field){
    if(field.id==='incomingPackagingRemark'){form.packaging_remark=field.value;return true;}
    if(field.id==='incomingViscositySetup'){
      const text=field.value,spindle=text.match(/\b(LV|RV)\s*(\d+)/i),speed=text.match(/([\d.]+)\s*rpm/i),time=text.match(/([\d.]+)\s*min/i);
      form.viscosity={...form.viscosity,setup:text,instrument:spindle?.[1]?.toUpperCase()||'',spindle:spindle?.[2]||'',speed_rpm:speed?.[1]||'',time_minutes:time?.[1]||''};return true;
    }
    if(field.id==='incomingFilterSize'){form.sections.DRY_SIEVE.filter_size_um=field.value;return true;}
    if(field.dataset.documentOther){form.observations[field.dataset.documentOther]=field.value;return true;}
    if(field.dataset.documentSignature){
      const key={verified:'verifier_badge',date:'verification_date'}[field.dataset.documentSignature];
      if(!key)return false;
      form.signoff??={};form.signoff[key]=field.value;
      paper().querySelectorAll(`[data-document-signature="${field.dataset.documentSignature}"]`).forEach(input=>input.value=field.value);
      return true;
    }
    if(field.dataset.documentSection){
      const key=field.dataset.documentSection,which=field.dataset.documentKey,index=Number(field.dataset.documentSample);
      if(key==='PARTICLE'){form.particle_size[which]=field.value;return true;}
      const section=form.sections[key];if(!section)return false;
      if(which==='RESULT'){const previous=values(key);section.mode='direct';section.samples=previous;section.samples[index]=field.value;}
      else{section.inputs[index][which]=field.value;if(methodFor(key))section.mode='raw';}
      section.enabled=!!ruleFor(key)||section.inputs.some(input=>Object.values(input).some(v=>v!==''&&v!==null))||section.samples.some(v=>v!==''&&v!==null);
      update();return true;
    }
    if(field.dataset.documentDisposition){
      const value=field.dataset.documentDisposition;
      const group=['PASS','FAIL'].includes(value)?['PASS','FAIL']:['ACCEPT','ACCEPT WITH LOC','REJECT WITH LOC'];
      let checks=(form.disposition_checks||[]).filter(v=>field.checked?!group.includes(v):v!==value);
      if(field.checked)checks.push(value);
      form.disposition_checks=checks;syncDispositions();return true;
    }
    return false;
  }
  function print(){
    update();const original=paper().querySelector('.incoming-html-form'),clone=original.cloneNode(true);
    const originals=[...original.querySelectorAll('input,select,textarea')],copies=[...clone.querySelectorAll('input,select,textarea')];
    originals.forEach((control,index)=>{
      if(control.type==='checkbox'){copies[index].toggleAttribute('checked',control.checked);return;}
      const span=document.createElement('span');span.className=control.className+' incoming-printed-value';span.style.cssText=control.style.cssText;span.style.display='inline-block';
      if(control.hidden||control.type==='hidden'){copies[index].remove();return;}
      if(control.dataset.documentSignature)span.dataset.documentSignature=control.dataset.documentSignature;
      let value=control.value;
      if(control.tagName==='SELECT')value=control.dataset.printValue??(control.value?control.selectedOptions[0].textContent:'');
      else if(control.type==='date'&&value)value=value.split('-').reverse().join('/');
      else if(control.type==='datetime-local'&&value){const [date,time]=value.split('T');value=date.split('-').reverse().join('/')+' '+time;}
      span.textContent=value;
      if(control.style.width)span.style.width=control.style.width;
      copies[index].replaceWith(span);
    });
    clone.querySelectorAll('[hidden]').forEach(element=>element.remove());
    return clone.outerHTML;
  }
  return {mount,reset,restore,render,handle,update,print,collect:()=>JSON.parse(JSON.stringify(form)),isSection};
};
