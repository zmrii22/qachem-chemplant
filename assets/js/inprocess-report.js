/* Render a printable snapshot without changing or saving the source readings. */
window.buildInprocessReport = ({row,context,parameters,user,stylesheet,logo}) => {
  const esc=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const status=value=>['PASS','FAIL'].includes(value)?value:'PENDING';
  const date=context.date?new Date(context.date+'T12:00:00').toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'}):'—';
  const shift=context.sampling_time==='20:00:00'?'Night':'Day';
  const sampleType=(Number(row.run_no||1)===1?'Original':({NEW_SAMPLE:'New Sample',RETEST:'Retest',REWORK:'Rework'})[row.sample_type||'NEW_SAMPLE']||'New Sample');
  
  const draft=parameters.some(parameter=>parameter.unsaved);
  const counts=parameters.reduce((totals,p)=>{totals[status(p.disposition)]++;return totals;},{PASS:0,FAIL:0,PENDING:0});
  const pending=counts.PENDING;
  const overall=counts.FAIL?'FAIL':parameters.length&&counts.PASS===parameters.length?'PASS':'PENDING';
  const printIcon='<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 8V3h10v5M7 17H4V9h16v8h-3M7 14h10v7H7zM17 11h.01"/></svg>';
  const sections=parameters.map((p,index)=>{
    const state=status(p.disposition),blank=p.code==='PH'?'<td colspan="2" class="reading-space" aria-hidden="true"></td>':'';
    const rows=p.samples.map((sample,i)=>`<tr><th scope="row">S${i+1}</th>${sample.map((value,j)=>`<td class="${p.columns[j].calculated?'calculated':''}">${esc(value)}</td>`).join('')}${blank}</tr>`).join('');
    return `<section class="report-parameter state-${state.toLowerCase()}" aria-labelledby="parameter-${index}">
      <header class="parameter-heading"><div class="parameter-name"><h2 id="parameter-${index}">${esc(p.title)}</h2>${p.method&&p.method.toLowerCase()!==p.title.toLowerCase()?`<p>${esc(p.method)}</p>`:''}</div><span class="result-badge ${state.toLowerCase()}">${state}</span></header>
      <div class="parameter-body"><div class="report-samples" tabindex="0" role="region" aria-label="${esc(p.title)} samples">${p.columns.length>2?'<span class="sample-scroll-hint">Scroll to view all columns</span>':''}<table class="sample-table"><colgroup><col class="sample-column">${Array.from({length:p.code==='PH'?4:p.columns.length},()=>'<col>').join('')}</colgroup><thead><tr><th scope="col">Sample</th>${p.columns.map(column=>`<th scope="col"><span class="column-code">${esc(column.key)}</span><span>${esc(column.label)}</span></th>`).join('')}${p.code==='PH'?'<th colspan="2" class="reading-space" aria-hidden="true"></th>':''}</tr></thead><tbody>${rows}</tbody></table></div>
      <aside class="parameter-result"><div class="result-caption">Average result</div><div class="result-value">${esc(p.value!==''&&p.value!==null&&Number.isFinite(Number(p.value))?Number(p.value).toFixed(2):p.value)}${p.unit?`<small>${esc(p.unit)}</small>`:''}</div><dl class="report-standards"><div><dt>IQS</dt><dd>${esc(p.iqs)}</dd></div><div><dt>QS</dt><dd>${esc(p.qs)}</dd></div></dl>${p.error?`<p class="reading-note" role="alert">${esc(p.error)}</p>`:''}</aside></div>
    </section>`;
  }).join('');
  return `<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>${esc('Inprocess - '+row.tank+' - '+(row.batch||'No batch')+' - '+context.date)}</title><link rel="stylesheet" href="${esc(stylesheet)}"></head><body>
    <div class="report-toolbar"><div class="toolbar-inner"><strong>Report preview</strong><div class="toolbar-actions"><button type="button" class="close-report" onclick="window.close()">Close</button><button type="button" class="print-report" onclick="window.print()">${printIcon}Print / Save PDF</button></div></div></div>
    <main class="report-sheet"><header class="report-brand"><img src="${esc(logo)}" alt="Top Glove" width="150" height="48"><div class="report-heading"><p>TOP GLOVE CHEMICAL SDN. BHD.</p><h1>Inprocess analysis</h1></div></header>
      <section class="report-product" aria-label="Product and batch"><div><span class="meta-label">Product</span><h2>${esc(row.product)}</h2></div><div class="product-identifiers">${Number(row.run_no||1)>1?`<div><span class="meta-label">Test type</span><strong>${esc(sampleType)}</strong></div>`:''}<div><span class="meta-label">Tank / Reactor</span><strong>${esc(row.tank)}</strong></div><div><span class="meta-label">Batch number</span><strong>${esc(row.batch||'—')}</strong></div></div></section>
      <dl class="report-context"><div><dt>Factory</dt><dd>${esc(row.factory||context.plant)}</dd></div><div><dt>Production date</dt><dd>${esc(date)}</dd></div><div><dt>Sampling time</dt><dd>${shift}<small>${esc(context.sampling_time?.slice(0,5)||'')}</small></dd></div><div><dt>Factory specification</dt><dd>${esc(row.factory_specification||'ALL')}</dd></div></dl>
      <div class="report-overview"><div class="overall-disposition"><span>Overall Disposition</span><strong class="result-badge ${overall.toLowerCase()}" data-overall-disposition>${overall}</strong></div><div class="overview-count"><strong>${parameters.length}</strong> parameters</div><div class="overview-results"><span class="pass"><i></i>${counts.PASS} pass</span><span class="fail"><i></i>${counts.FAIL} fail</span><span class="pending"><i></i>${pending} pending</span></div><span class="report-source ${draft?'draft':'saved'}">${draft?'Draft · unsaved readings':'Saved readings'}</span></div>
      <div class="report-parameters">${sections}</div>
      <footer class="report-footer"><div><strong>Printed by ${esc(user)}</strong><span>${esc(new Date().toLocaleString('en-GB'))}</span></div><p>Disposition follows IQS.<br>QS is shown for reference.</p></footer>
    </main></body></html>`;
};
