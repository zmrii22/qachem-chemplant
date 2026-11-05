<?php
/** Bind the supplied form fields to the Incoming editor. */
function incoming_example_field(string $name,string $attrs): string {
    $meta=['chemical_name'=>'incomingMaterial','supplier_brand'=>'incomingSupplier','batch_no'=>'incomingBatch','date_received'=>'incomingReceived','inspection_datetime'=>'incomingInspected','expiry_date'=>'incomingExpiry','packaging_condition'=>'incomingPackaging','packaging_remark'=>'incomingPackagingRemark','lv_rv_speed_time'=>'incomingViscositySetup','filter_size'=>'incomingFilterSize'];
    if(isset($meta[$name])){
        $id=$meta[$name];$attrs.=' id="'.e($id).'"';
        if(in_array($name,['chemical_name','supplier_brand','batch_no','date_received','inspection_datetime','packaging_condition'],true))$attrs.=' required';
        if(in_array($name,['chemical_name','supplier_brand','packaging_condition'],true)){
            $options=$name==='packaging_condition'?'<option value="">Good / Bad</option><option>Good</option><option>Bad</option>':'<option value="">Select…</option>';
            return '<select'.$attrs.'>'.$options.'</select>';
        }
        $type=['date_received'=>'date','inspection_datetime'=>'datetime-local','expiry_date'=>'date','filter_size'=>'number'][$name]??'text';
        return '<input'.$attrs.' type="'.$type.'"'.($type==='number'?' step="any"':'').'>';
    }
    $observations=['appearance'=>'APP','ph'=>'PH','viscosity'=>'VISC','specific_gravity'=>'SG'];
    if(isset($observations[$name]))return '<span data-document-observation="'.$observations[$name].'"></span>';
    if(preg_match('/^particle_(d10|d50|d90|mean)$/',$name,$match))return '<input'.$attrs.' type="number" step="any" data-document-section="PARTICLE" data-document-key="'.($match[1]==='mean'?'mean':strtoupper($match[1])).'" data-document-sample="0">';
    $sections=['tsc'=>'TSC','wet'=>'WET_SIEVE','moist'=>'MOISTURE','dry'=>'DRY_SIEVE','purity'=>'PURITY'];
    if(preg_match('/^(tsc|wet|moist|dry|purity)_(sample|a|b|c|w1|w2|w3|weight|nm|volume|result|particle|average)(?:_([123]))?$/',$name,$match)){
        $section=$sections[$match[1]];$field=$match[2];$index=(int)($match[3]??1)-1;
        if($field==='sample')return '<input'.$attrs.' readonly tabindex="-1" aria-label="Sample S'.($index+1).'" data-document-label="'.$section.'" data-document-sample="'.$index.'">';
        if($section==='PURITY')return '<span data-document-purity="'.(['weight'=>'A','nm'=>'N','volume'=>'V','result'=>'RESULT','average'=>'AVERAGE'][$field]).'" data-document-sample="'.$index.'"></span>';
        if(in_array($field,['particle','average'],true))return '<input'.$attrs.' data-document-output="'.$section.'" data-document-output-key="'.($field==='particle'?'fine':'average').'" data-document-sample="'.$index.'">';
        return '<input'.$attrs.' type="number" step="any" data-document-section="'.$section.'" data-document-key="'.strtoupper($field).'" data-document-sample="'.$index.'">';
    }
    $dispositions=['disp_pass'=>'PASS','disp_fail'=>'FAIL','disp_accept'=>'ACCEPT','disp_accept_loc'=>'ACCEPT WITH LOC','disp_reject_loc'=>'REJECT WITH LOC'];
    if(isset($dispositions[$name]))return '<input'.$attrs.' data-document-disposition="'.$dispositions[$name].'">';
    if(preg_match('/^(tested_by|verified_by|verified_date)(?:_page[12])?$/',$name,$match)){
        if($match[1]==='verified_by')return '<select'.$attrs.' aria-label="Verified by" data-document-signature="verified"><option value="">Select verifier</option></select>';
        if($match[1]==='verified_date')return '<input'.$attrs.' type="date" aria-label="Verification date" data-document-signature="date">';
        return '<input'.$attrs.' readonly aria-label="Tested by" data-document-signature="tested">';
    }
    throw new LogicException('Unmapped Incoming example field: '.$name);
}
