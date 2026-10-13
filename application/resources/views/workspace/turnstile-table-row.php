<?php
/** Security features table row; deliberately not a standalone page. */
try {
    $brTsRowStatus = \Barama\Security\TurnstileGuard::status();
    $brTsRowLabel = !empty($brTsRowStatus['enabled']) ? 'Enabled'
        : (!empty($brTsRowStatus['configured']) ? 'Disabled' : 'Not configured');
} catch (\Throwable) {
    $brTsRowLabel = 'Unavailable';
}
$brTsRowCss = $brTsRowLabel === 'Enabled' ? 'enabled' : ($brTsRowLabel === 'Disabled' ? 'disabled' : '');
?>
<!-- BARAMA_TURNSTILE_SECURITY_TABLE_ROW_V111 -->
<tr class="br-ts-table-row">
  <td><div class="br-ts-feature">
    <span class="br-ts-feature-icon" aria-hidden="true"><?= app_icon('shield') ?></span>
    <div><div class="br-ts-feature-title">Cloudflare Turnstile</div>
    <div class="br-ts-feature-description">Managed CAPTCHA and bot protection</div></div>
  </div></td>
  <td><span class="br-ts-status <?= e($brTsRowCss) ?>"><?= e($brTsRowLabel) ?></span></td>
  <td><div class="br-ts-td-description">Verify visitors on selected forms using real Cloudflare credentials and server-side Siteverify.</div></td>
  <td><button type="button" class="br-ts-row-btn" data-br-ts-open aria-haspopup="dialog" aria-controls="br-ts-overlay">Configure</button></td>
</tr>
