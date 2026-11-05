/* Incoming records and the selector for the supplied standalone forms. */
(() => {
  const q=selector=>document.querySelector(selector);
  const esc=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const averageInfo=q('#incomingAverageInfo'),averageHelp=q('#incomingAverageHelp');
  if(averageInfo&&averageHelp){
    function positionHelp(){
      if(averageHelp.hidden)return;
      const anchor=averageInfo.getBoundingClientRect(),box=averageHelp.getBoundingClientRect();
      const left=Math.max(12,Math.min(anchor.right-box.width,window.innerWidth-box.width-12));
      const top=anchor.bottom+8+box.height<=window.innerHeight-12?anchor.bottom+8:Math.max(12,anchor.top-box.height-8);
      averageHelp.style.left=left+'px';averageHelp.style.top=top+'px';
    }
    function showHelp(){averageHelp.hidden=false;averageInfo.setAttribute('aria-expanded','true');positionHelp();}
    function hideHelp(){averageHelp.hidden=true;averageInfo.setAttribute('aria-expanded','false');}
    averageInfo.addEventListener('pointerenter',event=>{if(event.pointerType==='mouse')showHelp();});
    averageInfo.addEventListener('pointerleave',hideHelp);
    document.addEventListener('keydown',event=>{if(event.key==='Escape')hideHelp();});
    const syncHelp=()=>{if(!averageHelp.hidden&&averageInfo.matches(':hover'))positionHelp();else hideHelp();};
    window.addEventListener('resize',syncHelp);window.addEventListener('scroll',syncHelp,{capture:true,passive:true});
  }
  function filterRecords(){
    const search=q('#recordSearch').value.trim().toLowerCase(),disposition=q('#recordDisposition').value,from=q('#receivedFrom').value,to=q('#receivedTo').value;
    const rows=[...document.querySelectorAll('#incomingRecords tr')];let count=0,pass=0,fail=0;
    rows.forEach(row=>{row.hidden=!!((search&&!row.textContent.toLowerCase().includes(search))||(disposition&&row.dataset.disposition!==disposition&&row.dataset.result!==disposition)||(from&&row.dataset.received<from)||(to&&row.dataset.received>to));if(!row.hidden){count++;if(row.dataset.result==='PASS')pass++;if(row.dataset.result==='FAIL')fail++;}});
    q('#incomingPassCount').textContent=pass;q('#incomingFailCount').textContent=fail;
    q('#recordCount').textContent=count+' of '+rows.length+' loaded records';q('#noIncoming').hidden=count>0;
  }
  function renderDirectory(){
    const search=q('#directorySearch').value.trim().toLowerCase(),entries=window.QACHEM_INCOMING.directory.filter(entry=>(entry.chemical+' '+entry.suppliers.join(' ')).toLowerCase().includes(search));
    q('#directoryEntries').innerHTML=entries.length?entries.map(entry=>`<article><strong>${esc(entry.chemical)}</strong><div>${entry.suppliers.map(esc).join('<br>')}</div></article>`).join(''):'<p class="empty">No matching suppliers.</p>';
  }
  q('#newIncoming').addEventListener('click',()=>q('#incomingChooser').showModal());
  q('#closeIncomingChooser').addEventListener('click',()=>q('#incomingChooser').close());
  q('#incomingRecords')?.addEventListener('click',event=>{const button=event.target.closest('[data-open-incoming]');if(button)location.href='IncomingForm.php?id='+encodeURIComponent(button.dataset.openIncoming);});
  ['recordSearch','recordDisposition','receivedFrom','receivedTo'].forEach(id=>q('#'+id)?.addEventListener('input',filterRecords));
  q('#resetFilters')?.addEventListener('click',()=>{['recordSearch','recordDisposition','receivedFrom','receivedTo'].forEach(id=>q('#'+id).value='');filterRecords();});
  if(q('#incomingRecords'))filterRecords();
  q('#supplierDirectoryBtn').addEventListener('click',()=>{renderDirectory();q('#supplierDirectory').showModal();});
  q('#closeSupplierDirectory').addEventListener('click',()=>q('#supplierDirectory').close());q('#directorySearch').addEventListener('input',renderDirectory);
})();
