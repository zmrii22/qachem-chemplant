<?php
require_once __DIR__.'/app/bootstrap.php';
require_once __DIR__.'/app/master.php';
$u=require_login();$ready=is_file(__DIR__.'/data/qachem_master.json');
$methods=$ready?qc_master_methods():[];
$legend=[['WOAF','Weight of Aluminium Foil'],['WOS','Weight of Sample'],['WOds','Weight of Dry Sample'],['WOws','Weight of Wet Sample'],['IR','Initial Reading'],['FR','Final Reading']];
$methodMeta=[
 1=>['TSC%',['WOAF (g)','WOws (g)','WOAF + dry sample (g)','',''],'WOds = C − A','TSC = (U ÷ B) × 100','Average = (S1 + S2 + S3) ÷ 3','TSC = ((C − A) ÷ B) × 100'],
 2=>['Alkalinity %(CA)',['Weight of sample (g)','Weight of sample + water (g)','Initial titrant V1 (ml)','Final titrant V2 (ml)',''],'CA = D − C','CA% = U × 1.6','Average = (S1 + S2 + S3) ÷ 3','CA% = (D − C) × 1.6'],
 3=>['Alkalinity %(CA) TG HF',['Weight of sample (g)','Weight of sample + water (g)','Initial titrant V1 (ml)','Final titrant V2 (ml)',''],'CA = D − C','CA% = U × 0.16','Average = (S1 + S2 + S3) ÷ 3','CA% = (D − C) × 0.16'],
 4=>['Alkalinity %(PTC)',['Weight of sample (g)','Weight of sample + water (g)','Initial titrant V1 (ml)','Final titrant V2 (ml)','Final titrant V3 (ml)'],'PTC = (D − C) + (E − D)','PTC% = U × 1.0 × 5','Average = (S1 + S2 + S3) ÷ 3','PTC% = ((D − C) + (E − D)) × 5'],
 5=>['Chlorine',['Weight of sample (g)','Weight of sample + water (g)','Initial titrant V1 (ml)','Final titrant V2 (ml)',''],'Volume = D − C','Chlorine% = U × 0.142','Average = (S1 + S2 + S3) ÷ 3','Chlorine% = (D − C) × 0.142'],
 6=>['Foaming Height',['Weight of sample (g)','Weight of sample + water (g)','Initial height H1 (cm)','Final height H2 (cm)',''],'','','Foaming height = D − C','Foaming height = H2 − H1'],
 7=>['Filtration',['Weight of sample W1 (g)','Wire mesh W2 (g)','Dried mesh residue W3 (g)','',''],'','Volume = D − C','Sieve residue = ((C − B) ÷ A) × 100','Sieve residue% = ((W3 − W2) ÷ W1) × 100'],
 8=>['Acidity %',['Volume of sample (ml)','Initial titrant V1 (ml)','Final titrant V2 (ml)','',''],'V3 = C − B','Acidity = (U + 0.56) ÷ 0.31','Average = (S1 + S2 + S3) ÷ 3','Acidity = ((C − B) + 0.56) ÷ 0.31'],
 9=>['Bacteria',['Reading','','','',''],'','','Result = A','Pass < 10'],
 10=>['pH',['Reading','Temperature','','','',''],'','','Result = A','Average = (S1 + S2) ÷ 2'],
 11=>['Viscosity, cP',['Viscosity (cP)','Viscosity torque (%)','Temperature','',''],'','','Result = A','Average = (S1 + S2) ÷ 2'],
 12=>['Chlorine %',['Weight of sample (g)','Normality / molarity (N/M)','Initial titrant V1 (ml)','Final titrant V2 (ml)',''],'V3 = D − C','Chlorine% = U × 25 × 0.0142','Average = (S1 + S2 + S3) ÷ 3','In-house sodium hypo: (D − C) × 25 × 0.0142'],
 13=>['Specific Gravity',['Reading','','','',''],'','','Result = A',''],
 14=>['Moisture Content',['Petri dish (A)','Sample (B)','Dish + sample after heating (C)','',''],'','','Moisture = (((A + B) − C) ÷ (A + B)) × 100','Moisture% = (((A + B) − C) ÷ (A + B)) × 100'],
 15=>['Dry Sieve Mesh Particle',['Empty plate (A)','Sample (B)','Plate + sample after sieve (C)','',''],'','','Dry sieve = ((C − A) ÷ B) × 100','Dry sieve% = ((C − A) ÷ B) × 100'],
 16=>['Available Chlorine',['Sample weight (A)','','Initial titrant V1 (ml)','Final titrant V2 (ml)',''],'Volume = D − C','Available chlorine = U × 3.72 ÷ (2 × A)','Average = (S1 + S2 + S3) ÷ 3','Supplier sodium hypo: ((D − C) × 3.72) ÷ (2 × A)'],
 17=>['Sodium Hydroxide',['Sample weight (A)','','Initial titrant V1 (ml)','Final titrant V2 (ml)',''],'Volume = D − C','NaOH = U × 4 ÷ A','Average = (S1 + S2 + S3) ÷ 3','Supplier caustic soda: ((D − C) × 4) ÷ A'],
];
function formula_frac(string $top,string $bottom): string{
  return '<span class="formula-frac"><span>'.e($top).'</span><span>'.e($bottom).'</span></span>';
}
function method_formula_html(?string $text): string{
  $text=trim((string)$text);
  if($text==='')return '';
  $map=[
    'WOds = C − A'=>'<span>WOds = C − A</span>',
    'CA = D − C'=>'<span>CA = D − C</span>',
    'PTC = (D − C) + (E − D)'=>'<span>PTC = (D − C) + (E − D)</span>',
    'Volume = D − C'=>'<span>Volume = D − C</span>',
    'V3 = C − B'=>'<span>V3 = C − B</span>',
    'V3 = D − C'=>'<span>V3 = D − C</span>',
    'Result = A'=>'<span>Result = A</span>',
    'TSC = (U ÷ B) × 100'=>'<span>TSC = </span>'.formula_frac('U','B').'<span> × 100</span>',
    'TSC = ((C − A) ÷ B) × 100'=>'<span>TSC = </span>'.formula_frac('C − A','B').'<span> × 100</span>',
    'CA% = U × 1.6'=>'<span>CA% = U × 1.6</span>',
    'CA% = (D − C) × 1.6'=>'<span>CA% = (D − C) × 1.6</span>',
    'CA% = U × 0.16'=>'<span>CA% = U × 0.16</span>',
    'CA% = (D − C) × 0.16'=>'<span>CA% = (D − C) × 0.16</span>',
    'PTC% = U × 1.0 × 5'=>'<span>PTC% = U × 1.0 × 5</span>',
    'PTC% = ((D − C) + (E − D)) × 5'=>'<span>PTC% = ((D − C) + (E − D)) × 5</span>',
    'Chlorine% = U × 0.142'=>'<span>Chlorine% = U × 0.142</span>',
    'Chlorine% = (D − C) × 0.142'=>'<span>Chlorine% = (D − C) × 0.142</span>',
    'Foaming height = D − C'=>'<span>Foaming height = D − C</span>',
    'Foaming height = H2 − H1'=>'<span>Foaming height = H2 − H1</span>',
    'Sieve residue = ((C − B) ÷ A) × 100'=>'<span>Sieve residue = </span>'.formula_frac('C − B','A').'<span> × 100</span>',
    'Sieve residue% = ((W3 − W2) ÷ W1) × 100'=>'<span>Sieve residue% = </span>'.formula_frac('W3 − W2','W1').'<span> × 100</span>',
    'Acidity = (U + 0.56) ÷ 0.31'=>'<span>Acidity = </span>'.formula_frac('U + 0.56','0.31'),
    'Acidity = ((C − B) + 0.56) ÷ 0.31'=>'<span>Acidity = </span>'.formula_frac('(C − B) + 0.56','0.31'),
    'Average = (S1 + S2 + S3) ÷ 3'=>'<span>Average = </span>'.formula_frac('S1 + S2 + S3','3'),
    'Average = (S1 + S2) ÷ 2'=>'<span>Average = </span>'.formula_frac('S1 + S2','2'),
    'Pass < 10'=>'<span>Pass &lt; 10</span>',
    'Chlorine% = U × 25 × 0.0142'=>'<span>Chlorine% = U × 25 × 0.0142</span>',
    'In-house sodium hypo: (D − C) × 25 × 0.0142'=>'<span>In-house sodium hypo = (D − C) × 25 × 0.0142</span>',
    'Moisture = (((A + B) − C) ÷ (A + B)) × 100'=>'<span>Moisture = </span>'.formula_frac('(A + B) − C','A + B').'<span> × 100</span>',
    'Moisture% = (((A + B) − C) ÷ (A + B)) × 100'=>'<span>Moisture% = </span>'.formula_frac('(A + B) − C','A + B').'<span> × 100</span>',
    'Dry sieve = ((C − A) ÷ B) × 100'=>'<span>Dry sieve = </span>'.formula_frac('C − A','B').'<span> × 100</span>',
    'Dry sieve% = ((C − A) ÷ B) × 100'=>'<span>Dry sieve% = </span>'.formula_frac('C − A','B').'<span> × 100</span>',
    'Available chlorine = U × 3.72 ÷ (2 × A)'=>'<span>Available chlorine = </span>'.formula_frac('U × 3.72','2 × A'),
    'Supplier sodium hypo: ((D − C) × 3.72) ÷ (2 × A)'=>'<span>Supplier sodium hypo = </span>'.formula_frac('(D − C) × 3.72','2 × A'),
    'NaOH = U × 4 ÷ A'=>'<span>NaOH = </span>'.formula_frac('U × 4','A'),
    'Supplier caustic soda: ((D − C) × 4) ÷ A'=>'<span>Supplier caustic soda = </span>'.formula_frac('(D − C) × 4','A'),
  ];
  return '<span class="formula-line">'.($map[$text]??e($text)).'</span>';
}
$calcOptions=[];
foreach($methods as $m){$key=(int)$m['method_key'];$meta=$methodMeta[$key]??[$m['method_name'],['','','','',''],'','',$m['formula_text'],''];$calcOptions[]=['key'=>$key,'name'=>$meta[0],'inputs'=>$meta[1],'formula'=>$meta[5],'formulaHtml'=>method_formula_html($meta[5])];}
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Methods | QACHEM</title><?php include __DIR__.'/include/mainstyle.php';include __DIR__.'/include/mainjs.php';?><link rel="stylesheet" href="assets/spreadsheet.css?v=<?=filemtime(__DIR__.'/assets/spreadsheet.css')?>"></head><body><?php include __DIR__.'/include/mainnavbar.php';?><main class="page sheet-page method-page">
<?php if(!$ready):?><div class="alert warn">Run the database migration and seed first.</div><?php else:?>
<div class="sheet-toolbar method-toolbar"><strong>Method</strong><button class="btn small" type="button" data-open-modal="legendModal">Legend</button><button class="btn small primary" type="button" data-open-modal="calculatorModal">Calculator</button></div>
<div class="sheet-scroll"><table class="sheet-table method-sheet"><colgroup><col style="width:45px"><col style="width:205px"><col style="width:105px"><col style="width:145px"><col style="width:175px"><col style="width:145px"><col style="width:145px"><col style="width:155px"><col style="width:110px"><col style="width:150px"><col style="width:160px"></colgroup>
<thead><tr><th colspan="2">Method</th><th colspan="5">Input Name</th><th colspan="2">Output Name &amp; Calculation</th><th>Result Name</th><th rowspan="2">Remark</th></tr><tr><th>Key</th><th>Method Name</th><th>A</th><th>B</th><th>C</th><th>D</th><th>E</th><th>U</th><th>V</th><th>Result Calc.</th></tr></thead><tbody>
<?php foreach($methods as $m):$meta=$methodMeta[(int)$m['method_key']]??[$m['method_name'],['','','','',''],'','',$m['formula_text'],''];$inputs=$meta[1];?><tr><td class="keycol"><?=e($m['method_key'])?></td><td class="method-name"><span><?=e($meta[0])?></span></td><?php for($i=0;$i<5;$i++):?><td class="<?=trim($inputs[$i]??'')===''?'inactive':'light'?>"><?=e($inputs[$i]??'')?></td><?php endfor;?><td class="<?=trim($meta[2])===''?'inactive':'light formula-cell'?>"><?=method_formula_html($meta[2])?></td><td class="<?=trim($meta[3])===''?'inactive':'light formula-cell'?>"><?=method_formula_html($meta[3])?></td><td class="light formula-cell"><?=method_formula_html($meta[4])?></td><td class="formula-cell"><?=method_formula_html($meta[5])?></td></tr><?php endforeach;?>
</tbody></table></div><?php endif;?></main>

