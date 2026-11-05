<?php
require_once __DIR__.'/../app/InprocessGrid.php';
require_once __DIR__.'/../app/spec.php';
require_once __DIR__.'/../app/Calculation.php';
$user=require_login();verify_csrf();
try{
    $d=json_input();$plant=strtoupper((string)($d['plant']??''));$id=(int)($d['entity_id']??0);
    $tab=strtoupper((string)($d['tab']??''));
    if(!in_array($plant,['F21RBD','F40ARP'],true)||!in_array($tab,array_column(InprocessGrid::tabs(),'key'),true)||!qc_master_product_by_id($id)){
        throw new InvalidArgumentException('Invalid tank result selection.');
    }
    $raw=is_array($d['raw_samples']??null)?array_slice($d['raw_samples'],0,8):[];
    if(!user_can('rawform',$user)){
        $raw=[];
        // Check the saved inputs internally; only calculated comparisons leave the API.
        $query=db_connect()->prepare("SELECT r.raw_input_json,r.result_value FROM t_qc_result r JOIN t_qc_inspection i ON i.id=r.inspection_id WHERE i.id=? AND i.inspection_type='INPROCESS' AND i.entity_id=? AND i.plant=? AND r.spec_rule_id=? ORDER BY r.attempt_no DESC,r.id DESC LIMIT 1");
        $query->execute([(int)($d['inspection_id']??0),$id,$plant,(int)($d['rule_id']??0)]);
        if($saved=$query->fetch()){
            $stored=json_decode($saved['raw_input_json']??'{}',true)?:[];
            $raw=$stored['raw_samples']??[];
            $d['value']=$saved['result_value'];
        }
    }
    $comparisons=[];
    foreach(['IQS','QS'] as $spec){
        $profile=qc_master_profile('PRODUCT',$id,$spec,$plant);
        $rules=array_values(array_filter($profile['rules']??[],fn($rule)=>InprocessGrid::ruleTabKey($rule)===InprocessGrid::normalizeTab($tab)));
        $rule=$rules[0]??null;
        if(!$rule){$comparisons[]=['spec_type'=>$spec,'rule_id'=>null,'value'=>null,'low'=>null,'high'=>null,'display'=>'Not applicable','disposition'=>'N/A'];continue;}
        $value=null;$partial=false;$values=[];$hasRaw=false;
        foreach($raw as $sample){
            if(!is_array($sample))continue;
            if(!array_filter($sample,fn($v)=>$v!==''&&$v!==null))continue;
            $hasRaw=true;
            try{
                if($tab==='VTQ')$values[]=qc_num($sample['B']??null);
                elseif(!empty($rule['method_key']))$values[]=qc_calculate((int)$rule['method_key'],$sample)['result'];
                else $values[]=qc_num($sample['A']??null);
            }catch(InvalidArgumentException $e){$partial=true;}
        }
        if($values&&!$partial)$value=qc_average($values);
        elseif(!$hasRaw&&is_numeric($d['value']??null))$value=(float)$d['value'];
        $torque=$tab==='VTQ';
        $comparisons[]=[
            'spec_type'=>$spec,'rule_id'=>$rule['id'],'value'=>$value,
            'low'=>$torque?null:$rule['low_value'],'high'=>$torque?null:$rule['high_value'],
            'display'=>$torque?'No torque limit in this specification':rule_display($rule),
            'disposition'=>$torque?($value===null?'PENDING':'INFO'):InprocessGrid::disposition(evaluate_rule($rule,$value)),
        ];
    }
    json_response(['success'=>true,'comparisons'=>$comparisons]);
}catch(InvalidArgumentException $e){json_response(['success'=>false,'message'=>$e->getMessage()],422);}
catch(Throwable $e){error_log('inprocess comparison: '.$e->getMessage());json_response(['success'=>false,'message'=>'Unable to compare specifications.'],500);}
