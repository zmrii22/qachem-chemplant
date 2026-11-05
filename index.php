<?php
require_once __DIR__ . '/app/bootstrap.php';
header('Location: '.(current_user() ? 'home.php' : 'login.php'));
exit;