<div class="modal-backdrop sheet-modal-backdrop" id="legendModal" aria-hidden="true">
  <div class="modal sheet-modal legend-modal" role="dialog" aria-modal="true" aria-labelledby="legendTitle">
    <div class="sheet-modal-head"><div><strong id="legendTitle">Legend</strong><span>Symbols used in calculations.</span></div><button class="btn small" type="button" data-close-modal>Close</button></div>
    <table class="legend"><thead><tr><th>ABBV</th><th>Description</th></tr></thead><tbody><?php foreach($legend as $l):?><tr><td><?=e($l[0])?></td><td><?=e($l[1])?></td></tr><?php endforeach;?></tbody></table>
  </div>
</div>

<div class="modal-backdrop sheet-modal-backdrop" id="calculatorModal" aria-hidden="true">
  <div class="modal sheet-modal calculator-modal" role="dialog" aria-modal="true" aria-labelledby="calculatorTitle">
    <div class="sheet-modal-head"><div><strong id="calculatorTitle">Calculator</strong><span>Test the formula before entering production samples.</span></div><button class="btn small" type="button" data-close-modal>Close</button></div>
    <div class="calc-grid"><label>Method<select id="calcMethod"><?php foreach($calcOptions as $opt):?><option value="<?=e($opt['key'])?>"><?=e($opt['key'].' - '.$opt['name'])?></option><?php endforeach;?></select></label><div class="calc-formula"><strong>Formula / Remark</strong><div id="calcFormula" class="formula-card"></div></div></div>
    <div class="calc-inputs" id="calcInputs"></div>
    <div class="actions calc-actions"><button type="button" class="btn primary" id="runCalcBtn">Calculate</button><button type="button" class="btn" id="clearCalcBtn">Clear</button><span id="calcState" class="muted"></span></div>
    <div class="calc-result" id="calcResult"><span>Intermediate</span><strong>--</strong><span>Result</span><strong>--</strong></div>
  </div>
