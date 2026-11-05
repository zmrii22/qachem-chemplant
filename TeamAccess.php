<?php
require_once __DIR__.'/app/bootstrap.php';
$u=require_level(9);
$pdo=db_connect();
$message='';
$error='';
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
  verify_csrf();
  $action=strtolower(trim((string)($_POST['action']??'')));
  try{
    if($action==='save'){
      $badge=(int)($_POST['badge']??0);
      $name=trim((string)($_POST['name']??''));
      $level=(int)($_POST['level']??1);
      $password=(string)($_POST['password']??'');
      if($badge<1||$name==='')throw new RuntimeException('Badge and name are required.');
      if($level<0)$level=0;
      if($level>9)$level=9;
      $exists=$pdo->prepare('SELECT COUNT(*) FROM m_user WHERE Badge=?');
      $exists->execute([$badge]);
      if((int)$exists->fetchColumn()){
        if($password!==''){
          $st=$pdo->prepare('UPDATE m_user SET Name=?, Password=?, Level=? WHERE Badge=?');
          $st->execute([$name,$password,$level,$badge]);
        }else{
          $st=$pdo->prepare('UPDATE m_user SET Name=?, Level=? WHERE Badge=?');
          $st->execute([$name,$level,$badge]);
        }
        $message='User updated.';
      }else{
        $st=$pdo->prepare('INSERT INTO m_user (Badge,Name,Password,Level) VALUES (?,?,?,?)');
        $st->execute([$badge,$name,$password,$level]);
        $message='User added.';
      }
    }elseif($action==='delete'){
      $badge=(int)($_POST['badge']??0);
      if($badge<1)throw new RuntimeException('Invalid badge.');
      $st=$pdo->prepare('DELETE FROM m_user WHERE Badge=?');
      $st->execute([$badge]);
      $message='User removed.';
    }
  }catch(Throwable $e){
    $error=$e->getMessage();
  }
}
$users=$pdo->query('SELECT Badge,Name,Level FROM m_user ORDER BY Level DESC, Name ASC, Badge ASC')->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Team & Access | QACHEM</title>
  <?php include __DIR__.'/include/mainstyle.php'; ?>
  <?php include __DIR__.'/include/mainjs.php'; ?>
</head>
<body>
<?php include __DIR__.'/include/mainnavbar.php'; ?>
<main class="page">
  <div class="page-head">
    <div>
      <h1>Team & Access</h1>
      
    </div>
  </div>
  <?php if($message): ?><div class="alert success"><?=e($message)?></div><?php endif; ?>
  <?php if($error): ?><div class="alert error"><?=e($error)?></div><?php endif; ?>

  <div class="grid cols-2">
    <section class="card">
      <h2>Add / Update User</h2>
      <form method="post" class="grid cols-2">
        <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
        <input type="hidden" name="action" value="save">
        <label>Badge
          <input name="badge" type="number" min="1" required placeholder="Example: 12345">
        </label>
        <label>Access Level
          <select name="level">
            <?php for($i=0;$i<=9;$i++): ?><option value="<?=$i?>" <?=$i===1?'selected':''?>>Level <?=$i?> — <?=e(access_label($i))?></option><?php endfor; ?>
          </select>
        </label>
        <label class="full">Name
          <input name="name" required placeholder="User full name">
        </label>
        <label class="full">Password
          <input name="password" placeholder="Leave blank when updating and keeping old password">
        </label>
        <div class="actions full">
          <button class="btn primary" type="submit">Save User</button>
        </div>
      </form>
    </section>

    <section class="card">
      <h2>Access Guide</h2>
      <p class="muted">Levels 0–1: view only. Level 2: Incoming and Sealing editing, Tracking planning, read-only Inprocess results. Levels 3–8: full access except Team &amp; Access. Level 9: administrator.</p>
      <div class="alert warn">Deleting a user removes the login account from <strong>m_user</strong>. Existing inspection records keep their saved tester names.</div>
    </section>
  </div>

  <section class="card" style="margin-top:16px">
    <div class="section-title" style="margin-top:0"><h2>Users</h2><span class="muted"><?=count($users)?> user(s)</span></div>
    <div class="table-wrap">
      <table class="data-table">
        <thead><tr><th>Badge</th><th>Name</th><th>Level</th><th>Action</th></tr></thead>
        <tbody>
        <?php if(!$users): ?>
          <tr><td colspan="4" class="empty">No users found.</td></tr>
        <?php else: foreach($users as $row): ?>
          <tr>
            <td><strong><?=e($row['Badge'])?></strong></td>
            <td><?=e($row['Name'])?></td>
            <td><span class="badge <?=((int)$row['Level']>=9)?'verified':'draft'?>">Level <?=e($row['Level'])?></span></td>
            <td>
              <form method="post" onsubmit="return confirm('Remove this user?')" class="actions">
                <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="badge" value="<?=e($row['Badge'])?>">
                <button class="btn small danger" type="submit">Delete / Remove</button>
              </form>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>
<?php include __DIR__.'/include/mainfooter.php'; ?>
</body>
</html>
