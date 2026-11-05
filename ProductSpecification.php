<?php
require_once __DIR__.'/app/bootstrap.php';
require_once __DIR__.'/app/spec.php';
$u=require_login();$ready=is_file(__DIR__.'/data/qachem_master.json');
$view=strtoupper(trim($_GET['type']??''));$q=trim($_GET['q']??'');
$params=[
 'PH'=>['label'=>'pH','match'=>fn($r)=>strtoupper($r['parameter_code'])==='PH'||str_contains(strtolower($r['parameter_name']),'ph')],
 'VCP'=>['label'=>'Viscosity (cP)','match'=>fn($r)=>in_array(strtoupper($r['parameter_code']),['VISC','VCP'])],
 'VTQ'=>['label'=>'Viscosity (Torque(%))','match'=>fn($r)=>strtoupper($r['parameter_code'])==='VTQ'||str_contains(strtolower($r['parameter_name']),'torque')],
 'TSC'=>['label'=>'TSC%','match'=>fn($r)=>strtoupper($r['parameter_code'])==='TSC'||str_contains(strtolower($r['parameter_name']),'solid')],
 'ALK'=>['label'=>'Alkalinity%','match'=>fn($r)=>str_contains(strtolower($r['parameter_name']),'alkalin')],
 'CHL'=>['label'=>'Chlorine','match'=>fn($r)=>str_contains(strtolower($r['parameter_name']),'chlor')],
 'FOAM'=>['label'=>'Foaming Height','match'=>fn($r)=>str_contains(strtolower($r['parameter_name']),'foam')],
 'ACD'=>['label'=>'Acidity%','match'=>fn($r)=>str_contains(strtolower($r['parameter_name']),'acid')],
 'BAC'=>['label'=>'Bacteria Content','match'=>fn($r)=>str_contains(strtolower($r['parameter_name']),'bacteria')],
 'SG'=>['label'=>'Specific Gravity','match'=>fn($r)=>str_contains(strtolower($r['parameter_name']),'specific gravity')],
];
$rows=[];
if($ready){
 $profiles=qc_master_active('spec_profiles');
 if(in_array($view,['IQS','QS','COA','INCOMING'],true)){$want=$view==='COA'?'QS':$view;$profiles=array_values(array_filter($profiles,fn($p)=>strtoupper((string)$p['spec_type'])===$want));}
 foreach($profiles as $profile){
  $p=$profile+qc_master_profile_entity($profile);
  if($q!==''){
   $hay=strtolower(implode(' ',[$p['product_name']??'',$p['batch_code']??'',$p['product_type']??'',$p['material_name']??'',$p['supplier']??'']));
   if(!str_contains($hay,strtolower($q)))continue;
  }
  $rules=qc_master_rules_for_profile((int)$p['id']);$matrix=[];
  foreach($params as $key=>$info){$matrix[$key]=null;foreach($rules as $r){if(($info['match'])($r)){$matrix[$key]=$r;break;}}}
  $rows[]=['profile'=>$p,'matrix'=>$matrix];
 }
 usort($rows,fn($a,$b)=>strnatcasecmp(($a['profile']['product_type']??$a['profile']['material_type']??'').($a['profile']['product_name']??$a['profile']['material_name']??''),($b['profile']['product_type']??$b['profile']['material_type']??'').($b['profile']['product_name']??$b['profile']['material_name']??''))?:((int)$a['profile']['id']<=>(int)$b['profile']['id']));
}
function cell_pair(?array $rule): array{
 if(!$rule)return ['',''];
 $op=strtoupper((string)$rule['operator']);
 if($op==='RANGE')return [fmt_spec_value($rule['low_value']),fmt_spec_value($rule['high_value'])];
 if(in_array($op,['MIN','GTE','GT','EQ'],true))return [fmt_spec_value($rule['low_value']),''];
 if(in_array($op,['MAX','LTE','LT'],true))return ['',fmt_spec_value($rule['high_value'])];
 return [fmt_spec_value($rule['target_text']),''];
}
function fmt_spec_value($value): string{
 if($value===null)return '';
 $value=trim((string)$value);
 if($value==='')return '';
 if(is_numeric($value)){
  return number_format((float)$value,2,'.','');
 }
 return $value;
}
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Specifications | QACHEM</title><?php include __DIR__.'/include/mainstyle.php';include __DIR__.'/include/mainjs.php';?><link rel="stylesheet" href="assets/reference-editor.css?v=<?=filemtime(__DIR__.'/assets/reference-editor.css')?>"><link rel="stylesheet" href="assets/spreadsheet.css?v=<?=filemtime(__DIR__.'/assets/spreadsheet.css')?>"></head><body><?php include __DIR__.'/include/mainnavbar.php';?><main class="page sheet-page">
<?php if(!$ready):?><div class="alert warn">Run the database migration and seed first.</div><?php else:?>
<div id="productFeedback" class="reference-feedback" role="status" aria-live="polite" hidden><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 12 3 3 7-7"/><circle cx="12" cy="12" r="9"/></svg><span id="productFeedbackText"></span><button type="button" id="dismissProductFeedback" aria-label="Dismiss message">&times;</button></div>
<form class="sheet-toolbar" method="get"><?php if($u['level']>=3):?><button type="button" class="btn small primary" id="newProductSpec">Add Product Specification</button><?php endif;?><strong>Type</strong><select name="type"><option value="">All</option><?php foreach(['IQS','COA','INCOMING'] as $x):?><option value="<?=$x?>" <?=$view===$x?'selected':''?>><?=$x?></option><?php endforeach;?></select><strong>Search</strong><input name="q" value="<?=e($q)?>" placeholder="Search product / batch / type"><button class="btn small primary">Filter</button><a class="btn small" href="ProductSpecification.php">Reset</a></form>
<div class="sheet-scroll"><table class="sheet-table spec-sheet"><colgroup><col style="width:42px"><col style="width:125px"><col style="width:230px"><col style="width:70px"><col style="width:70px"><col style="width:70px"><?php foreach($params as $_):?><col style="width:50px"><col style="width:50px"><?php endforeach;?><col style="width:60px"></colgroup>
<thead><tr><th rowspan="2">ID</th><th rowspan="2">Product Type</th><th rowspan="2">Name</th><th rowspan="2">Batch Code</th><th rowspan="2">Factory</th><th rowspan="2">Spec. Type</th><?php foreach($params as $p):?><th class="group" colspan="2"><?=e($p['label'])?></th><?php endforeach;?><th rowspan="2">Shelf Life</th></tr><tr><?php foreach($params as $p):?><th class="subhead">Low</th><th class="subhead">High</th><?php endforeach;?></tr></thead><tbody>
<?php foreach($rows as $row):$p=$row['profile'];$name=$p['product_name']?:$p['material_name'];$type=$p['product_type']?:$p['material_type'];$batch=$p['batch_code']?:$p['supplier'];$factory=$p['factory_scope']?:$p['supplier_scope']?:'All';$spec=$p['spec_type']==='QS'?'COA':$p['spec_type'];?><tr><td><?php if($p['entity_type']==='PRODUCT'):?><button type="button" class="idpill spec-open" data-open-spec="<?=e($p['id'])?>"><?=e($p['id'])?></button><?php else:?><span class="idpill"><?=e($p['id'])?></span><?php endif;?></td><td><?=e($type)?></td><td class="spec-name-cell"><?=e($name)?></td><td><?=e($batch)?></td><td><?=e($factory)?></td><td><?=e($spec)?></td><?php foreach($params as $key=>$info):$pair=cell_pair($row['matrix'][$key]);?><td class="<?=$pair[0]===''?'blank':'spec-number'?>"><?=e($pair[0])?></td><td class="<?=$pair[1]===''?'blank':'spec-number'?>"><?=e($pair[1])?></td><?php endforeach;?><td><?=e($p['shelf_life_value']??'')?></td></tr><?php endforeach;?>
</tbody></table></div><?php endif;?></main><?php include __DIR__.'/include/mainfooter.php';include __DIR__.'/include/product_editor.php';?></body></html>

