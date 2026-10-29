<?php
declare(strict_types=1);
$currentVersion=(string)($state['current_version']??'unknown');
$targetVersion=(string)($state['target_version']??APP_VERSION);
$pending=is_array($state['pending_migrations']??null)?$state['pending_migrations']:[];
$issues=is_array($state['issues']??null)?$state['issues']:[];
$upToDate=!empty($state['up_to_date']);
$ready=!empty($state['ready']);
$assetBase=rtrim((string)$basePath,'/');
$completed=is_array($result)&&!empty($result['upgraded']);
$alreadyCurrent=is_array($result)&&!empty($result['already_current']);
$siteUrl=rtrim((string)($state['site_url']??''),'/');
$homepage=$siteUrl!==''?$siteUrl:($assetBase!==''?$assetBase:'/');
$finished=$completed||$alreadyCurrent||$upToDate;
$migrationsApplied=$completed?count((array)($result['migrations']??[])):0;
$cssAssetVersion=(string)(@filemtime(BASE_PATH.'/assets/css/frontend.css')?:APP_VERSION);
$jsAssetVersion=(string)(@filemtime(BASE_PATH.'/assets/js/frontend.js')?:APP_VERSION);
?>
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="color-scheme" content="light dark"><meta name="robots" content="noindex,nofollow,noarchive">
<title><?=e($pageTitle)?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="<?=e($assetBase.'/assets/css/frontend.css?v='.rawurlencode($cssAssetVersion))?>" rel="stylesheet">
    <link rel="icon" href="<?= e($assetBase . '/assets/images/branding/favicon-32x32.png') ?>" sizes="32x32" type="image/png">
    <link rel="icon" href="<?= e($assetBase . '/assets/images/branding/favicon-16x16.png') ?>" sizes="16x16" type="image/png">
    <link rel="shortcut icon" href="<?= e($assetBase . '/favicon.ico') ?>">
    <link rel="apple-touch-icon" href="<?= e($assetBase . '/assets/images/branding/apple-touch-icon.png') ?>">
</head>
<body class="updater-body">
<div class="updater-shell">
<aside class="updater-sidebar">
  <div class="updater-brand"><img class="updater-brand__logo" src="<?= e($assetBase . '/assets/images/branding/barama-mark-256.png') ?>" alt="" width="42" height="42" aria-hidden="true"><div><strong>Barama Real Estate</strong><span>Secure Updater</span></div></div>
  <ol class="updater-progress" aria-label="Upgrade progress">
    <li class="is-complete"><span class="updater-progress__marker"><?=app_icon('check')?></span><span><strong>Existing installation</strong><small><?=e($currentVersion)?></small></span></li>
    <li class="<?=$finished?'is-complete':'is-active'?>"><span class="updater-progress__marker"><?=$finished?app_icon('check'):'2'?></span><span><strong>Authenticate &amp; upgrade</strong><small>Administrator confirmation</small></span></li>
    <li class="<?=$finished?'is-active':''?>"><span class="updater-progress__marker">3</span><span><strong>Finish</strong><small>Verify the updated version</small></span></li>
  </ol>
  <div class="updater-sidebar__footer"><span>Target version</span><strong>v<?=e($targetVersion)?></strong></div>
</aside>
<section class="updater-workspace">
<header class="updater-topbar">
  <div class="updater-heading"><span class="eyebrow">Release upgrade</span><h1>Upgrade Barama Real Estate</h1><p>Update <?=e($currentVersion)?> → <?=e($targetVersion)?>. The updater applies only changes required by this package.</p></div>
  <div class="updater-utilities"><span class="updater-target"><small>Target</small><strong><?=e($targetVersion)?></strong></span><button class="updater-theme" type="button" data-theme-toggle aria-label="Toggle light and dark mode" title="Toggle theme"><?=app_icon('eye')?><span class="visually-hidden">Toggle theme</span></button></div>
