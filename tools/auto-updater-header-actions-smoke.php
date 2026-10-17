<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$view=file_get_contents($root.'/application/resources/views/workspace/system-center.php');
$css=file_get_contents($root.'/assets/css/barama-auto-updater-actions-v122.css');
$controller=file_get_contents($root.'/application/app/Http/Controllers/SystemCenterController.php');
$feed=file_get_contents($root.'/application/app/Services/Updates/TrustedReleaseFeed.php');
if (!is_string($view)||!is_string($css)||!is_string($controller)||!is_string($feed)) exit(1);
$assert=static function(bool $pass,string $label):void {
    if (!$pass) {fwrite(STDERR,'FAIL '.$label.PHP_EOL);exit(1);}
    echo 'PASS '.$label.PHP_EOL;
};
$assert(substr_count($view,"asset('css/barama-auto-updater-actions-v122.css')")===1,'Only Auto Updater loads v1.2.2 external CSS');
$assert(substr_count($view,"action=\"<?= e(url('/dashboard/system/auto-updater/check-updates'))")===1,'Exactly one CSRF-protected update check');
$assert(substr_count($view,"action=\"<?= e(url('/dashboard/system/auto-updater/check-license'))")===1,'Exactly one license check');
$assert(str_contains($view,'class="bsys-update-header-actions"') && str_contains($view,'class="bsys-license-action-row"'),'Actions at header and license hero');
$assert(str_contains($view,'aria-label="Staged release inspection"') && str_contains($view,'inspect-staged-release'),'Staged inspection remains available');
$assert(substr_count($view,'Request domain replacement <span aria-hidden="true">↗</span>')===1,'Domain replacement appears only once');
$assert(substr_count($view,'License assistance <span aria-hidden="true">↗</span>')===1,'License assistance appears only once');
$assert(str_contains($view,'data-publisher-check-form') && str_contains($view,'data-publisher-check-button'),'Existing license JS binding unchanged');
$assert(str_contains($view,'systemFeedReadiness') && str_contains($view,'systemUpdaterPresent'),'Publisher and updater readiness gating preserved');
$assert(!str_contains($view,'<style>') && !str_contains($view,'style="'),'No inline CSS in system center');
$assert(str_contains($css,'.bsys-update-header-actions') && str_contains($css,'.bsys-license-action-row'),'New controls styled');
$assert(str_contains($css,'var(--bp-panel)') && str_contains($css,'var(--bp-text)'),'Theme color tokens preserved');
$assert(str_contains($css,'@media (max-width: 630px)'),'Responsive wrapping for smaller screens');
$assert(str_contains($feed,"['entitlement_denied', 'entitlement_not_configured']") && str_contains($feed,"$"."result['reason']"),'Publisher error mapping kept to two allowed codes');
$assert(str_contains($controller,"($"."result['reason'] ?? '') === 'not_configured'"),'Controller shows specific entitlement reason');
echo 'AUTO_UPDATER_ACTIONS_THEME_CONTRACT=PASS'.PHP_EOL;
