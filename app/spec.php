<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/master.php';

function qc_normalize_factory(string $factory): string {
    $factory=strtoupper(trim($factory));
    if(preg_match('/\bF\s*0*(\d+)([A-Z]*)\b/',$factory,$m)) return 'F'.(int)$m[1].($m[2]??'');
    return str_replace(' ','',$factory);
}
function qc_factory_scope_match(?string $scope, ?string $factory): bool {
    $scope=trim((string)$scope);$factory=qc_normalize_factory((string)$factory);
    if($scope===''||strtoupper($scope)==='ALL'||$factory==='')return true;
    preg_match_all('/\bF\s*0*(\d+)([A-Z]*)\b/i',$scope,$m,PREG_SET_ORDER);
    $codes=[];foreach($m as $x)$codes[]='F'.(int)$x[1].strtoupper($x[2]??'');
    if(stripos($scope,'except')!==false)return !in_array($factory,$codes,true);
    if($codes)return in_array($factory,$codes,true);
    return stripos(str_replace(' ','',$scope),$factory)!==false;
}
function qc_factory_scope_score(?string $scope): int {
    $scope=trim((string)$scope);if($scope===''||strtoupper($scope)==='ALL')return 1;if(stripos($scope,'except')!==false)return 2;return 3;
}
function qc_supplier_scope_match(?string $scope,?string $supplier): bool {
    $scope=trim((string)$scope);$supplier=trim((string)$supplier);if($scope===''||$supplier==='')return true;return stripos($scope,$supplier)!==false||stripos($supplier,$scope)!==false;
}
function get_spec_profile_by_id(PDO $pdo, int $profileId): ?array {
    if($profileId<1)return null;
    return qc_master_profile_by_id($profileId);
}
function get_spec_profile(PDO $pdo, string $entityType, int $entityId, string $specType, ?string $factory = null, ?string $supplier = null): ?array {
    return qc_master_profile($entityType,$entityId,$specType,$factory,$supplier);
}
function evaluate_rule(array $rule,$numericValue,?string $textValue=null): string {
    $op=strtoupper((string)($rule['operator']??'INFO'));
    if(($rule['data_type']??'')==='TEXT'||$op==='TEXT'||$op==='INFO'){
        if($op==='INFO')return'PASS';$expected=trim((string)($rule['target_text']??''));$actual=trim((string)$textValue);if($expected===''||$actual==='')return'PENDING';$normalize=fn($x)=>preg_replace('/[^a-z0-9]+/',' ',strtolower($x));$e=$normalize($expected);$a=$normalize($actual);return(stripos($e,$a)!==false||stripos($a,$e)!==false)?'PASS':'REVIEW';
    }
    if($numericValue===''||$numericValue===null||!is_numeric($numericValue))return'PENDING';$v=(float)$numericValue;$low=$rule['low_value']!==null?(float)$rule['low_value']:null;$high=$rule['high_value']!==null?(float)$rule['high_value']:null;
    switch($op){case'OBSERVE':return'REVIEW';case'RANGE':return($low===null||$v>=$low)&&($high===null||$v<=$high)?'PASS':'FAIL';case'MIN':case'GTE':return$low!==null&&$v>=$low?'PASS':'FAIL';case'GT':return$low!==null&&$v>$low?'PASS':'FAIL';case'MAX':case'LTE':return$high!==null&&$v<=$high?'PASS':'FAIL';case'LT':return$high!==null&&$v<$high?'PASS':'FAIL';case'EQ':return$low!==null&&abs($v-$low)<0.000001?'PASS':'FAIL';default:return'PENDING';}
}
function rule_display(array $rule): string {$op=strtoupper((string)$rule['operator']);$low=$rule['low_value'];$high=$rule['high_value'];if($op==='RANGE')return$low.' to '.$high;if(in_array($op,['MIN','GTE'],true))return'≥ '.$low;if($op==='GT')return'> '.$low;if(in_array($op,['MAX','LTE'],true))return'≤ '.$high;if($op==='LT')return'< '.$high;if($op==='EQ')return'= '.$low;return(string)($rule['target_text']??'');}
