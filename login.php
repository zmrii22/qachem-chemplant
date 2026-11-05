<?php
require_once __DIR__ . '/app/bootstrap.php';
if (current_user()) { header('Location: home.php'); exit; }
$error = flash('flash_error') ?: '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $badge = trim($_POST['badge'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    if ($badge === '' || $password === '') {
        $error = 'Please enter both badge and password.';
    } else {
        try {
            $pdo = db_connect();
            $stmt = ctype_digit($badge)
                ? $pdo->prepare('SELECT * FROM m_user WHERE Badge = :badge OR Name = :name LIMIT 1')
                : $pdo->prepare('SELECT * FROM m_user WHERE Name = :name LIMIT 1');
            $params=[':name'=>$badge];
            if(ctype_digit($badge))$params[':badge']=(int)$badge;
            $stmt->execute($params);
            $row = $stmt->fetch();
            $stored = $row['Password'] ?? '';
            $isHash = is_string($stored) && preg_match('/^\$2[ayb]\$|^\$argon2/i', $stored);
            $plainStored=trim((string)$stored);
            $plainInput=trim($password);
            $ok = $row && ($isHash ? password_verify($password, $stored) : hash_equals($plainStored, $plainInput));
            if ($ok) {
                session_regenerate_id(true);
                $_SESSION['badge'] = (string)$row['Badge'];
                $_SESSION['name'] = (string)($row['Name'] ?? $row['Badge']);
                $_SESSION['level'] = (int)($row['Level'] ?? 1);
                $_SESSION['last_activity'] = time();
                csrf_token();
                header('Location: home.php'); exit;
            }
            $error = 'Invalid badge or password.';
        } catch (Throwable $ex) {
            error_log('QACHEM login error: '.$ex->getMessage());
            $cfg=qachem_config();
            $detail=empty($cfg['app']['production']) ? ' '.$ex->getMessage() : '';
            $error = 'Unable to sign in. Please contact the system administrator.'.$detail;
        }
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sign in | QACHEM</title><link rel="stylesheet" href="assets/qachem.css"></head>
<body class="login-page"><div class="login-shell"><div class="brand-card"><img src="TGLOGO.png" alt="Top Glove"><div><strong>QACHEM</strong><span>QA Chemplant Recording System</span></div></div><form class="login-card" method="post" autocomplete="off"><h1>Sign in</h1><p>F21RBD &amp; F40ARP Quality Assurance</p><?php if($error): ?><div class="alert error"><?=e($error)?></div><?php endif; ?><label>Badge / Username<input name="badge" required autofocus autocomplete="username"></label><label>Password<input type="password" name="password" required autocomplete="current-password"></label><button class="btn primary wide" type="submit">Sign in</button></form></div></body></html>
