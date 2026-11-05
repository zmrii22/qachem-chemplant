<?php
/** Set a placeholder's href when that factory's page is ready. */
function qc_navigation_items(array $user): array {
    $factories=function(string $page,bool $scrubber=false): array {
        $items=[
            ['label'=>'F21RBD, F40ARP','href'=>$page],
            ['label'=>'F20BC','href'=>null],
            ['label'=>'F19BF','href'=>null],
        ];
        if($scrubber)$items[]=['label'=>'SCRUBBER','href'=>null];
        return $items;
    };
    $items=[
        ['label'=>'Dashboard','icon'=>'home','href'=>'home.php'],
        ['label'=>'Incoming','icon'=>'inbox-in','children'=>$factories('Incoming.php',true)],
    ];
    $inprocess=$factories('Inprocess.php');
    $items[]=['label'=>'Inprocess','icon'=>'workflow','children'=>$inprocess];
    $items[]=['label'=>'Tracking','icon'=>'document','children'=>[['label'=>'F21RBD, F40ARP','href'=>'Tracking.php']]];
    $items[]=['label'=>'Sealing','icon'=>'box','children'=>[['label'=>'F21RBD, F40ARP','href'=>'Sealing.php']]];
    $items[]=['label'=>'Product Specification','icon'=>'list-check','children'=>$factories('ProductSpecification.php')];
    $items[]=['label'=>'Others','icon'=>'book-alt','children'=>[['label'=>'Method','href'=>'method.php','scope'=>'F21RBD, F40ARP'],['label'=>'COA Remark','href'=>'COARemark.php']]];
    if(user_can('team',$user))$items[]=['label'=>'Team & Access','icon'=>'user-gear','href'=>'TeamAccess.php'];
    return $items;
}

function qc_navigation_active(array $item,string $current): bool {
    if(!empty($item['href'])&&basename((string)parse_url($item['href'],PHP_URL_PATH))===$current)return true;
    foreach($item['children']??[] as $child)if(qc_navigation_active($child,$current))return true;
    return false;
}