</header>
<main class="updater-content">
<?php if(is_string($error)&&$error!==''):?><div class="updater-notice is-error" role="alert"><?=app_icon('alert')?><span><?=e($error)?></span></div><?php endif;?>
<?php if($completed):?>
<section class="updater-result"><span class="updater-result__icon"><?=app_icon('check')?></span><span class="eyebrow">Upgrade complete</span><h2>Barama Real Estate is now <?=e((string)$result['version'])?>.</h2><p><?=e((string)$migrationsApplied)?> migration<?=$migrationsApplied===1?'':'s'?> applied. The installed version has been recorded successfully.</p>
<dl class="updater-result__summary"><div><dt>Previous version</dt><dd><?=e((string)($result['previous_version']??$currentVersion))?></dd></div><div><dt>Current version</dt><dd><?=e((string)($result['version']??$targetVersion))?></dd></div><div><dt>Migrations applied</dt><dd><?=e((string)$migrationsApplied)?></dd></div><div><dt>Status</dt><dd><span class="updater-badge is-ready">Healthy</span></dd></div></dl>
<div class="updater-result__actions"><a class="btn btn-primary" href="<?=e($homepage)?>">Open website</a><a class="btn btn-outline-secondary" href="<?=e(rtrim($homepage,'/').'/dashboard')?>">Open dashboard</a></div></section>
<?php elseif($upToDate||$alreadyCurrent):?>
<section class="updater-result"><span class="updater-result__icon"><?=app_icon('check')?></span><span class="eyebrow">Up to date</span><h2>No upgrade is required.</h2><p>The installed application version and database schema already match <?=e($targetVersion)?>.</p><div class="updater-result__actions"><a class="btn btn-primary" href="<?=e($assetBase.'/dashboard/system/auto-updater')?>">Return to Update Center</a></div></section>
<?php else:?>
<section class="updater-stage">
  <div class="updater-stage__intro"><span class="updater-stage__icon"><?=app_icon('shield')?></span><div><h2>Upgrade authorization</h2><p>Review the upgrade summary, authenticate an active administrator, and confirm your current backup.</p></div></div>
  <div class="updater-summary" aria-label="Upgrade summary">
    <div class="updater-summary__row"><span><small>Installed version</small><strong><?=e($currentVersion)?></strong></span><span class="updater-badge">Detected</span></div>
    <div class="updater-summary__row"><span><small>Target version</small><strong><?=e($targetVersion)?></strong></span><span class="updater-badge is-target">Package</span></div>
    <div class="updater-summary__row"><span><small>Pending migrations</small><strong><?=count($pending)===0?'None':e((string)count($pending))?></strong></span><span class="updater-badge"><?=e((string)count($pending))?></span></div>
    <div class="updater-summary__row"><span><small>Upgrade preflight</small><strong><?=$ready?'Runtime, database, license and storage are ready.':'Action is required before upgrading.'?></strong></span><span class="updater-badge <?=$ready?'is-ready':'is-blocked'?>"><?=$ready?'Ready':'Fix required'?></span></div>
  </div>
  <?php if($issues!==[]):?><div class="updater-issues" role="alert"><strong>Resolve before continuing</strong><?php foreach($issues as $issue):?><div><?=app_icon('alert')?><span><?=e((string)$issue)?></span></div><?php endforeach;?></div><?php endif;?>
  <?php if($pending!==[]):?><details class="updater-migrations"><summary>View <?=e((string)count($pending))?> pending migration<?=count($pending)===1?'':'s'?></summary><div><?php foreach($pending as $migration):?><span><?=app_icon('layers')?> <?=e((string)$migration)?></span><?php endforeach;?></div></details><?php endif;?>
  <form action="<?=e($updaterUrl)?>" method="post" class="updater-form"><?=csrf_field()?>
    <div class="updater-form__head"><h2>Administrator authorization</h2><p>Sign in with an active administrator account to authorize this upgrade.</p></div>
    <div class="updater-form__grid">
      <label class="updater-field"><span>Administrator email</span><span class="updater-input"><span class="updater-input__icon"><?=app_icon('mail')?></span><input class="form-control" type="email" name="admin_email" maxlength="191" autocomplete="username" required></span></label>
      <label class="updater-field"><span>Administrator password</span><span class="updater-input"><span class="updater-input__icon"><?=app_icon('lock')?></span><input class="form-control" type="password" name="admin_password" autocomplete="current-password" required></span></label>
    </div>
    <label class="updater-backup"><input type="checkbox" name="backup_confirmed" value="1" required><span><strong>I created a current database and files backup.</strong><small>Keep the backup until the upgraded website has been validated.</small></span></label>
    <div class="updater-form__actions"><p><?=app_icon('shield')?><span>The updater validates the installed activation. Your original purchase code is not required.</span></p><button class="btn btn-primary" type="submit" <?=$ready?'':'disabled'?>>Authenticate &amp; Upgrade</button></div>
  </form>
</section>
<?php endif;?>
</main></section></div>
<script src="<?=e($assetBase.'/assets/js/frontend.js?v='.rawurlencode($jsAssetVersion))?>" defer></script>
<script type="application/x-barama-module" data-barama-module="app"></script>
</body></html>
