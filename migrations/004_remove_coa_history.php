<?php
// Keep current Tracking status/send date only; remove the former document archive.
require_once __DIR__.'/../app/TrackingStorage.php';
qc_tracking_storage($pdo);