</div>
<?php include __DIR__.'/include/mainfooter.php';?>
<script>
const calcMethods=<?=json_encode($calcOptions,JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
const byId=id=>document.getElementById(id);
function openSheetModal(id){const m=byId(id);if(!m)return;m.classList.add('show');m.setAttribute('aria-hidden','false');if(id==='calculatorModal')renderCalcInputs();}
function closeSheetModal(el){const m=el.closest('.sheet-modal-backdrop');if(!m)return;m.classList.remove('show');m.setAttribute('aria-hidden','true');}
document.querySelectorAll('[data-open-modal]').forEach(btn=>btn.addEventListener('click',()=>openSheetModal(btn.dataset.openModal)));
document.querySelectorAll('[data-close-modal]').forEach(btn=>btn.addEventListener('click',()=>closeSheetModal(btn)));
document.querySelectorAll('.sheet-modal-backdrop').forEach(bg=>bg.addEventListener('click',e=>{if(e.target===bg){bg.classList.remove('show');bg.setAttribute('aria-hidden','true');}}));
function activeCalcMethod(){return calcMethods.find(m=>String(m.key)===String(byId('calcMethod').value))||calcMethods[0];}
function renderCalcInputs(){const method=activeCalcMethod();byId('calcFormula').innerHTML=method?.formulaHtml||'';const labels=method?.inputs||[];byId('calcInputs').innerHTML=['A','B','C','D','E'].map((key,i)=>`<label><span>${key}</span><small>${labels[i]||'Not used'}</small><input type="number" step="any" data-calc-input="${key}" ${labels[i]?'':'disabled'}></label>`).join('');byId('calcState').textContent='';byId('calcResult').innerHTML='<span>Intermediate</span><strong>--</strong><span>Result</span><strong>--</strong>';}
byId('calcMethod')?.addEventListener('change',renderCalcInputs);
byId('clearCalcBtn')?.addEventListener('click',renderCalcInputs);
byId('runCalcBtn')?.addEventListener('click',async()=>{const method=activeCalcMethod();const inputs={};document.querySelectorAll('[data-calc-input]').forEach(i=>inputs[i.dataset.calcInput]=i.value);byId('calcState').textContent='Calculating...';try{const r=await fetch('api/calculate.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':window.QACHEM_CSRF},body:JSON.stringify({method_key:Number(method.key),inputs})});const j=await r.json();if(!j.success)throw new Error(j.message||'Calculation failed.');const c=j.calculation||{};byId('calcResult').innerHTML=`<span>Intermediate</span><strong>${QACHEM_UI.result(c.intermediate)}</strong><span>Result</span><strong>${QACHEM_UI.result(c.result)} ${c.unit||''}</strong>`;byId('calcState').textContent='Done.';}catch(err){byId('calcState').textContent=err.message;}});
</script></body></html>
