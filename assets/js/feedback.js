/* Consistent result display and feedback after confirmed saves. */
(() => {
  let timer;
  function result(value){
    if(value===null||value===undefined||value==='')return '\u2014';
    return Number.isFinite(Number(value))?Number(value).toFixed(2):String(value);
  }
  function notify(message,type='success'){
    let toast=document.getElementById('qachemFeedback');
    if(!toast){toast=document.createElement('div');toast.id='qachemFeedback';document.body.append(toast);}
    clearTimeout(timer);toast.className='qachem-feedback '+type;
    toast.setAttribute('role',type==='error'?'alert':'status');toast.textContent=message;toast.hidden=false;
    timer=setTimeout(()=>{toast.hidden=true;},3200);
  }
  function flash(message){try{sessionStorage.setItem('qachemFeedback',message);}catch(_){}}
  window.QACHEM_UI={result,notify,flash};
  document.addEventListener('DOMContentLoaded',()=>{
    try{const message=sessionStorage.getItem('qachemFeedback');sessionStorage.removeItem('qachemFeedback');if(message)notify(message);}catch(_){}
  });
})();
