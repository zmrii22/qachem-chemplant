<script src="assets/js/feedback.js?v=<?=filemtime(__DIR__.'/../assets/js/feedback.js')?>"></script>
<script>
window.QACHEM_CSRF=<?=json_encode(csrf_token())?>;

document.addEventListener('DOMContentLoaded',()=>{
  // Navigation already marks the active link on the server. Keep wide data tables
  // scrollable with a keyboard and make their extra columns discoverable.
  document.querySelectorAll('.table-wrap,.sheet-scroll,.grid-scroll,.sealing-table-wrap,.incoming-table-scroll').forEach((region,index)=>{
    const table=region.querySelector('table');if(!table)return;
    region.classList.add('responsive-scroll');
    if(!region.hasAttribute('role'))region.setAttribute('role','region');
    if(!region.hasAttribute('aria-label'))region.setAttribute('aria-label','Scrollable data table');
    const hint=document.createElement('p');hint.className='table-overflow-hint';hint.id='tableScrollHint'+index;
    hint.textContent='Scroll sideways for more columns.';region.before(hint);
    region.setAttribute('aria-describedby',[region.getAttribute('aria-describedby'),hint.id].filter(Boolean).join(' '));
    const update=()=>{const overflow=region.scrollWidth>region.clientWidth+1;hint.hidden=!overflow;region.tabIndex=overflow?0:-1;};
    update();
    if(window.ResizeObserver){const observer=new ResizeObserver(update);observer.observe(region);observer.observe(table);}
    else window.addEventListener('resize',update);
  });
});
</script>
