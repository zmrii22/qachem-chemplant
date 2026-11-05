<?php
if(!function_exists('current_user'))require_once __DIR__.'/../app/bootstrap.php';
require_once __DIR__.'/../app/navigation.php';
$navUser=current_user()?:['name'=>'','level'=>0];
$navCurrent=basename($_SERVER['PHP_SELF']??'');
$navigation=qc_navigation_items($navUser);
?>
<nav class="navbar" aria-label="Main navigation">
  <div class="nav-top">
    <a class="mainlogo" href="home.php" aria-label="QACHEM dashboard"><img src="TGLOGO.png" alt="Top Glove"><span>QA</span></a>
    <button class="nav-toggle" type="button" aria-label="Open sidebar" aria-expanded="false" aria-controls="sidebarMenu"><i class="fi fi-rr-angle-small-right" aria-hidden="true"></i></button>
  </div>
  <div class="spread" id="sidebarMenu"><div class="cat-buttons">
    <?php foreach($navigation as $index=>$item):
      $children=$item['children']??[];
      $active=qc_navigation_active($item,$navCurrent);
      $menuId='navGroup'.$index;
    ?>
      <?php if(!$children):?>
        <a href="<?=e($item['href'])?>" class="cat-btn<?=$active?' active':''?>" title="<?=e($item['label'])?>"<?=$active?' aria-current="page"':''?>><i class="fi fi-rr-<?=e($item['icon'])?>" aria-hidden="true"></i><span class="nav-label"><?=e($item['label'])?></span></a>
      <?php else:?>
        <div class="nav-group<?=$active?' active-group':''?>">
          <button class="cat-btn nav-group-toggle<?=$active?' active':''?>" type="button" title="<?=e($item['label'])?>" aria-expanded="<?=$active?'true':'false'?>" aria-controls="<?=$menuId?>"><i class="fi fi-rr-<?=e($item['icon'])?>" aria-hidden="true"></i><span class="nav-label"><?=e($item['label'])?></span><span class="nav-chevron" aria-hidden="true"></span></button>
          <ul class="nav-submenu" id="<?=$menuId?>"<?=$active?'':' hidden'?>>
            <?php foreach($children as $child):$childActive=!empty($child['href'])&&$navCurrent===basename($child['href']);$childBranchActive=qc_navigation_active($child,$navCurrent);?>
              <li><?php if(!empty($child['href'])):?>
                <a href="<?=e($child['href'])?>" class="nav-child<?=$childActive?' active':($childBranchActive?' ancestor-active':'')?>"<?=$childActive?' aria-current="page"':''?>><span class="nav-dot" aria-hidden="true"></span><span class="nav-child-label"><?=e($child['label'])?><?php if(!empty($child['scope'])):?><small><?=e($child['scope'])?></small><?php endif;?></span></a>
                <?php if(!empty($child['children'])):?><ul class="nav-nested-menu" aria-label="<?=e($child['label'])?> tools">
                  <?php foreach($child['children'] as $tool):$toolActive=qc_navigation_active($tool,$navCurrent);?>
                    <li><a href="<?=e($tool['href'])?>" class="nav-child<?=$toolActive?' active':''?>"<?=$toolActive?' aria-current="page"':''?>><span class="nav-dot" aria-hidden="true"></span><span class="nav-child-label"><?=e($tool['label'])?></span></a></li>
                  <?php endforeach;?>
                </ul><?php endif;?>
              <?php else:?>
                <span class="nav-child nav-placeholder" aria-disabled="true"><span class="nav-dot" aria-hidden="true"></span><span class="nav-child-label"><?=e($child['label'])?><small>Will continue</small></span></span>
              <?php endif;?></li>
            <?php endforeach;?>
          </ul>
        </div>
      <?php endif;?>
    <?php endforeach;?>
  </div></div>
  <div class="nav-bottom">
    <div class="hero">TG CHEMICAL SDN BHD<span>F21RBD, F40ARP</span></div>
    <div class="nav-user"><span class="nav-avatar" aria-hidden="true"><?=e(strtoupper(qc_text_slice((string)$navUser['name'],0,1)))?></span><div class="nav-user-details"><strong><?=e($navUser['name'])?></strong><span class="star" title="<?=e(access_label((int)$navUser['level']))?>">Level <?=e($navUser['level'])?></span></div></div>
    <a href="logout.php" class="cat-btn logout" title="Logout"><i class="fi fi-rr-sign-out-alt" aria-hidden="true"></i><span class="nav-label">Logout</span></a>
  </div>
</nav>
<script>
(() => {
  const navbar=document.querySelector('.navbar'),button=navbar.querySelector('.nav-toggle'),icon=button.querySelector('i');
  const desktop=matchMedia('(min-width:901px)'),hover=matchMedia('(hover:hover)');
  let leaveTimer,keyboardFocus=true;
  function apply(expanded){
    clearTimeout(leaveTimer);
    navbar.classList.toggle('nav-expanded',expanded);
    button.setAttribute('aria-expanded',String(expanded));
    button.setAttribute('aria-label',expanded?'Close sidebar':'Open sidebar');
    icon.className='fi fi-rr-angle-small-'+(expanded?'left':'right');
  }
  document.body.classList.add('nav-collapsed');apply(false);
  navbar.addEventListener('pointerenter',event=>{if(event.pointerType!=='touch'&&desktop.matches&&hover.matches)apply(true);});
  navbar.addEventListener('pointerleave',event=>{if(event.pointerType!=='touch'&&desktop.matches&&hover.matches){clearTimeout(leaveTimer);leaveTimer=setTimeout(()=>apply(false),140);}});
  navbar.addEventListener('pointerdown',()=>{keyboardFocus=false;});
  navbar.addEventListener('focusin',event=>{if(keyboardFocus&&event.target!==button)apply(true);});
  navbar.addEventListener('focusout',event=>{if(!navbar.contains(event.relatedTarget))apply(false);});
  button.addEventListener('click',()=>apply(!navbar.classList.contains('nav-expanded')));
  navbar.querySelectorAll('.nav-group-toggle').forEach(toggle=>{
    let reveal=false;
    toggle.addEventListener('pointerdown',()=>{reveal=!navbar.classList.contains('nav-expanded');});
    toggle.addEventListener('click',()=>{
      const menu=document.getElementById(toggle.getAttribute('aria-controls'));
      const open=reveal||toggle.getAttribute('aria-expanded')!=='true';
      reveal=false;apply(true);toggle.setAttribute('aria-expanded',String(open));menu.hidden=!open;
    });
  });
  document.addEventListener('pointerdown',event=>{if(!navbar.contains(event.target))apply(false);});
  document.addEventListener('keydown',event=>{keyboardFocus=true;if(event.key==='Escape'){apply(false);if(navbar.contains(document.activeElement))button.focus();}});
  desktop.addEventListener('change',()=>apply(false));
})();
</script>
