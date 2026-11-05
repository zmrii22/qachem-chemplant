(() => {
  'use strict';
  const q = s => document.querySelector(s);
  const esc = v => String(v ?? '').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  let catalog = {locations:[],parameters:[]}, rows = new Map(), loaded = null, busy = false;
  const selection = () => ({plant:q('#presetPlant').value});
  const state = (text,error=false) => {q('#presetState').textContent=text;q('#presetState').classList.toggle('error',error);};
  function productFor(location,row) {return location.products.find(p=>p.product_id===row.product_id);}
  function allowedFor(location,row) {return productFor(location,row)?.specs.IQS || [];}
  function updateLink() {q('#openInprocess').href='Inprocess.php?'+new URLSearchParams(selection());}
  function rowHtml(location) {
    const row=rows.get(location.location_id), product=productFor(location,row), allowed=allowedFor(location,row);
    const scopes=product?.factory_specifications || ['ALL'];
    return `<tr data-location="${location.location_id}" class="${row.status==='RUN'?'running':'stopped'}"><td>${esc(location.factory)}</td><td class="preset-tank">${esc(location.tank)}</td><td><select data-field="status" aria-label="${esc(location.tank)} status"><option value="STOP" ${row.status==='STOP'?'selected':''}>Stop</option><option value="RUN" ${row.status==='RUN'?'selected':''}>Run</option></select></td><td class="preset-product"><select data-field="product_id" aria-label="${esc(location.tank)} product"><option value="0">-- Select product --</option>${location.products.map(p=>`<option value="${p.product_id}" ${row.product_id===p.product_id?'selected':''}>${esc(p.name)}</option>`).join('')}</select></td><td>${esc(product?.batch || '—')}</td><td><select data-field="factory_specification" aria-label="${esc(location.tank)} factory specification" title="Display only; disposition uses IQS">${scopes.map(scope=>`<option value="${esc(scope)}" ${scope===row.factory_specification?'selected':''}>${esc(scope)}</option>`).join('')}</select></td><td class="working-standard">IQS</td>${catalog.parameters.map(p=>allowed.includes(p.key)?`<td><label class="preset-test"><input type="checkbox" data-parameter="${p.key}" ${row.parameters.includes(p.key)?'checked':''} aria-label="${esc(location.tank+' '+p.short)}"><span>${row.parameters.includes(p.key)?'ON':'OFF'}</span></label></td>`:'<td class="not-applicable" title="Not applicable">—</td>').join('')}</tr>`;
  }
  function render() {
    q('#presetTable thead').innerHTML=`<tr><th>Factory</th><th>Tank / Reactor</th><th>Status</th><th>Product</th><th>Batch</th><th title="Factory scope shown in Inprocess; display only">Specification</th><th>Standard</th>${catalog.parameters.map(p=>`<th>${esc(p.short)}</th>`).join('')}</tr>`;
    q('#presetTable tbody').innerHTML=catalog.locations.map(rowHtml).join('');
  }
  function setBusy(value) {
    busy=value;
    q('#loadPreset').disabled=value;q('#savePreset').disabled=value||!loaded;
    q('#presetPlant').disabled=value;
    q('#presetTable').querySelectorAll('input,select').forEach(control=>{control.disabled=value;});
  }
  async function request(body) {
    const response=await fetch('api/line_presets.php'+(body?'':'?'+new URLSearchParams(selection())),body?{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':window.QACHEM_CSRF},body:JSON.stringify(body)}:{headers:{Accept:'application/json'}});
    const data=await response.json();
    if(!response.ok||!data.success)throw new Error(data.message||'Unable to load presets.');
    return data;
  }
  async function load() {
    if(busy)return;
    setBusy(true);state('Loading line presets…');
    try {
      const data=await request();catalog=data.catalog;
      const saved=new Map(data.rows.map(r=>[r.location_id,r]));rows=new Map();
      catalog.locations.forEach(location=>{
        const existing=saved.get(location.location_id);
        const first=location.products[0];
        const row=existing?{...existing,parameters:[...existing.parameters]}:{location_id:location.location_id,product_id:first?.product_id||0,status:'STOP',parameters:[]};
        row.spec_type='IQS';
        row.factory_specification=row.factory_specification||'ALL';
        const product=productFor(location,row);
        if(!product){row.product_id=0;row.status='STOP';row.parameters=[];row.factory_specification='ALL';}
        else {
          if(!(product.factory_specifications||['ALL']).includes(row.factory_specification))row.factory_specification='ALL';
          row.parameters=(existing?row.parameters:allowedFor(location,row)).filter(key=>allowedFor(location,row).includes(key));
          if(!row.parameters.length)row.status='STOP';
        }
        rows.set(location.location_id,row);
      });
      loaded=selection();render();
      const count=[...rows.values()].filter(r=>r.status==='RUN').length;
      state(`${count} running tank(s). Inprocess shows these tanks alongside each shift's saved setup. Results use IQS.`);
    }catch(error){state(error.message,true);}finally{setBusy(false);}
  }
  async function save() {
    if(!loaded||busy)return;
    setBusy(true);state('Saving line presets…');
    try {
      await request({...loaded,rows:[...rows.values()]});
      QACHEM_UI.flash('Line preset saved.');location.assign(q('#openInprocess').href);
    }catch(error){state('Save failed. '+error.message+' Please retry.',true);}finally{setBusy(false);}
  }
  q('#presetTable').addEventListener('change',event=>{
    const control=event.target;
    const tr=control.closest('[data-location]');if(!tr||busy)return;
    const id=Number(tr.dataset.location),row=rows.get(id),location=catalog.locations.find(l=>l.location_id===id);
    if(control.dataset.field==='product_id') {
      row.product_id=Number(control.value);
      row.spec_type='IQS';row.factory_specification='ALL';
      row.parameters=[...allowedFor(location,row)];
      if(!row.product_id)row.status='STOP';
    }else if(control.dataset.field==='factory_specification') {
      row.factory_specification=control.value;
    }else if(control.dataset.field==='status')row.status=control.value;
    else if(control.dataset.parameter) {
      const key=control.dataset.parameter;
      row.parameters=control.checked?[...new Set([...row.parameters,key])]:row.parameters.filter(k=>k!==key);
    }
    tr.outerHTML=rowHtml(location);
    state('Unsaved preset changes. Click Save Preset to apply them to Inprocess.');
  });
  q('#loadPreset').addEventListener('click',load);
  q('#savePreset').addEventListener('click',save);
  q('#presetPlant').addEventListener('change',()=>{
    loaded=null;q('#savePreset').disabled=true;q('#presetTable thead').innerHTML='';q('#presetTable tbody').innerHTML='';
    updateLink();state('Click Load Preset for the selected plant.');
  });
  const params=new URLSearchParams(location.search);
  if(params.has('plant'))q('#presetPlant').value=params.get('plant');
  updateLink();load();
})();
