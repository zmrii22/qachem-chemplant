<?php
/** UTF-8 helpers also work on IIS installs without the optional mbstring extension. */
function qc_text_length(string $text): int {
    if(function_exists('mb_strlen'))return mb_strlen($text,'UTF-8');
    $length=preg_match_all('/./us',$text);
    return $length===false?strlen($text):$length;
}
function qc_text_contains(string $text,string $search): bool {
    if($search==='')return true;
    if(function_exists('mb_stripos'))return mb_stripos($text,$search,0,'UTF-8')!==false;
    return preg_match('/'.preg_quote($search,'/').'/iu',$text)===1;
}
function qc_text_slice(string $text,int $start,int $length): string {
    if(function_exists('mb_substr'))return mb_substr($text,$start,$length,'UTF-8');
    $characters=preg_split('//u',$text,-1,PREG_SPLIT_NO_EMPTY);
    return $characters===false?substr($text,$start,$length):implode('',array_slice($characters,$start,$length));
}
function qc_text_width(string $text): int {
    if(function_exists('mb_strwidth'))return mb_strwidth($text,'UTF-8');
    $characters=preg_split('//u',$text,-1,PREG_SPLIT_NO_EMPTY);
    if($characters===false)return strlen($text);
    $width=0;
    // Count wide CJK/full-width characters and emoji twice for spreadsheet wrapping.
    foreach($characters as $character){
        $wide=preg_match('/[\x{1100}-\x{115F}\x{2329}-\x{232A}\x{2E80}-\x{A4CF}\x{AC00}-\x{D7A3}\x{F900}-\x{FAFF}\x{FE10}-\x{FE19}\x{FE30}-\x{FE6F}\x{FF01}-\x{FF60}\x{FFE0}-\x{FFE6}\x{1F300}-\x{1FAFF}\x{20000}-\x{3FFFD}]/u',$character);
        $width+=$wide===1?2:1;
    }
    return $width;
}
if(!function_exists('str_contains')){
    function str_contains($haystack,$needle): bool {
        $needle=(string)$needle;
        if($needle==='')return true;
        return strpos((string)$haystack,$needle)!==false;
    }
}
if(!function_exists('str_starts_with')){
    function str_starts_with($haystack,$needle): bool {
        $haystack=(string)$haystack;$needle=(string)$needle;
        return $needle===''||strncmp($haystack,$needle,strlen($needle))===0;
    }
}
