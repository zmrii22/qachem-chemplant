/* Shared summary with entry dialogs by parameter or by tank. */
(() => {
  'use strict';
  const q = selector => document.querySelector(selector);
  const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const canonical = tab => tab === 'VTQ' ? 'VCP' : tab;
  const user = window.QACHEM_INPROCESS.user;
  const canEdit = window.QACHEM_INPROCESS.canEdit;
  const plantPreferenceKey = window.QACHEM_INPROCESS.plantPreferenceKey;
  const validPlant = value => [...q('#plant').options].some(option => option.value !== '' && option.value === value);
  function rememberPlant() {
    try {
      const plant=q('#plant').value;
      if(validPlant(plant))localStorage.setItem(plantPreferenceKey,plant);
      else localStorage.removeItem(plantPreferenceKey);
    } catch (_) { /* The form still works when browser storage is unavailable. */ }
  }
  function defaultSamplingTime() {
    const now=new Date();let hour=now.getHours();
    try {hour=Number(new Intl.DateTimeFormat('en-GB',{timeZone:window.QACHEM_INPROCESS.timeZone,hour:'numeric',hourCycle:'h23'}).format(now));} catch (_) { /* Use the local clock if Intl is unavailable. */ }
    return hour>=7&&hour<19?'08:00:00':'20:00:00';
  }
  const fields = {
    PH: {A:'pH reading', B:'Temperature (°C)'},
    VCP: {A:'Viscosity (cP)', B:'Torque (%)', C:'Temperature (°C)'},
    TSC: {A:'Foil / dish weight (g)', B:'Wet sample (g)', C:'Dish + dry sample (g)', U:'Dry sample (g)', V:'TSC (%)'},
    ALK: {C:'Initial titrant V1 (mL)', D:'Final titrant V2 (mL)', E:'Final titrant V3 (mL)', U:'Volume (mL)', V:'Alkalinity (%)'},
    CHL: {A:'Sample weight (g)', C:'Initial titrant (mL)', D:'Final titrant (mL)', U:'Volume (mL)', V:'Chlorine (%)'},
    FOAM: {C:'Initial height (cm)', D:'Final height (cm)'},
    ACD: {B:'Initial titrant (mL)', C:'Final titrant (mL)', U:'Volume (mL)', V:'Acidity (%)'},
    BAC: {A:'Bacteria count'}, SG: {A:'Specific gravity'}
  };
  let tabs = [], records = new Map(), summaryRows = [], context = null;
  let activeTab = '', activeTank = '', loading = false, saving = false, loadVersion = 0, returnFocus = null, compareVersion = 0;
  const timers = new Map();
  const batchSaves = new Set();
  const batchFeedback = new Map();

  function resetBatchFeedback() {
    batchFeedback.forEach(feedback => clearTimeout(feedback.timer));
    batchFeedback.clear();
  }
  function batchButtonContent(state) {
    if (state === 'success') return '<svg viewBox="0 0 20 20" aria-hidden="true"><path d="m4 10 4 4 8-8"/></svg>';
    if (state === 'error') return '<svg viewBox="0 0 20 20" aria-hidden="true"><path d="m5 5 10 10M15 5 5 15"/></svg>';
    return '<i class="fi fi-rr-disk" aria-hidden="true"></i>';
  }
  function updateBatchButtons(key) {
    const state = batchFeedback.get(key)?.state || '';
    const label = state === 'success' ? 'Batch saved' : state === 'error' ? 'Batch not saved. Click to retry.' : 'Save batch number';
    document.querySelectorAll('[data-save-batch]').forEach(button => {
      if (button.dataset.saveBatch !== key) return;
      button.className = 'batch-save' + (state ? ' is-' + state : '');
      button.innerHTML = batchButtonContent(state);
      button.title = label;
      button.setAttribute('aria-label', label);
      button.disabled = batchSaves.has(key) || saving;
      button.setAttribute('aria-busy', String(batchSaves.has(key)));
    });
  }
  function showBatchFeedback(key, state) {
    clearTimeout(batchFeedback.get(key)?.timer);
    batchFeedback.delete(key);
    if (state) {
      const feedback = {state, timer:setTimeout(() => {
        batchFeedback.delete(key);
        updateBatchButtons(key);
      }, state === 'success' ? 2000 : 3500)};
      batchFeedback.set(key, feedback);
    }
    updateBatchButtons(key);
  }

  function number(value) {
    if (value === '' || value === null || value === undefined) return '--';
    return Number.isFinite(Number(value)) ? Number(value).toFixed(2) : String(value);
  }
  function standard(rule) {
    if (!rule) return '';
    const low = number(rule.low_value), high = number(rule.high_value);
    switch (String(rule.operator).toUpperCase()) {
      case 'RANGE': return `${low} – ${high}`;
      case 'MIN': case 'GTE': return `≥ ${low}`;
      case 'GT': return `> ${low}`;
      case 'MAX': case 'LTE': return `≤ ${high}`;
      case 'LT': return `< ${high}`;
      case 'EQ': return `= ${low}`;
      default: return rule.target_text || '';
    }
  }
  function disposition(rule, value) {
    if (value === null || value === '' || value === undefined || !Number.isFinite(Number(value))) return 'PENDING';
    const v = Number(value);
    const limit = key => rule[key] === null || rule[key] === undefined || rule[key] === '' ? null : Number(rule[key]);
    const low = limit('low_value'), high = limit('high_value');
    switch (String(rule.operator).toUpperCase()) {
      case 'RANGE': return (low === null || v >= low) && (high === null || v <= high) ? 'PASS' : 'FAIL';
      case 'MIN': case 'GTE': return low !== null && v >= low ? 'PASS' : 'FAIL';
      case 'GT': return low !== null && v > low ? 'PASS' : 'FAIL';
      case 'MAX': case 'LTE': return high !== null && v <= high ? 'PASS' : 'FAIL';
      case 'LT': return high !== null && v < high ? 'PASS' : 'FAIL';
      case 'EQ': return low !== null && Math.abs(v - low) < 0.000001 ? 'PASS' : 'FAIL';
      case 'INFO': return 'PASS';
      default: return 'PENDING';
    }
  }
  const resultStatus = status => ['PASS','FAIL'].includes(status) ? status : 'PENDING';
  const statusClass = status => resultStatus(status).toLowerCase();
  function setState(message, error = false) {
    q('#gridState').textContent = message;
    q('#gridState').classList.toggle('error', error);
  }
  async function request(url, body) {
    const response = await fetch(url, body ? {
      method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':window.QACHEM_CSRF}, body:JSON.stringify(body)
    } : {headers:{Accept:'application/json'}});
    let data;
    try { data = await response.json(); } catch (_) { throw new Error('Unable to read the server response. Please sign in again if your session expired.'); }
    if (!response.ok || !data.success) throw new Error(data.message || 'Request failed.');
    return data;
  }
  const rowKey = row => `${row.location_id}:${row.product_id}:${row.run_no||1}:${row.legacy_key||row.root_batch_id||0}`;
  const runLabel = row => (row.run_no||1)>1?(sampleTypes[row.sample_type]||'New Sample'):'';
  const editable = record => canEdit && !record.row.is_history && !record.row.is_read_only && !(record.isFollowup&&record.carryForward);
  const recordState = record => record.row.is_history ? 'History · Read only' : record.row.is_read_only ? 'Read only' : record.carryForward ? 'Copied PASS' : record.id&&record.value!==null ? 'Saved' : 'Enter new readings';
  function recordKey(tab, row, spec) {
    return `${canonical(tab)}:${rowKey(row)}:${spec}`;
  }
  function recordFor(tab, row, spec) {
    return records.get(recordKey(tab, row, spec));
  }
  function makeRecord(tab, row, option) {
    const spec = option.spec_type.toUpperCase();
    const saved = row.saved_by_spec?.[spec];
    const rule = option.rules[0];
    const raw = Array.isArray(saved?.raw_samples) ? saved.raw_samples : [];
    return {
      key:recordKey(tab, row, spec), tab, row, spec, rule, profileId:option.profile_id,
      samples:Array.from({length:Number(rule.sample_count) || 3}, (_, i) => ({...(raw[i] || {})})),
      torqueValue:saved?.torque_value ?? null,
      value:saved ? (saved.result_value ?? saved.result_text ?? null) : null,
      disposition:resultStatus(saved?.disposition), id:saved?.inspection_id || null,
      modified:saved?.modified_display || '', author:saved?.modified_by_name || saved?.created_by_name || user,
      modifiedAt:saved?.modified ? new Date(saved.modified.replace(' ', 'T')).getTime() : 0,
      carryForward:saved?.carry_forward||null,
      dirty:false, version:0, error:'', details:[]
    };
  }
  function populate(data) {
    tabs = data.tabs;
    records = new Map();
    const merged = new Map();
    Object.entries(data.parameter_rows).forEach(([tab, rows]) => rows.forEach(row => {
      const key = rowKey(row);row.key=key;
      let summary = merged.get(key);
      if (!summary) {
        summary = {...row, key, savedBatch:row.batch || '', sample_type:row.sample_type||'NEW_SAMPLE', batchDirty:false, specs:new Set()};
        merged.set(key, summary);
      }
      row.spec_options.forEach(option => {
        if (!option.rules?.length) return;
        summary.specs.add(option.spec_type.toUpperCase());
        const record = makeRecord(tab, row, option);
        records.set(record.key, record);
      });
    }));
    summaryRows = [...merged.values()].sort((a,b) => a.location_id - b.location_id || a.product.localeCompare(b.product) || (a.run_no||1)-(b.run_no||1));
    renderSummary();
  }
  async function loadGrid() {
    if (loading || saving) return;
    updatePresetLinks();
    const selected = {date:q('#inspectionDate').value, sampling_time:q('#samplingTime').value, plant:q('#plant').value};
    if (!selected.date || !selected.sampling_time || !selected.plant) {
      setState('Select Date, Sampling Time and Plant, then click Load.', true);
      return;
    }
    const version = ++loadVersion;
    loading = true;
    q('#loadGridBtn').disabled = true;
    setState('Loading all parameter results…');
    try {
      const data = await request('api/inprocess_grid.php?' + new URLSearchParams({...selected, tab:'ALL'}));
      if (version !== loadVersion) return;
      resetBatchFeedback();
      context = {...selected,presetSaved:Boolean(data.preset_saved),currentRunning:Number(data.current_running_count||0)};
      populate(data);
      q('#presetNotice').hidden=summaryRows.length>0;
      q('#loadedContext').textContent = `${selected.plant} · ${selected.sampling_time === '08:00:00' ? 'Day' : 'Night'} · ${selected.date}`;
      updateSummaryState();
    } catch (error) { if (version === loadVersion) setState(error.message, true); }
    finally {loading = false; q('#loadGridBtn').disabled = false;}
  }
  function updatePresetLinks() {
    const params=new URLSearchParams();
    if(q('#plant').value)params.set('plant',q('#plant').value);
    const href='LinePreset.php'+(params.size?'?'+params:'');
    q('#linePresetShortcut').href=href;
    q('#configurePreset').href=href;
  }
  function clearGrid() {
    updatePresetLinks();
    ++loadVersion;
    closeComparison();
    closeModal();
    timers.forEach(timer => clearTimeout(timer));
    timers.clear();
    resetBatchFeedback();
    context = null; records.clear(); summaryRows = []; tabs = [];
    q('#presetNotice').hidden=true;
    q('#gridHead').innerHTML = '';
    q('#gridBody').innerHTML = '<tr><td class="empty-message">Choose a date, shift and plant to load the summary.</td></tr>';
    q('#loadedContext').textContent = '';
    setState(q('#plant').value?'Click Load to show results.':'Select a plant, then click Load.');
  }
  function applicableRows() {
    return summaryRows;
  }
  function updateSummaryState() {
    const setup=context?.presetSaved?(context.currentRunning?'Saved shift + current running tanks.':'Saved shift.'):'Current preset. No saved readings.';
    const tanks=new Set(applicableRows().map(row=>row.location_id)).size;
    const followups=applicableRows().filter(row=>(row.run_no||1)>1).length;
    setState(`${tanks} tank(s)${followups?`, ${followups} follow-up(s)`:''}. ${setup}`);
  }
  function torqueValue(record) {
    if(!canEdit)return record.torqueValue;
    const values = record.samples.map(s => s.B).filter(v => v !== '' && v !== null && v !== undefined && Number.isFinite(Number(v))).map(Number);
    return values.length ? values.reduce((sum, v) => sum + v, 0) / values.length : null;
  }
  function summaryCell(row, tab) {
    const record = recordFor(tab.key, row, row.spec_type);
    if (!record) return '<td class="not-applicable" title="Not applicable">—</td>';
    const torque = tab.key === 'VTQ';
    const value = torque ? torqueValue(record) : record.value;
    const status = torque ? 'neutral' : statusClass(record.disposition);
    return `<td class="parameter-result ${status}"><button type="button" class="result-box" data-compare-row="${esc(row.key)}" data-compare-tab="${esc(tab.key)}" aria-haspopup="dialog" aria-label="${esc(row.tank+' '+tab.short+' IQS and QS comparison')}" title="Right-click to compare IQS and QS">${esc(number(value))}${record.dirty ? '<sup title="Unsaved preview">*</sup>' : ''}</button></td>`;
  }
  function overall(row) {
    const applicable = [...new Set(tabs.map(tab => recordFor(tab.key, row, row.spec_type)).filter(Boolean))];
    if (applicable.some(r => r.disposition === 'FAIL')) return 'FAIL';
    if (!applicable.length || applicable.some(r => r.disposition !== 'PASS')) return 'PENDING';
    return 'PASS';
  }
  const sampleTypes={RETEST:'Retest',REWORK:'Rework',NEW_SAMPLE:'New Sample'};
  function batchField(row) {
    if(!canEdit||row.is_history||row.is_read_only||(row.run_no||1)>1)return '<span class="locked-batch-value">'+esc(row.batch||'—')+'</span>';
    const key = row.key || rowKey(row);
    const state = batchFeedback.get(key)?.state || '';
    const label = state === 'success' ? 'Batch saved' : state === 'error' ? 'Batch not saved. Click to retry.' : 'Save batch number';
    return `<div class="batch-field"><div class="batch-editor"><input type="text" class="batch-input" data-batch-row="${esc(key)}" value="${esc(row.batch || '')}" maxlength="100" placeholder="Enter batch" autocomplete="off" aria-label="${esc(row.tank+' '+row.product+' batch')}" title="Complete the product prefix with your batch number."><button type="button" class="batch-save${state?' is-'+state:''}" data-save-batch="${esc(key)}" title="${label}" aria-label="${label}" aria-busy="${batchSaves.has(key)}" ${batchSaves.has(key)?'disabled':''}>${batchButtonContent(state)}</button></div></div>`;
  }
  function changeBatch(input) {
    const row = summaryRows.find(row => row.key === input.dataset.batchRow);
    if (!row || row.is_history || row.is_read_only || (row.run_no||1)>1 || saving || loading) return;
    showBatchFeedback(row.key, '');
    row.batch = input.value;
    row.batchDirty = row.batch.trim() !== row.savedBatch;
    for (const record of records.values()) {
      if (rowKey(record.row) !== rowKey(row)) continue;
      record.row.batch = row.batch;
    }
    document.querySelectorAll('[data-batch-row]').forEach(field => {
      if (field !== input && field.dataset.batchRow === row.key) field.value = row.batch;
    });
    setState(row.batchDirty ? 'Batch changed. Click the small Save button beside the batch.' : 'Batch matches the saved value.');
  }
  async function saveBatch(key) {
    if(!canEdit)return;
    const row=summaryRows.find(row=>row.key===key);
    if(!row||row.is_history||row.is_read_only||(row.run_no||1)>1||!context||saving||loading||batchSaves.has(key))return false;
    const savedContext=context,value=row.batch.trim();
    if(!value){showBatchFeedback(key,'error');setState('Enter the batch number before saving.',true);if(activeTab||activeTank)q('#sInputState').textContent='Enter the batch number before saving.';return false;}
    batchSaves.add(key);
    showBatchFeedback(key,'');
    try{
      const saved=await request('api/inprocess_batch.php',{...context,product_id:row.product_id,location_id:row.location_id,batch_no:value,run_id:row.run_id});
      if(context!==savedContext)return false;
      row.run_id=saved.run_id;for(const record of records.values())if(rowKey(record.row)===row.key)record.row.run_id=saved.run_id;row.savedBatch=saved.batch_no;row.batch_saved=true;row.batchDirty=row.batch.trim()!==row.savedBatch;
      row.batch_modified=saved.modified;row.batch_author=saved.modified_by_name;
      refreshSummary({row});
      if(!row.batchDirty)showBatchFeedback(key,'success');
      setState('Batch '+saved.batch_no+' saved.');
      if(activeTab||activeTank)q('#sInputState').textContent='Batch '+saved.batch_no+' saved.';
      return true;
    }catch(error){if(context===savedContext){showBatchFeedback(key,'error');setState(error.message,true);if(activeTab||activeTank)q('#sInputState').textContent=error.message;}return false;}
    finally{batchSaves.delete(key);updateBatchButtons(key);}
  }
  function summaryRowHtml(row) {
    const status = overall(row);
    const saved = [...records.values()].filter(r => rowKey(r.row) === rowKey(row) && r.spec === row.spec_type && r.modified);
    const lastModified = saved.sort((a,b) => b.modifiedAt - a.modifiedAt || (b.id||0)-(a.id||0))[0];
    const batchAt=row.batch_modified?new Date(row.batch_modified.replace(' ','T')).getTime():0;
    const batchNewest=batchAt&&(!lastModified||batchAt>=lastModified.modifiedAt);
    const batchDisplay=row.batch_modified?row.batch_modified.slice(0,10).split('-').reverse().join('/')+' '+row.batch_modified.slice(11,16):'';
    const modified = batchNewest ? `<span class="modified-user">${esc(row.batch_author)}</span><span class="modified-time">${esc(batchDisplay)}</span>` : lastModified ? `<span class="modified-user">${esc(lastModified.author)}</span><span class="modified-time">${esc(lastModified.modified)}</span>` : '--';
    const mark=(row.run_no||1)>1?`<span class="test-type-icon ${esc(row.sample_type.toLowerCase())}" role="img" tabindex="0" title="${esc(runLabel(row))}" aria-label="${esc(runLabel(row))}">${typeIcon(row.sample_type)}</span>`:'';
    const action=canEdit&&row.run_id&&!row.is_history&&(['PASS','FAIL'].includes(status)||saved.some(record=>record.value!==null&&record.value!==''))?`<button type="button" class="test-menu-button" data-followup="${esc(row.key)}" aria-label="Retest, Rework or New Sample for ${esc(row.tank)}" title="Retest / Rework / New Sample" aria-haspopup="menu"><svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="4" r="1.5"/><circle cx="10" cy="10" r="1.5"/><circle cx="10" cy="16" r="1.5"/></svg></button>`:'';
    return `<tr class="${row.is_history?'run-history':(row.run_no||1)>1?'run-followup':''}" data-summary="${esc(row.key)}"><td class="factory-cell">${esc(row.factory || context.plant)}</td><td class="tank-cell"><div class="tank-name-row">${canEdit?`<button type="button" class="tank-button" data-open-tank="${esc(row.key)}" aria-haspopup="dialog" aria-label="Enter all readings for ${esc(row.tank)}" title="Enter all applicable readings for ${esc(row.tank)}">${esc(row.tank)}</button>`:`<strong>${esc(row.tank)}</strong>`}${mark}${action}</div></td><td class="product-cell">${esc(row.product)}</td><td class="batch-cell">${batchField(row)}</td><td class="factory-spec-cell" title="Factory specification — display only">${esc(row.factory_specification||'ALL')}</td>${tabs.map(tab => summaryCell(row, tab)).join('')}<td><span class="dispo-badge ${statusClass(status)}">${status}</span></td><td class="modified-cell">${modified}</td></tr>`;
  }
  function renderSummary() {
    q('#gridHead').innerHTML = `<tr><th class="factory-cell">Factory</th><th class="tank-cell">Tank</th><th class="product-cell">Product</th><th>Batch</th><th class="factory-spec-cell" title="Factory specification — display only">Spec</th>${tabs.map(tab => `<th class="parameter-heading">${canEdit?`<button type="button" class="column-button" data-tab="${esc(tab.key)}" aria-haspopup="dialog" ${recordsForTab(tab.key).length?'':'disabled'}>${esc(tab.short)}<small>${new Set(recordsForTab(tab.key).map(r=>r.row.location_id)).size} tanks</small></button>`:`<span class="readonly-column">${esc(tab.short)}<small>${new Set(recordsForTab(tab.key).map(r=>r.row.location_id)).size} tanks</small></span>`}</th>`).join('')}<th>Dispo.</th><th>Modified</th></tr>`;
    const rows = applicableRows();
    q('#gridBody').innerHTML = rows.length ? rows.map(summaryRowHtml).join('') : `<tr><td colspan="${7 + tabs.length}" class="empty-message">No running tanks with enabled tests for this plant. Configure Line Preset first.</td></tr>`;
  }
  function refreshSummary(record) {
    const row = summaryRows.find(row => rowKey(row) === rowKey(record.row));
    if (!row) return;
    const element = [...q('#gridBody').children].find(tr => tr.dataset.summary === row.key);
    if (element) element.outerHTML = summaryRowHtml(row);
  }
  function columnsFor(record) {
    const headings = fields[canonical(record.tab)] || {A:'Reading'};
    let schema = {};
    try {schema = typeof record.rule.input_schema === 'string' ? JSON.parse(record.rule.input_schema) : (record.rule.input_schema || {});} catch (_) { /* Use parameter headings if master schema is absent. */ }
    const declared = [...String(schema.inputs || '').matchAll(/\b([A-F])\s*=/g)].map(match => match[1]);
    const input = declared.length ? declared : Object.keys(headings).filter(key => /^[A-F]$/.test(key));
    return [...input, ...Object.keys(headings).filter(key => key === 'U' || key === 'V')];
  }
  function recordsForTab(tab) {
    return summaryRows.map(row => recordFor(tab, row, row.spec_type)).filter(Boolean);
  }
  function visibleRecords() {
    if (activeTank) {
      const row = summaryRows.find(row => row.key === activeTank);
      return row ? [...new Set(tabs.map(tab => recordFor(tab.key, row, row.spec_type)).filter(Boolean))] : [];
    }
    return recordsForTab(activeTab);
  }
  function sampleCell(record, key) {
    const count = record.samples.length;
    if (key === 'U' || key === 'V') {
      return `<td><div class="sample-stack">${Array.from({length:count}, (_, i) => `<span class="autovalue" data-auto="${key}" data-index="${i}">${esc(number(record.details[i]?.[key === 'U' ? 'intermediate' : 'result']))}</span>`).join('')}</div></td>`;
    }
    return `<td><div class="sample-stack">${record.samples.map((sample, i) => `<input type="number" step="any" ${editable(record)?'':'disabled'} data-key="${key}" data-index="${i}" value="${esc(sample[key] ?? '')}" placeholder="S${i + 1}" aria-label="${esc(record.row.tank + ' ' + record.row.product + ' ' + (fields[record.tab]?.[key] || key) + ' sample ' + (i+1))}">`).join('')}</div></td>`;
  }
  function entryStandards(record) {
    return ['IQS','QS'].map(spec=>{
      const option=record.row.spec_options.find(option=>option.spec_type.toUpperCase()===spec);
      const rule=option?.rules?.[0];
      return `<div class="standard-line"><span class="standard-label">${spec}</span><span>${esc(rule?standard(rule):'N/A')}</span></div>`;
    }).join('');
  }
  function lockedBatch(row) {
    const key = row.key || rowKey(row);
    const summary = summaryRows.find(item => item.key === key);
    return `<div class="batch-readonly" data-batch-display="${esc(key)}" title="Batch number is read-only. Change it in the summary table."><svg viewBox="0 0 20 20" aria-hidden="true"><rect x="4" y="8" width="12" height="9" rx="2"/><path d="M6.5 8V6a3.5 3.5 0 0 1 7 0v2"/></svg><span>${esc(row.batch || '—')}</span></div><small>${esc(runLabel(row))}</small>${summary?.batchDirty ? '<small class="batch-unsaved">Unsaved — save batch in the summary</small>' : ''}`;
  }
  function entryRow(record, cols) {
    return `<tr data-record="${esc(record.key)}"><td>${esc(record.row.factory || context.plant)}</td><td class="entry-tank">${esc(record.row.tank)}</td><td class="entry-product">${esc(record.row.product)}<small>${esc(record.rule.method_name || '')}</small></td><td>${lockedBatch(record.row)}</td>${cols.map(key => sampleCell(record, key)).join('')}<td><span class="autovalue entry-result ${statusClass(record.disposition)}" data-entry-result>${esc(number(record.value))}</span></td><td class="entry-standard">${entryStandards(record)}</td><td><span data-entry-dispo class="dispo-badge ${statusClass(record.disposition)}">${esc(record.disposition)}</span><small class="row-save-state" data-row-state>${record.dirty ? 'Unsaved' : recordState(record)}</small></td></tr>`;
  }
  function renderModal() {
    if (activeTank) {renderTankModal(); return;}
    const grouped = new Map();
    const applicable = visibleRecords();
    applicable.forEach(record => {
      const cols = columnsFor(record), signature = cols.join(',');
      if (!grouped.has(signature)) grouped.set(signature, {cols, entries:[]});
      grouped.get(signature).entries.push(record);
    });
    q('#sInputModalBody').innerHTML = [...grouped.values()].map(({cols, entries}) => {
      const h = fields[canonical(activeTab)] || {};
      return `<section class="sinput-panel"><div class="sinput-table-scroll"><table class="sinput-table"><thead><tr><th rowspan="2" class="entry-factory">Factory</th><th rowspan="2" class="entry-tank">Tank</th><th rowspan="2" class="entry-product">Product</th><th rowspan="2" class="entry-batch">Batch</th><th colspan="${cols.length}">S input</th><th rowspan="2">Average</th><th rowspan="2" class="entry-standard">Standard<small>QS for reference</small></th><th rowspan="2">Dispo.<small>Based on IQS</small></th></tr><tr>${cols.map(key => `<th class="input-heading">${key}<small>${esc(h[key] || key)}</small></th>`).join('')}</tr></thead><tbody>${entries.map(record => entryRow(record, cols)).join('')}</tbody></table></div></section>`;
    }).join('') || '<p class="empty-message">No applicable tanks for the selected specification.</p>';
    q('#sInputSave').hidden = !applicable.some(editable);q('#sInputSave').disabled = !applicable.some(editable);
  }
  function renderTankModal() {
    const row = summaryRows.find(row => row.key === activeTank);
    const applicable = visibleRecords();
    q('#sInputModalBody').innerHTML = `
      <div class="tank-context">
        <div><span class="tank-context-label">Product</span><strong>${esc(row.product)}</strong>
          <span class="tank-context-spec">Factory specification: ${esc(row.factory_specification || 'ALL')}</span></div>
        <div class="tank-batch"><span class="tank-context-label">Batch number · Read only</span>${lockedBatch(row)}<small class="tank-batch-hint">${row.is_history||row.parent_batch_id?'Same batch as original test.':'Edit and save in the summary table.'}</small></div>
      </div>
      <p class="run-guidance">${row.is_history?'Read only. Open the latest row to enter readings.':row.is_read_only?'Saved by '+esc(row.batch_author||'another operator')+' · Read only':row.parent_batch_id?'All readings in this row can be edited.':''}</p><div class="tank-parameters" data-count="${applicable.length}">${applicable.map(tankParameterCard).join('')}</div>
      <p class="tank-entry-note">Disposition follows IQS. QS is shown for reference. Fill the samples you need, then save all changed parameters together.</p>`;
    q('#sInputSave').hidden = !applicable.some(editable);q('#sInputSave').disabled = !applicable.some(editable);
  }
  function tankParameterCard(record) {
    const cols = columnsFor(record), headings = fields[canonical(record.tab)] || {};
    const title = tabs.find(tab => tab.key === canonical(record.tab))?.short || record.tab;
    const method = record.rule.method_name || '';
    const sampleRows = record.samples.map((sample, i) => {
      const cells = cols.map(key => {
        if (key === 'U' || key === 'V') {
          const value = record.details[i]?.[key === 'U' ? 'intermediate' : 'result'];
          return `<td><span class="autovalue" data-auto="${key}" data-index="${i}">${esc(number(value))}</span></td>`;
        }
        return `<td><input type="number" step="any" ${editable(record)?'':'disabled'} data-key="${key}" data-index="${i}" value="${esc(sample[key] ?? '')}" placeholder="—" aria-label="${esc(title+' '+(headings[key] || key)+' sample '+(i+1))}"></td>`;
      }).join('');
      return `<tr><th scope="row" class="sample-label">S${i+1}</th>${cells}${record.tab==='PH'?'<td class="reading-space reading-space-start" aria-hidden="true"></td><td class="reading-space" aria-hidden="true"></td>':''}</tr>`;
    }).join('');
    const state = record.error || (record.dirty ? 'Unsaved changes' : recordState(record));
    return `<section class="tank-parameter-card" data-record="${esc(record.key)}" aria-label="${esc(title)} readings">
      <div class="tank-card-head">
        <div><h3>${esc(title)}</h3>${record.carryForward?`<span class="tank-method carried-label">Copied PASS</span>`:''}${method && method.toLowerCase() !== title.toLowerCase() ? `<span class="tank-method">${esc(method)}</span>` : ''}</div>
        <span data-entry-dispo class="dispo-badge ${statusClass(record.disposition)}">${esc(record.disposition)}</span>
      </div>
      <div class="tank-readings-scroll"><table class="tank-readings-table">${record.tab==='PH'?'<colgroup><col style="width:52px"><col><col><col><col></colgroup>':''}
        <thead><tr><th scope="col" class="sample-label">Sample</th>${cols.map(key => `<th scope="col">${key}<small>${esc(headings[key] || key)}</small></th>`).join('')}${record.tab==='PH'?'<th class="reading-space reading-space-start" aria-hidden="true"></th><th class="reading-space" aria-hidden="true"></th>':''}</tr></thead>
        <tbody>${sampleRows}</tbody>
      </table></div>
      <div class="tank-card-foot">
        <div class="tank-average"><span>Average</span><span class="autovalue entry-result ${statusClass(record.disposition)}" data-entry-result>${esc(number(record.value))}</span></div>
        <div class="entry-standard">${entryStandards(record)}</div>
      </div>
      <small class="row-save-state${record.error ? ' error' : ''}" data-row-state>${esc(state)}</small>
    </section>`;
  }
  function openModal(tab, trigger) {
    if(!canEdit)return;
    if (!context || saving) return;
    closeComparison();
    activeTab = tab;
    activeTank = '';
    returnFocus = trigger;
    const applicable = visibleRecords();
    q('#sInputModalTitle').textContent = `${tabs.find(t => t.key === tab)?.short || tab} — Tank Readings`;
    q('#sInputModalMeta').textContent = `${context.plant} · ${context.date} · ${context.sampling_time === '08:00:00' ? 'Day' : 'Night'} · ${applicable.length} running tanks · Disposition uses IQS; QS is reference only`;
    q('#sInputState').textContent = 'Enter readings, then Save All. Batch numbers are edited in the summary table.';
    showEntryModal(applicable);
  }
  function openTankModal(key, trigger) {
    if(!canEdit)return;
    if (!context || saving) return;
    const row = summaryRows.find(row => row.key === key);
    if (!row) return;
    closeComparison();
    activeTank = key;
    activeTab = '';
    returnFocus = trigger;
    const applicable = visibleRecords();
    q('#sInputModalTitle').textContent = `${row.tank} — ${runLabel(row)||'All Parameters'}`;
    q('#sInputModalMeta').textContent = `${row.factory || context.plant} · ${context.date} · ${context.sampling_time === '08:00:00' ? 'Day' : 'Night'} · ${applicable.length} applicable parameters`;
    q('#sInputState').textContent = row.is_history?'History · Read only':row.is_read_only?'Read only':row.parent_batch_id?'Edit readings, then Save All.':'Enter readings, then Save All.';
    showEntryModal(applicable);
  }
  function showEntryModal(applicable) {
    q('.sinput-modal').classList.toggle('is-tank-mode', Boolean(activeTank));
    q('#sInputPrint').hidden=!activeTank;
    q('#sInputState').classList.remove('error');
    renderModal();
    q('#sInputModal').classList.add('open');
    q('#sInputModal').setAttribute('aria-hidden','false');
    q('#mainContent').inert = true;
    document.body.classList.add('sinput-open');
    q('#sInputModalBody input[data-key]:not(:disabled)')?.focus();
    if (!q('#sInputModalBody input')) q('#sInputClose').focus();
    // Populate calculated sample outputs from the saved raw readings without changing saved results.
    applicable.forEach(record => {
      if (record.samples.some(sample => Object.values(sample).some(v => v !== ''))) {
        preview(record).catch(() => {});
      }
    });
  }
  function closeModal() {
    if (saving) return;
    q('#sInputModal').classList.remove('open');
    q('#sInputModal').setAttribute('aria-hidden','true');
    q('#mainContent').inert = false;
    document.body.classList.remove('sinput-open');
    const tankTrigger = activeTank && [...q('#gridBody').querySelectorAll('[data-open-tank]')].find(button => button.dataset.openTank === activeTank);
    (tankTrigger || (returnFocus?.isConnected ? returnFocus : null))?.focus();
    activeTank = ''; activeTab = '';
  }
  function entryElement(record) {
    return [...document.querySelectorAll('#sInputModalBody [data-record],#followupReadings [data-record]')].find(tr => tr.dataset.record === record.key);
  }
  function refreshEntry(record) {
    const tr = entryElement(record);
    if (!tr) return;
    const result=tr.querySelector('[data-entry-result]');
    result.textContent=number(record.value);
    result.className='autovalue entry-result '+statusClass(record.disposition);
    const badge = tr.querySelector('[data-entry-dispo]');
    badge.textContent = record.disposition;
    badge.className = 'dispo-badge ' + statusClass(record.disposition);
    const state = tr.querySelector('[data-row-state]');
    state.textContent = record.error || (record.dirty ? 'Unsaved' : recordState(record));
    state.classList.toggle('error', Boolean(record.error));
    tr.querySelectorAll('[data-auto]').forEach(box => {
      box.textContent = number(record.details[Number(box.dataset.index)]?.[box.dataset.auto === 'U' ? 'intermediate' : 'result']);
    });
  }
  async function calculate(record) {
    const samples = record.samples.map(sample => ({...sample}));
    return Promise.all(samples.map(async sample => {
      if (!Object.values(sample).some(v => v !== '' && v !== null && v !== undefined)) return null;
      if (!record.rule.method_key) {
        if (sample.A === '' || sample.A === undefined) throw new Error('Enter the required reading.');
        return {result:Number(sample.A), intermediate:null};
      }
      return (await request('api/calculate.php', {method_key:Number(record.rule.method_key), inputs:sample})).calculation;
    }));
  }
  function applyCalculation(record, details) {
    record.details = details;
    const values = details.filter(d => d && d.result !== null && d.result !== '').map(d => Number(d.result));
    record.value = values.length ? values.reduce((sum,v) => sum+v,0) / values.length : null;
    record.disposition = disposition(record.rule, record.value);
    record.error = '';
  }
  async function preview(record) {
    const version = record.version;
    try {
      const details = await calculate(record);
      if (version !== record.version || (records.get(record.key) !== record && !followupDraft?.records.includes(record))) return;
      // Saved server results remain authoritative until the user changes an input.
      if (record.dirty) applyCalculation(record, details); else record.details = details;
    } catch (error) {
      if (version !== record.version) return;
      if (record.dirty) {record.value = null; record.details = []; record.disposition = 'PENDING'; record.error = 'Complete the required readings.';}
    }
    refreshEntry(record);
    if(!record.isFollowup)refreshSummary(record);
  }
  async function saveRecord(record) {
    if(!editable(record))throw new Error('This result is read only.');
    const details = await calculate(record);
    const samples = details.filter(Boolean).map(d => d.result);
    if (!samples.length) throw new Error('Enter at least one complete sample before saving.');
    applyCalculation(record, details);
    const saved = await request('api/inspection.php', {
      action:'save', source:'INPROCESS_GRID', id:record.id, inspection_type:'INPROCESS', entity_type:'PRODUCT',
      spec_profile_id:record.profileId,
      spec_type:record.spec, entity_id:record.row.product_id, batch_no:record.row.batch.trim(), run_id:record.row.run_id||0,
      plant:context.plant, factory:'', sampling_location:record.row.tank,
      manufacturing_date:context.date, inspection_at:context.date+' '+context.sampling_time,
      remarks:'Saved from in-process parameter entry: '+record.tab,
      results:[{rule_id:Number(record.rule.id),samples,raw_samples:record.samples}]
    });
    record.id = saved.id;
    record.value = saved.result_value ?? saved.result_text ?? null;
    record.disposition = resultStatus(saved.result_disposition || saved.overall_disposition);
    record.modified = saved.modified_display || new Date().toLocaleString('en-GB', {dateStyle:'short',timeStyle:'short'});
    record.modifiedAt = saved.modified ? new Date(saved.modified.replace(' ', 'T')).getTime() : Date.now();
    record.author = saved.modified_by_name || user;
    record.carryForward = null;
    record.dirty = false;
    record.error = '';
    const row = summaryRows.find(row => rowKey(row) === rowKey(record.row));
    if (row) {
      row.run_id=saved.run_id;for(const sibling of records.values())if(rowKey(sibling.row)===row.key)sibling.row.run_id=saved.run_id;
      row.batch = record.row.batch.trim(); row.savedBatch = row.batch; row.batchDirty = false;
      for (const sibling of records.values()) {
        if (rowKey(sibling.row) === rowKey(row)) sibling.row.batch = row.batch;
      }
      document.querySelectorAll('[data-batch-row]').forEach(input => {if (input.dataset.batchRow === row.key) input.value = row.batch;});
    }
    context.presetSaved=true;
    refreshEntry(record);
    refreshSummary(record);
  }
  async function saveAll() {
    if(!canEdit)return;
    if (saving) return;
    if (batchSaves.size) {q('#sInputState').textContent = 'Please wait for the batch save to finish.'; return;}
    const visible = visibleRecords();
    const changed = visible.filter(record => editable(record) && record.dirty);
    if (!changed.length) {q('#sInputState').textContent = 'No changed readings to save.'; return;}
    const unsavedBatch = summaryRows.find(row => row.batchDirty && changed.some(record => record.row.location_id === row.location_id && record.row.product_id === row.product_id));
    if (unsavedBatch) {
      q('#sInputState').textContent = `Save the batch number for ${unsavedBatch.tank} in the summary table first. Your entered readings are kept here.`;
      q('#sInputState').classList.add('error');
      return;
    }
    saving = true;
    const controls = [...q('#sInputModal').querySelectorAll('button,input')];
    const disabledBefore=new Map(controls.map(control=>[control,control.disabled]));
    controls.forEach(control => {control.disabled = true;});
    q('#sInputState').classList.remove('error');
    let count = 0, errors = [];
    try {
      for (const record of changed) {
        clearTimeout(timers.get(record.key));
        ++record.version;
        q('#sInputState').textContent = `Saving ${count + errors.length + 1} of ${changed.length}…`;
        try {await saveRecord(record); ++count;}
        catch (error) {
          record.error = error.message;
          errors.push(`${record.row.tank} / ${record.tab}: ${error.message}`);
          refreshEntry(record);
        }
      }
      const message = errors.length ? `${count} reading row(s) saved; ${errors.length} could not be saved. ${errors[0]}` : `${count} row(s) saved. Results remain available after reload and sign-in.`;
      q('#sInputState').textContent = message;
      q('#sInputState').classList.toggle('error', Boolean(errors.length));
      setState(message, Boolean(errors.length));
    } finally {
      saving = false;
      controls.forEach(control => {control.disabled = disabledBefore.get(control);});
      if(!errors.length&&count){closeModal();QACHEM_UI.notify('Readings saved.');}
      else if(errors.length){q('#sInputState').textContent+=' Please retry the failed rows.';}
    }
  }
  function closeComparison() {
    ++compareVersion;
    q('#specCompare').hidden=true;
  }
  function positionComparison(x,y) {
    const panel=q('#specCompare');
    panel.style.left=Math.max(8,Math.min(x,innerWidth-panel.offsetWidth-8))+'px';
    panel.style.top=Math.max(8,Math.min(y,innerHeight-panel.offsetHeight-8))+'px';
  }
  async function compareResult(button,event) {
    const row=summaryRows.find(row=>row.key===button.dataset.compareRow);
    const tab=button.dataset.compareTab;
    const record=row&&recordFor(tab,row,row.spec_type);
    if(!record)return;
    const version=++compareVersion;
    q('#compareTitle').textContent=`${row.tank} · ${tabs.find(t=>t.key===tab)?.short || tab}`;
    q('#compareBody').innerHTML='<div class="compare-loading"><span class="compare-spinner" aria-hidden="true"></span>Loading IQS / QS standards…</div>';
    q('#specCompare').hidden=false;
    const rect=button.getBoundingClientRect(),x=event.clientX||rect.left,y=event.clientY||rect.bottom;
    positionComparison(x,y);
    q('#specCompare').focus({preventScroll:true});
    try {
      const data=await request('api/inprocess_compare.php',{
        plant:context.plant,entity_id:record.row.product_id,tab,inspection_id:record.id,rule_id:record.rule.id,raw_samples:record.samples,value:tab==='VTQ'?torqueValue(record):record.value
      });
      if(version!==compareVersion)return;
      q('#compareBody').innerHTML=`<p class="compare-meta">${esc(row.product)} · ${record.id?'Record #'+esc(record.id):'No saved record'}${record.dirty?' · Unsaved preview':''}</p><table><thead><tr><th>Spec</th><th>Rule ID</th><th>Result</th><th>Lower</th><th>Upper</th><th>Dispo.</th></tr></thead><tbody>${data.comparisons.map(result=>`<tr class="comparison-${statusClass(result.disposition)}" title="${esc(result.display)}"><td>${esc(result.spec_type)}</td><td>${esc(result.rule_id??'—')}</td><td>${esc(number(result.value))}</td><td>${esc(number(result.low))}</td><td>${esc(number(result.high))}</td><td><span class="dispo-badge ${statusClass(result.disposition)}">${esc(['INFO','N/A'].includes(result.disposition)?result.disposition:resultStatus(result.disposition))}</span></td></tr>`).join('')}</tbody></table><p class="compare-note">The same readings are checked against both standards. ${tab==='VTQ'?'Torque has no controlled limit in the current specifications.':esc(record.spec)+' is the standard set in Line Preset.'}</p>`;
      positionComparison(x,y);
    }catch(error){
      if(version===compareVersion)q('#compareBody').innerHTML=`<p class="error">${esc(error.message)}</p>`;
    }
  }
  q('#gridBody').addEventListener('contextmenu',event=>{
    const button=event.target.closest('[data-compare-row]');
    if(!button)return;
    event.preventDefault();compareResult(button,event);
  });
  q('#gridBody').addEventListener('input', event => {
    if (event.target.matches('[data-batch-row]')) changeBatch(event.target);
  });
  q('#gridBody').addEventListener('click',event=>{
    const followup=event.target.closest('[data-followup]');
    if(followup){openFollowupMenu(followup);return;}
    const save=event.target.closest('[data-save-batch]');
    if(save){saveBatch(save.dataset.saveBatch);return;}
    const tank=event.target.closest('[data-open-tank]');
    if(tank){openTankModal(tank.dataset.openTank,tank);return;}
    const button=event.target.closest('[data-compare-row]');
    if(button)compareResult(button,event);
  });
  let followupDraft=null, followupMenuRow='', followupVersion=0, followupTrigger=null;
  const iconPaths={RETEST:'<path d="M16 7a6 6 0 1 0 0 6M16 3v4h-4"/>',REWORK:'<path d="m12 5 3-3a5 5 0 0 0-6 6l-6 6a2 2 0 0 0 3 3l6-6a5 5 0 0 0 6-6l-3 3z"/>',NEW_SAMPLE:'<path d="M7 2h6M8 2v6l-4 7a2 2 0 0 0 2 3h8a2 2 0 0 0 2-3l-4-7V2M6 12h8"/>'};
  function typeIcon(type){return `<svg viewBox="0 0 20 20" aria-hidden="true">${iconPaths[type]||iconPaths.NEW_SAMPLE}</svg>`;}
  function closeFollowupMenu(){const menu=q('#followupMenu');if(menu.contains(document.activeElement)&&followupTrigger?.isConnected)followupTrigger.focus();menu.hidden=true;followupMenuRow='';}
  function openFollowupMenu(button){
    if(saving||loading||!canEdit)return;
    closeComparison();followupMenuRow=button.dataset.followup;followupTrigger=button;
    const menu=q('#followupMenu');menu.innerHTML=Object.entries(sampleTypes).map(([type,label])=>`<button type="button" role="menuitem" data-start-followup="${type}">${typeIcon(type)}${esc(label)}</button>`).join('');
    menu.hidden=false;const rect=button.getBoundingClientRect();menu.style.left=Math.max(8,Math.min(rect.left,innerWidth-menu.offsetWidth-8))+'px';menu.style.top=Math.max(8,Math.min(rect.bottom+5,innerHeight-menu.offsetHeight-8))+'px';menu.querySelector('button').focus();
  }
  function selectFollowupType(type){
    if(!followupDraft)return;
    const changed=followupDraft.type!==type;followupDraft.type=type;
    if(type==='NEW_SAMPLE'){
      followupDraft.scope='ALL';
      if(changed)followupDraft.records.forEach(record=>{clearTimeout(timers.get(record.key));++record.version;record.samples=record.samples.map(()=>({}));record.value=null;record.details=[];record.disposition='PENDING';record.dirty=false;record.error='';record.carryForward=null;});
    }else if(changed)followupDraft.scope='FAILED';
    q('#followupTitle').textContent=sampleTypes[type];
    const info=q('#followupTypeInfo');
    q('#followupTypes').innerHTML=Object.entries(sampleTypes).map(([value,label])=>`<button type="button" data-followup-type="${value}" aria-label="${esc(label)}" data-tooltip="${esc(label+(type===value?' selected':''))}" aria-pressed="${type===value}">${typeIcon(value)}</button>`).join('');
    q('#followupTypes').append(info);
    renderFollowupReadings();
  }
  function renderFollowupReadings(){
    const draft=followupDraft;if(!draft)return;
    const all=draft.type==='NEW_SAMPLE'||draft.scope==='ALL';
    q('#followupScope').hidden=draft.type==='NEW_SAMPLE';
    q('#followupScope').querySelectorAll('[data-followup-scope]').forEach(button=>button.setAttribute('aria-pressed',String(button.dataset.followupScope===draft.scope)));
    q('#followupExplanation').textContent=draft.type==='NEW_SAMPLE'?'New sample: enter fresh readings for every parameter.':all?'Enter new readings for every parameter, including previous PASS results.':'Keep previous PASS results; enter the remaining readings.';
    if(!draft.sourceToken)return;
    draft.records.forEach(record=>{
      const carried=!all&&record.previousStatus==='PASS';
      if(carried){clearTimeout(timers.get(record.key));++record.version;record.dirty=false;record.details=[];record.carryForward={source_run_no:draft.row.run_no||1};record.value=record.previousValue;record.disposition='PASS';}
      else if(record.carryForward){clearTimeout(timers.get(record.key));++record.version;record.carryForward=null;record.value=null;record.disposition='PENDING';record.samples=record.samples.map(()=>({}));record.details=[];}
    });
    const passed=draft.records.filter(record=>record.carryForward),remaining=draft.records.filter(record=>!record.carryForward);
    q('#followupReadings').innerHTML=(passed.length?`<section class="carried-results"><h3>Previous PASS <span>Carried forward</span></h3><table><tbody>${passed.map(record=>`<tr><th>${esc(record.rule.parameter_name)}</th><td>${esc(number(record.previousValue))} <small>${esc(record.rule.unit||'')}</small></td><td><span class="dispo-badge pass">PASS</span></td></tr>`).join('')}</tbody></table></section>`:'')+
      remaining.map(record=>`<div class="followup-reading"><div class="previous-reading">Previous: <strong>${esc(number(record.previousValue))}</strong><span class="dispo-badge ${statusClass(record.previousStatus)}">${esc(record.previousStatus)}</span></div>${tankParameterCard(record)}</div>`).join('');
    q('#followupCreate').disabled=!remaining.length;
    q('#followupError').textContent=remaining.length?'':'All parameters passed. Choose All parameters to test again.';
  }
  async function openFollowup(key,type){
    if(saving||loading||!canEdit)return;
    const row=summaryRows.find(row=>row.key===key);if(!row||!row.run_id||row.is_history)return;
    const current=[...records.values()].filter(record=>rowKey(record.row)===key&&record.spec===row.spec_type);
    if(row.batchDirty||current.some(record=>record.dirty)){setState('Save your changes first.',true);return;}
    const version=++followupVersion;
    followupDraft={row,type,scope:type==='NEW_SAMPLE'?'ALL':'FAILED',records:[],requestId:window.crypto?.randomUUID?.()||Date.now().toString(36)+'-'+Math.random().toString(36).slice(2)+'-'+Math.random().toString(36).slice(2)};
    q('#followupProductIcon').innerHTML=typeIcon('NEW_SAMPLE');
    q('#followupContextDetails').innerHTML=`<strong>${esc(row.product)}</strong><dl><div><dt>Factory</dt><dd>${esc(row.factory||context.plant)}</dd></div><div><dt>Tank</dt><dd>${esc(row.tank)}</dd></div><div><dt>Batch</dt><dd>${esc(row.batch)}</dd></div></dl>`;
    q('#followupLegend').innerHTML=Object.entries(sampleTypes).map(([value,label])=>`<span>${typeIcon(value)}<span><strong>${esc(label)}</strong><small>${esc({RETEST:'Repeat the failed tests',REWORK:'Test after reprocessing',NEW_SAMPLE:'Test a fresh sample'}[value])}</small></span></span>`).join('');
    selectFollowupType(type);
    q('#followupReadings').innerHTML='<p class="drawer-loading">Loading previous results...</p>';q('#followupError').textContent='';q('#followupCreate').disabled=true;
    q('#followupDialog').showModal();
    try{
      const source=await request('api/inprocess_followup.php',{action:'preview',run_id:row.run_id});
      if(version!==followupVersion||!followupDraft)return;
      followupDraft.sourceToken=source.source_token;
      followupDraft.records=current.flatMap(record=>{
        const previous=source.records.find(r=>r.spec_type===record.spec&&r.parameter_code===record.rule.parameter_code);if(!previous)return [];
        const passed=previous.disposition==='PASS';
        return [{...record,key:'draft:'+record.key,isFollowup:true,row:{...record.row,is_history:false,is_read_only:false},sourceId:Number(previous.id),rule:{...record.rule,id:Number(previous.spec_rule_id)},previousValue:previous.result_value??previous.result_text,previousStatus:resultStatus(previous.disposition),
          samples:Array.from({length:record.samples.length},()=>({})),carryForward:passed?{source_run_no:row.run_no||1}:null,value:passed?previous.result_value:null,disposition:passed?'PASS':'PENDING',dirty:false,id:null,error:'',version:0,details:[]}];
      });
      renderFollowupReadings();
      q('#followupReadings input:not(:disabled)')?.focus();
    }catch(error){if(version===followupVersion){q('#followupReadings').innerHTML='';q('#followupError').textContent=error.message+' Close and try again.';}}
  }
  function closeFollowup(){if(saving)return;++followupVersion;q('#followupDialog').close();followupDraft=null;followupTrigger?.isConnected&&followupTrigger.focus();}
  q('#followupMenu').addEventListener('click',event=>{const button=event.target.closest('[data-start-followup]');if(!button)return;const key=followupMenuRow;closeFollowupMenu();openFollowup(key,button.dataset.startFollowup);});
  q('#followupTypes').addEventListener('click',event=>{const button=event.target.closest('[data-followup-type]');if(button&&!saving)selectFollowupType(button.dataset.followupType);});
  q('#followupScope').addEventListener('click',event=>{const button=event.target.closest('[data-followup-scope]');if(button&&!saving&&followupDraft){followupDraft.scope=button.dataset.followupScope;renderFollowupReadings();}});
  q('#followupCancel').addEventListener('click',closeFollowup);q('#followupClose').addEventListener('click',closeFollowup);
  q('#followupDialog').addEventListener('cancel',event=>{event.preventDefault();closeFollowup();});
  document.addEventListener('pointerdown',event=>{if(!event.target.closest('#followupMenu,[data-followup]'))closeFollowupMenu();});
  document.addEventListener('keydown',event=>{if(event.key==='Escape')closeFollowupMenu();});
  q('.grid-scroll').addEventListener('scroll',closeFollowupMenu);window.addEventListener('resize',closeFollowupMenu);
  q('#followupReadings').addEventListener('input',event=>{
    const input=event.target.closest('input[data-key]');if(!input||saving||!followupDraft)return;
    const record=followupDraft.records.find(r=>r.key===input.closest('[data-record]').dataset.record);if(!record||record.carryForward)return;
    record.samples[Number(input.dataset.index)][input.dataset.key]=input.value;record.dirty=true;record.error='';++record.version;refreshEntry(record);
    clearTimeout(timers.get(record.key));timers.set(record.key,setTimeout(()=>preview(record),250));
  });
  q('#followupForm').addEventListener('submit',async event=>{
    event.preventDefault();if(!followupDraft||saving||!followupDraft.sourceToken)return;
    const draft=followupDraft,remaining=draft.records.filter(record=>!record.carryForward);
    saving=true;const controls=[...q('#followupForm').querySelectorAll('button,input')],disabledBefore=new Map(controls.map(control=>[control,control.disabled]));controls.forEach(control=>control.disabled=true);q('#followupError').classList.remove('error');q('#followupError').textContent='Saving...';
    let saved=null;
    try{
      for(const record of remaining){clearTimeout(timers.get(record.key));++record.version;let details;try{details=await calculate(record);}catch(error){throw new Error(record.rule.parameter_name+': '+error.message);}if(!details.some(Boolean))throw new Error('Enter readings for '+record.rule.parameter_name+'.');applyCalculation(record,details);refreshEntry(record);}
      saved=await request('api/inprocess_followup.php',{action:'save',run_id:draft.row.run_id,sample_type:draft.type,scope:draft.scope,source_token:draft.sourceToken,request_id:draft.requestId,readings:remaining.map(record=>({source_inspection_id:record.sourceId,rule_id:Number(record.rule.id),raw_samples:record.samples}))});
    }catch(error){q('#followupError').textContent=error.message+' Please retry.';q('#followupError').classList.add('error');}
    finally{saving=false;controls.forEach(control=>control.disabled=disabledBefore.get(control));}
    if(!saved)return;
    const label=sampleTypes[draft.type];closeFollowup();await loadGrid();QACHEM_UI.notify(label+' saved.');
  });
  q('#compareClose').addEventListener('click',closeComparison);
  document.addEventListener('pointerdown',event=>{
    if(!event.target.closest('#specCompare,[data-compare-row]'))closeComparison();
  });
  q('.grid-scroll').addEventListener('scroll',closeComparison);
  window.addEventListener('resize',closeComparison);
  q('#loadGridBtn').addEventListener('click', loadGrid);
  ['#inspectionDate','#samplingTime','#plant'].forEach(selector => q(selector).addEventListener('change', clearGrid));
  q('#plant').addEventListener('change',rememberPlant);
  q('#gridHead').addEventListener('click', event => {
    const tab = event.target.closest('[data-tab]');
    if (tab && !tab.disabled) openModal(tab.dataset.tab, tab);
  });
  q('#sInputModalBody').addEventListener('input', event => {
    const input = event.target.closest('input[data-key]');
    if (!input || saving) return;
    const record = records.get(input.closest('[data-record]').dataset.record);
    if(!record||!editable(record))return;
    record.samples[Number(input.dataset.index)][input.dataset.key] = input.value;
    record.dirty = true;
    record.error = '';
    ++record.version;
    refreshEntry(record);
    clearTimeout(timers.get(record.key));
    timers.set(record.key, setTimeout(() => preview(record), 250));
  });
  q('#sInputPrint').addEventListener('click',async()=>{
    if(!activeTank||saving||!canEdit)return;
    const reportRecords=visibleRecords(),row={...summaryRows.find(row=>row.key===activeTank)},reportContext={...context};
    const win=window.open('about:blank','_blank');
    if(!win){q('#sInputState').textContent='Allow popups to open the report.';return;}
    win.document.body.textContent='Preparing report...';
    const button=q('#sInputPrint');button.disabled=true;
    try{
      await Promise.all(reportRecords.map(preview));
      if(win.closed)return;
      const parameters=reportRecords.map(record=>{
        const cols=columnsFor(record),headings=fields[canonical(record.tab)]||{};
        const limits=spec=>{const option=record.row.spec_options.find(option=>option.spec_type.toUpperCase()===spec);return option?.rules?.[0]?standard(option.rules[0]):'N/A';};
        return {
          code:record.tab,title:tabs.find(tab=>tab.key===canonical(record.tab))?.short||record.tab,
          method:record.rule.method_name||'',unit:record.rule.unit||'',value:number(record.value),
          disposition:record.disposition,unsaved:record.dirty||!record.id,error:record.error,
          iqs:limits('IQS'),qs:limits('QS'),
          columns:cols.map(key=>({key,label:headings[key]||key,calculated:key==='U'||key==='V'})),
          samples:record.samples.map((sample,i)=>cols.map(key=>{
            if(key==='U'||key==='V')return number(record.details[i]?.[key==='U'?'intermediate':'result']);
            const value=sample[key];return value===undefined||value===null||value===''?'—':String(value);
          }))
        };
      });
      const html=window.buildInprocessReport({row,context:reportContext,parameters,user,
        stylesheet:new URL(window.QACHEM_INPROCESS.reportCss,location.href).href,
        logo:new URL('TGLOGO.png',location.href).href});
      win.document.open();win.document.write(html);win.document.close();win.focus();
      q('#sInputState').textContent='Report opened.';
    }catch(error){win.close();q('#sInputState').textContent=error.message;}
    finally{button.disabled=false;}
  });
  q('#sInputSave').addEventListener('click', saveAll);
  q('#sInputClose').addEventListener('click', closeModal);
  q('#sInputModal').addEventListener('click', event => {if (event.target.id === 'sInputModal') closeModal();});
  document.addEventListener('keydown', event => {
    if(event.key==='Escape')closeComparison();
    if (!q('#sInputModal').classList.contains('open')) return;
    if (event.key === 'Escape') closeModal();
    if (event.key !== 'Tab') return;
    const focusable = [...q('#sInputModal').querySelectorAll('button:not(:disabled),input:not(:disabled),[tabindex="0"]')];
    const first = focusable[0], last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) {event.preventDefault(); last?.focus();}
    else if (!event.shiftKey && document.activeElement === last) {event.preventDefault(); first?.focus();}
  });
  q('#samplingTime').value=defaultSamplingTime();
  try {
    const plant=localStorage.getItem(plantPreferenceKey);
    if(validPlant(plant))q('#plant').value=plant;
    else if(plant!==null)localStorage.removeItem(plantPreferenceKey);
  } catch (_) { /* No saved preference: let the user select a plant. */ }
  clearGrid();
  const params=new URLSearchParams(location.search);
  [['plant','#plant'],['date','#inspectionDate'],['sampling_time','#samplingTime']].forEach(([key,selector])=>{
    const value=params.get(key);
    if(!value)return;
    if(key==='plant'&&!validPlant(value))return;
    if(key==='sampling_time'&&!['08:00:00','20:00:00'].includes(value))return;
    q(selector).value=value;
  });
  updatePresetLinks();
  if(q('#plant').value&&q('#inspectionDate').value&&q('#samplingTime').value) {
    loadGrid();
  }
})();
