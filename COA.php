<?php
require_once __DIR__.'/app/bootstrap.php';
require_login();
$query=http_build_query(array_intersect_key($_GET,array_flip(['inspection_id','id'])));
header('Location: Tracking.php'.($query?'?'.$query:''));
exit;
