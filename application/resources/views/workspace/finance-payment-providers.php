<?php

declare(strict_types=1);

$providers = is_array($paymentProviders ?? null) ? $paymentProviders : [];
$events = is_array($webhookEvents ?? null) ? $webhookEvents : [];

// Payment Providers Webhook Pagination Standard v1.8.11.
$webhookQ = substr(trim((string) ($_GET['webhook_q'] ?? '')), 0, 120);
$webhookStatus = trim((string) ($_GET['webhook_status'] ?? ''));

$webhookStatuses = [];
foreach ($events as $event) {
    $candidateStatus = trim((string) ($event['status'] ?? ''));
    if ($candidateStatus !== '') {
        $webhookStatuses[$candidateStatus] = true;
    }
}
$webhookStatuses = array_keys($webhookStatuses);
sort($webhookStatuses, SORT_NATURAL | SORT_FLAG_CASE);

if ($webhookStatus !== '' && !in_array($webhookStatus, $webhookStatuses, true)) {
    $webhookStatus = '';
}

$filteredWebhookEvents = array_values(array_filter(
    $events,
    static function (array $event) use ($webhookQ, $webhookStatus): bool {
        if ($webhookStatus !== '' && (string) ($event['status'] ?? '') !== $webhookStatus) {
            return false;
        }

        if ($webhookQ === '') {
            return true;
        }

        $haystack = strtolower(
            (string) ($event['provider_name'] ?? '')
            . ' ' . (string) ($event['provider_code'] ?? '')
            . ' ' . (string) ($event['event_id'] ?? '')
            . ' ' . (string) ($event['event_type'] ?? '')
            . ' ' . (string) ($event['status'] ?? '')
            . ' ' . (string) ($event['transaction_reference'] ?? '')
            . ' ' . (string) ($event['payload_sha256'] ?? '')
            . ' ' . (string) ($event['error_message'] ?? '')
        );

        return str_contains($haystack, strtolower($webhookQ));
    }
));

$webhookPerPage = 10;
$webhookTotal = count($filteredWebhookEvents);
$webhookPages = max(1, (int) ceil($webhookTotal / $webhookPerPage));
$webhookPage = filter_var(
    $_GET['webhook_page'] ?? 1,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);
$webhookPage = $webhookPage === false ? 1 : min((int) $webhookPage, $webhookPages);

$visibleWebhookEvents = array_slice(
    $filteredWebhookEvents,
    ($webhookPage - 1) * $webhookPerPage,
    $webhookPerPage
);

$webhookQuery = array_filter(
    [
        'webhook_q' => $webhookQ,
        'webhook_status' => $webhookStatus,
    ],
    static fn (string $value): bool => $value !== ''
);

$webhookPageUrl = static function (int $target) use ($webhookQuery): string {
    return url(
        '/dashboard/payment-providers?'
        . http_build_query(array_merge($webhookQuery, ['webhook_page' => max(1, $target)]))
    );
};

$summary = is_array($paymentProviderSummary ?? null) ? $paymentProviderSummary : [];
$methods = is_array($paymentMethods ?? null) ? $paymentMethods : [];
$methodSummary = is_array($paymentMethodSummary ?? null) ? $paymentMethodSummary : [];
$paymentNativeNotice = trim((string) ($_GET['payment_notice'] ?? ''));
$paymentNativeError = trim((string) ($_GET['payment_error'] ?? ''));
?>
<section class="workspace-page finance-vertical-cards-page finance-vertical-cards-page--payment-providers">
    <!-- BARAMA_PAYMENT_PROVIDERS_PREMIUM_V100 -->
    <link rel="stylesheet" href="<?= e(asset('css/finance-payment-providers-v200.css')) ?>?v=200">
    <script defer src="<?= e(asset('js/finance-payment-providers-v200.js')) ?>?v=200"></script>
    <!-- BARAMA_PAYMENT_METHODS_V110 -->
    <!-- BARAMA_PAYMENT_METHODS_CHANGE_CONFIRMATION_V111 -->
    <!-- BARAMA_PAYMENT_METHODS_CHANGE_CONFIRMATION_V112 -->
    <!-- BARAMA_PAYMENT_METHODS_CHANGE_CONFIRMATION_REPLAY_V113 -->
    <!-- BARAMA_PAYMENT_METHODS_CHANGE_CONFIRMATION_WATCHDOG_V114 -->
    <!-- BARAMA_PAYMENT_METHODS_CONFIRMATION_RESULT_V115 -->
    <!-- BARAMA_PAYMENT_METHODS_CONFIRMATION_COMPLETION_V116 -->
    <!-- BARAMA_PAYMENT_METHODS_CONFIRMATION_LIVE_FORM_V117 -->
    <!-- BARAMA_PAYMENT_METHODS_NATIVE_CONFIRMATION_V120 -->
    <link rel="stylesheet" href="<?= e(asset('css/finance-payment-methods.css')) ?>?v=1.0.0-clean2">
    <script defer src="<?= e(asset('js/finance-payment-methods.js')) ?>?v=1.0.0-clean2"></script>
    <!-- BARAMA_FINANCE_VERTICAL_CARDS_V100 -->
    <div class="workspace-page__header">
        <div>
            <span class="eyebrow"><?= e($workspaceLabel ?? 'Admin Control Center') ?></span>
            <h1>Payment Providers</h1>
            <p>Configure provider-neutral webhook endpoints and review immutable incoming payment event receipts.</p>
        </div>
        <button
            class="payment-provider-header-action"
            type="button"
            data-provider-dialog-open="payment-provider-add-dialog"
        >
            <?= app_icon('plus') ?>
            <span>Add Payment Provider</span>
        </button>
    </div>

    <div data-workspace-notice hidden></div>

    <!-- BARAMA_PAYMENT_NATIVE_POST_V128 -->
    <?php if ($paymentNativeNotice !== ''): ?>
        <div class="payment-native-result payment-native-result--success" role="status">
            <div><strong>Saved successfully</strong><span><?= e($paymentNativeNotice) ?></span></div>
        </div>
    <?php elseif ($paymentNativeError !== ''): ?>
        <div class="payment-native-result payment-native-result--error" role="alert">
            <div><strong>Payment change failed</strong><span><?= e($paymentNativeError) ?></span></div>
        </div>
    <?php endif; ?>

    <div class="workspace-stat-grid finance-summary-cards">
        <div class="workspace-stat"><span><?= app_icon('layers') ?></span><strong><?= e(number_format((int) ($methodSummary['total'] ?? 0))) ?></strong><small>Payment methods</small></div>
        <div class="workspace-stat"><span><?= app_icon('check') ?></span><strong><?= e(number_format((int) ($methodSummary['active'] ?? 0))) ?></strong><small>Enabled</small></div>
        <div class="workspace-stat"><span><?= app_icon('pipeline') ?></span><strong><?= e(number_format((int) ($methodSummary['automatic'] ?? 0))) ?></strong><small>Automatic</small></div>
        <div class="workspace-stat"><span><?= app_icon('settings') ?></span><strong><?= e(number_format((int) ($methodSummary['manual'] ?? 0))) ?></strong><small>Manual</small></div>
    </div>

    <dialog class="payment-provider-dialog" id="payment-provider-add-dialog">
        <!-- BARAMA_PAYMENT_METHODS_V120 -->
        <div class="payment-provider-dialog__shell">
            <header class="payment-provider-dialog__header">
                <div>
                    <span class="payment-provider-dialog__eyebrow">Payment Setup</span>
                    <h2>Add Payment Provider</h2>
                    <p>Choose Automatic Payment for gateway infrastructure or Manual Payment for customer transfer instructions.</p>
                </div>
                <button class="payment-provider-dialog__close" type="button" data-provider-dialog-close aria-label="Close"><?= app_icon('close') ?></button>
            </header>
            <div class="payment-provider-dialog__body">
                <div class="payment-add-kind" role="tablist" aria-label="Payment type">
                    <button class="is-active" type="button" aria-selected="true" data-payment-add-kind="automatic"><?= app_icon('pipeline') ?> Automatic Payment</button>
                    <button type="button" aria-selected="false" data-payment-add-kind="manual"><?= app_icon('layers') ?> Manual Payment</button>
                </div>

                <div data-payment-add-panel="automatic">
                    <form action="<?= e(url('/dashboard/payment-providers')) ?>" method="post" data-workspace-form data-payment-native-confirmed-form="1" class="payment-provider-form">
                        <?= csrf_field() ?>
                        <div class="payment-provider-form__grid">
                            <label>
                                <span>Provider code</span>
                                <input class="form-control" name="code" maxlength="24" required placeholder="wing_payment" data-payment-normalize-code>
                                <small>Lowercase internal code, for example <code>wing_payment</code>.</small>
                            </label>
                            <label>
                                <span>Provider name</span>
                                <input class="form-control" name="name" maxlength="160" required placeholder="Wing Payment">
                            </label>
                            <label>
                                <span>Environment</span>
                                <select class="form-select" name="environment" required>
                                    <option value="sandbox">Sandbox</option>
                                    <option value="live">Live</option>
                                </select>
                            </label>
                            <label>
                                <span>Adapter</span>
                                <input class="form-control" value="Generic token" readonly>
                            </label>
                            <label class="is-span-2">
                                <span>Webhook token · 32–191 characters</span>
                                <div class="input-group">
                                    <input class="form-control" id="payment-provider-add-token" name="webhook_token" type="password" minlength="32" maxlength="191" autocomplete="new-password" required>
                                    <button class="btn btn-outline-secondary" type="button" data-provider-token-toggle="payment-provider-add-token">Show</button>
                                </div>
                            </label>
                        </div>
                        <div class="payment-provider-security-note">
                            <?= app_icon('shield') ?>
                            <span>Automatic providers use gateway/webhook infrastructure. Provider-specific checkout logic is required for actual customer charging.</span>
                        </div>
                        <div class="payment-provider-form__footer"><span></span><div class="payment-provider-form__actions">
                            <button class="btn btn-outline-secondary" type="button" data-provider-dialog-close>Cancel</button>
                            <button class="btn btn-primary" type="submit"><?= app_icon('plus') ?> Create Automatic Provider</button>
                        </div></div>
                    </form>
                </div>

                <div data-payment-add-panel="manual" hidden>
                    <form action="<?= e(url('/dashboard/payment-methods')) ?>" method="post" enctype="multipart/form-data" data-workspace-form data-payment-native-confirmed-form="1" class="payment-provider-form">
                        <?= csrf_field() ?>
                        <div class="payment-provider-form__grid">
                            <label><span>Payment name</span><input class="form-control" name="name" maxlength="160" required placeholder="Wing Manual Payment" data-payment-code-source="#manual-payment-code"></label>
                            <label><span>Internal code</span><input class="form-control" id="manual-payment-code" name="code" maxlength="40" required placeholder="wing_manual_payment" data-payment-normalize-code></label>
                            <label><span>Recipient name</span><input class="form-control" name="recipient_name" maxlength="160" required></label>
                            <label><span>Account number</span><input class="form-control" name="account_number" maxlength="160" required></label>
                            <label><span>Currency</span><input class="form-control" name="currency" maxlength="3" value="USD" required></label>
                            <label><span>Processing days</span><input class="form-control" name="processing_days" type="number" min="0" max="30" value="1" required></label>
                            <label class="is-span-2">
                                <span>QR code upload · PNG/JPG/WebP · max 5 MB</span>
                                <input class="form-control" name="qr_code" type="file" accept="image/png,image/jpeg,image/webp" data-payment-qr-input="#manual-payment-qr-preview">
                                <span class="payment-qr-preview"><img id="manual-payment-qr-preview" alt="QR preview" hidden></span>
                            </label>
                            <label class="is-span-2"><span>Instructions before Payment selection</span><textarea class="form-control" name="instructions_before" rows="4" maxlength="5000"></textarea></label>
                            <label class="is-span-2"><span>Instructions after Payment / receipt upload</span><textarea class="form-control" name="instructions_after" rows="4" maxlength="5000"></textarea></label>
                            <label class="is-span-2"><span>Payment transfer terms title</span><input class="form-control" name="terms_title" maxlength="160" value="Payment transfer terms and conditions"></label>
                            <label class="is-span-2">
                                <span>Payment transfer terms and conditions</span>
                                <textarea class="form-control" name="terms_content" rows="6" maxlength="10000"></textarea>
                                <button class="btn btn-outline-secondary" type="button" data-payment-terms-preview>Preview terms popup</button>
                            </label>
                            <div class="payment-manual-checks is-span-2">
                                <label><input type="checkbox" name="receipt_required" value="1" checked> Require customer Receipt Upload</label>
                                <label><input type="checkbox" name="terms_required" value="1" checked> Require payment-transfer terms checkbox</label>
                                <label><input type="checkbox" name="approval_required" value="1" checked> Require administrator approval</label>
                            </div>
                        </div>
                        <div class="payment-manual-note">Manual Payment does not require a webhook token. Account, QR, receipt, instruction and terms settings are stored in the governed payment-method catalog.</div>
                        <div class="payment-provider-form__footer"><span></span><div class="payment-provider-form__actions">
                            <button class="btn btn-outline-secondary" type="button" data-provider-dialog-close>Cancel</button>
                            <button class="btn btn-primary" type="submit"><?= app_icon('plus') ?> Create Manual Payment</button>
                        </div></div>
                    </form>
                </div>
            </div>
        </div>
    </dialog>




    <section class="workspace-table-section payment-methods-section">
        <!-- BARAMA_PAYMENT_METHODS_CHANGE_CONFIRMATION_V112_VALID_DOM -->
        <div class="workspace-table-head">
            <div>
                <h2>Payment methods</h2>
                <p>Manage automatic and manual payment options from one governed table. Configure a method before turning it ON.</p>
            </div>
            <div class="payment-methods-section__summary">
                <span class="payment-methods-summary-pill"><?= e(number_format((int) ($methodSummary['configured'] ?? 0))) ?> configured</span>
                <span class="payment-methods-summary-pill"><?= e(number_format((int) ($methodSummary['active'] ?? 0))) ?> enabled</span>
            </div>
        </div>

        <div class="payment-methods-table-wrap">
            <table class="workspace-table payment-methods-table">
                <thead>
                    <tr>
                        <th>Method</th>
                        <th>Type</th>
                        <th>Mode</th>
                        <th>Configuration</th>
                        <th>Status</th>
                        <th>Enabled</th>
                        <th>Configure</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($methods as $method): ?>
                        <?php
                        $methodId = (int) ($method['id'] ?? 0);
                        $code = (string) ($method['code'] ?? '');
                        $type = (string) ($method['method_type'] ?? 'manual');
                        $status = (string) ($method['status'] ?? 'inactive');
                        $configured = (bool) ($method['is_configured'] ?? false);
                        $config = is_array($method['public_config'] ?? null)
                            ? $method['public_config']
                            : [];
                        $isActive = $status === 'active';
                        $dialogId = 'payment-method-config-' . $methodId;
                        $modePrimary = $type === 'automatic'
                            ? ucfirst((string) ($config['environment'] ?? 'sandbox'))
                            : 'Manual approval';
                        $modeSecondary = $type === 'automatic'
                            ? (string) ($config['currency'] ?? 'USD')
                            : ($code === 'bank_transfer'
                                ? (string) ($config['currency'] ?? 'USD')
                                : 'Admin managed');
                        ?>
                        <tr>
                            <td>
                                <div class="payment-method-identity">
                                    <span class="payment-method-icon"><?= app_icon($type === 'automatic' ? 'pipeline' : ($code === 'bank_transfer' ? 'building' : 'layers')) ?></span>
                                    <span class="payment-method-identity__copy">
                                        <strong><?= e((string) ($method['name'] ?? 'Payment method')) ?></strong>
                                        <small><?= e($code) ?></small>
                                    </span>
                                </div>
                            </td>
                            <td><span class="payment-method-kind<?= $type === 'automatic' ? ' is-auto' : '' ?>"><?= e($type === 'automatic' ? 'Auto Payment' : 'Manual Payment') ?></span></td>
                            <td><div class="payment-method-mode"><strong><?= e($modePrimary) ?></strong><small><?= e($modeSecondary) ?></small></div></td>
                            <td><span class="payment-method-config-state<?= $configured ? ' is-ready' : '' ?>"><?= e($configured ? 'Configured' : 'Setup required') ?></span></td>
                            <td><span class="status-badge status-badge--<?= e($isActive ? 'approved' : 'pending') ?>"><?= e($isActive ? 'Enabled' : 'Disabled') ?></span></td>
                            <td>
                                <form id="payment-method-status-form-<?= e((string) $methodId) ?>" class="payment-method-switch-form" action="<?= e(url('/dashboard/payment-methods/' . $methodId . '/status')) ?>" method="post" data-workspace-form data-change-confirmation-stable-form="1" data-payment-native-confirmed-form="1">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="status" value="<?= e($isActive ? 'inactive' : 'active') ?>">
                                    <label class="payment-method-switch<?= (!$configured && !$isActive) ? ' is-disabled' : '' ?>" title="<?= e((!$configured && !$isActive) ? 'Configure this method before enabling it' : ($isActive ? 'Turn method OFF' : 'Turn method ON')) ?>">
                                        <input type="checkbox" data-payment-method-toggle <?= $isActive ? 'checked' : '' ?> <?= (!$configured && !$isActive) ? 'disabled' : '' ?> aria-label="<?= e($isActive ? 'Disable payment method' : 'Enable payment method') ?>">
                                        <span class="payment-method-switch__track" aria-hidden="true"></span>
                                        <span class="payment-method-switch__label"><?= e($isActive ? 'ON' : 'OFF') ?></span>
                                    </label>
                                </form>
                            </td>
                            <td>
                                <button class="payment-method-configure" type="button" data-payment-method-dialog-open="<?= e($dialogId) ?>">
                                    <?= app_icon('settings') ?><span>Configure</span>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                </tbody>
            </table>
        </div>

        <?php foreach ($methods as $method): ?>
<?php
                        $methodId = (int) ($method['id'] ?? 0);
                        $code = (string) ($method['code'] ?? '');
                        $type = (string) ($method['method_type'] ?? 'manual');
                        $status = (string) ($method['status'] ?? 'inactive');
                        $configured = (bool) ($method['is_configured'] ?? false);
                        $config = is_array($method['public_config'] ?? null)
                            ? $method['public_config']
                            : [];
                        $isActive = $status === 'active';
                        $dialogId = 'payment-method-config-' . $methodId;
                        $modePrimary = $type === 'automatic'
                            ? ucfirst((string) ($config['environment'] ?? 'sandbox'))
                            : 'Manual approval';
                        $modeSecondary = $type === 'automatic'
                            ? (string) ($config['currency'] ?? 'USD')
                            : ($code === 'bank_transfer'
                                ? (string) ($config['currency'] ?? 'USD')
                                : 'Admin managed');
                        ?>
<dialog class="payment-method-dialog" id="<?= e($dialogId) ?>">
                            <div class="payment-method-dialog__shell">
                                <header class="payment-method-dialog__header">
                                    <div>
                                        <span><?= e($type === 'automatic' ? 'Automatic Payment' : 'Manual Payment') ?></span>
                                        <h2><?= e((string) ($method['name'] ?? 'Payment method')) ?></h2>
                                        <p><?= e($type === 'automatic'
                                            ? 'Credentials are encrypted at rest. Leave existing secret fields blank to preserve them.'
                                            : 'Configure the customer-facing instructions and operational details for this method.') ?></p>
                                    </div>
                                    <button class="payment-method-dialog__close" type="button" data-payment-method-dialog-close aria-label="Close">×</button>
                                </header>

                                <div class="payment-method-dialog__body">
                                    <form id="payment-method-config-form-<?= e((string) $methodId) ?>" class="payment-method-form" action="<?= e(url('/dashboard/payment-methods/' . $methodId . '/configure')) ?>" method="post" data-workspace-form data-change-confirmation-stable-form="1" data-payment-native-confirmed-form="1">
                                        <?= csrf_field() ?>
                                        <div class="payment-method-form__grid">
                                            <?php if ($code === 'paypal'): ?>
                                                <label><span>Environment</span>
                                                    <select class="form-select" name="environment" required>
                                                        <option value="sandbox" <?= (string) ($config['environment'] ?? 'sandbox') === 'sandbox' ? 'selected' : '' ?>>Sandbox</option>
                                                        <option value="live" <?= (string) ($config['environment'] ?? '') === 'live' ? 'selected' : '' ?>>Live</option>
                                                    </select>
                                                </label>
                                                <label><span>Currency</span><input class="form-control" name="currency" maxlength="3" value="<?= e((string) ($config['currency'] ?? 'USD')) ?>" required></label>
                                                <?php foreach (['client_id'=>'Client ID','client_secret'=>'Client Secret','webhook_id'=>'Webhook ID · optional'] as $field=>$label): ?>
                                                    <?php $fieldId = 'payment-method-' . $methodId . '-' . $field; ?>
                                                    <label class="<?= $field === 'webhook_id' ? 'is-span-2' : '' ?>">
                                                        <span><?= e($label) ?></span>
                                                        <div class="payment-method-secret-field">
                                                            <input class="form-control" id="<?= e($fieldId) ?>" name="<?= e($field) ?>" type="password" maxlength="500" autocomplete="new-password" placeholder="<?= e((bool) ($method['has_secrets'] ?? false) ? 'Configured — leave blank to keep' : 'Enter credential') ?>">
                                                            <button class="payment-method-secret-toggle" type="button" data-payment-secret-toggle="<?= e($fieldId) ?>">Show</button>
                                                        </div>
                                                    </label>
                                                <?php endforeach; ?>

                                            <?php elseif ($code === 'stripe'): ?>
                                                <label><span>Environment</span>
                                                    <select class="form-select" name="environment" required>
                                                        <option value="test" <?= (string) ($config['environment'] ?? 'test') === 'test' ? 'selected' : '' ?>>Test</option>
                                                        <option value="live" <?= (string) ($config['environment'] ?? '') === 'live' ? 'selected' : '' ?>>Live</option>
                                                    </select>
                                                </label>
                                                <label><span>Currency</span><input class="form-control" name="currency" maxlength="3" value="<?= e((string) ($config['currency'] ?? 'USD')) ?>" required></label>
                                                <label class="is-span-2"><span>Publishable key</span><input class="form-control" name="publishable_key" maxlength="255" value="<?= e((string) ($config['publishable_key'] ?? '')) ?>" placeholder="pk_test_..." required></label>
                                                <?php foreach (['secret_key'=>'Secret key','webhook_secret'=>'Webhook signing secret · optional'] as $field=>$label): ?>
                                                    <?php $fieldId = 'payment-method-' . $methodId . '-' . $field; ?>
                                                    <label class="<?= $field === 'webhook_secret' ? 'is-span-2' : '' ?>">
                                                        <span><?= e($label) ?></span>
                                                        <div class="payment-method-secret-field">
                                                            <input class="form-control" id="<?= e($fieldId) ?>" name="<?= e($field) ?>" type="password" maxlength="500" autocomplete="new-password" placeholder="<?= e((bool) ($method['has_secrets'] ?? false) ? 'Configured — leave blank to keep' : 'Enter credential') ?>">
                                                            <button class="payment-method-secret-toggle" type="button" data-payment-secret-toggle="<?= e($fieldId) ?>">Show</button>
                                                        </div>
                                                    </label>
                                                <?php endforeach; ?>

                                            <?php elseif ($code === 'offline'): ?>
                                                <label><span>Display title</span><input class="form-control" name="display_title" maxlength="120" value="<?= e((string) ($config['display_title'] ?? 'Offline Payment')) ?>" required></label>
                                                <label><span>Processing days</span><input class="form-control" name="processing_days" type="number" min="0" max="30" value="<?= e((string) ($config['processing_days'] ?? 1)) ?>" required></label>
                                                <label class="is-span-2"><span>Instructions before Payment selection</span><textarea class="form-control" name="instructions_before" rows="5" maxlength="5000"><?= e((string) ($config['instructions_before'] ?? $config['instructions'] ?? '')) ?></textarea></label>
                                                <label><span>Recipient name</span><input class="form-control" name="recipient_name" maxlength="160" value="<?= e((string) ($config['recipient_name'] ?? $config['account_name'] ?? '')) ?>" required></label>
                                                <label><span>Account number</span><input class="form-control" name="account_number" maxlength="160" value="<?= e((string) ($config['account_number'] ?? '')) ?>" required></label>
                                                <label><span>Currency</span><input class="form-control" name="currency" maxlength="3" value="<?= e((string) ($config['currency'] ?? 'USD')) ?>" required></label>
                                                <label><span>QR code upload</span><input class="form-control" name="qr_code" type="file" accept="image/png,image/jpeg,image/webp"></label>
                                                <?php if (!empty($config['qr_code_url'])): ?><div class="payment-qr-preview is-span-2"><img src="<?= e((string) $config['qr_code_url']) ?>" alt="Current payment QR"><label><input type="checkbox" name="remove_qr_code" value="1"> Remove current QR code</label></div><?php endif; ?>
                                                <label class="is-span-2"><span>Instructions after Payment</span><textarea class="form-control" name="instructions_after" rows="4" maxlength="5000"><?= e((string) ($config['instructions_after'] ?? '')) ?></textarea></label>
                                                <label class="is-span-2"><span>Payment transfer terms title</span><input class="form-control" name="terms_title" maxlength="160" value="<?= e((string) ($config['terms_title'] ?? 'Payment transfer terms and conditions')) ?>"></label>
                                                <label class="is-span-2"><span>Payment transfer terms and conditions</span><textarea class="form-control" name="terms_content" rows="5" maxlength="10000"><?= e((string) ($config['terms_content'] ?? '')) ?></textarea><button class="btn btn-outline-secondary" type="button" data-payment-terms-preview>Preview terms popup</button></label>
                                                <div class="payment-manual-checks is-span-2"><label><input type="checkbox" name="receipt_required" value="1" <?= !empty($config['receipt_required']) || !empty($config['proof_required']) ? 'checked' : '' ?>> Require customer Receipt Upload</label><label><input type="checkbox" name="terms_required" value="1" <?= !empty($config['terms_required']) ? 'checked' : '' ?>> Require transfer-terms agreement checkbox</label><label><input type="checkbox" name="approval_required" value="1" checked> Require administrator approval</label></div>

                                            <?php elseif ($code === 'bank_transfer'): ?>
                                                <label><span>Bank name</span><input class="form-control" name="bank_name" maxlength="160" value="<?= e((string) ($config['bank_name'] ?? '')) ?>" required></label>
                                                <label><span>Account name</span><input class="form-control" name="account_name" maxlength="160" value="<?= e((string) ($config['account_name'] ?? '')) ?>" required></label>
                                                <label><span>Account number</span><input class="form-control" name="account_number" maxlength="120" value="<?= e((string) ($config['account_number'] ?? '')) ?>" required></label>
                                                <label><span>Currency</span><input class="form-control" name="currency" maxlength="3" value="<?= e((string) ($config['currency'] ?? 'USD')) ?>" required></label>
                                                <label><span>SWIFT / BIC</span><input class="form-control" name="swift_code" maxlength="32" value="<?= e((string) ($config['swift_code'] ?? '')) ?>"></label>
                                                <label><span>Branch</span><input class="form-control" name="branch" maxlength="160" value="<?= e((string) ($config['branch'] ?? '')) ?>"></label>
                                                <label class="is-span-2"><span>Transfer instructions</span><textarea class="form-control" name="instructions" rows="5" maxlength="5000"><?= e((string) ($config['instructions'] ?? '')) ?></textarea></label>
                                                <label class="is-span-2"><span>QR code upload · PNG/JPG/WebP · max 5 MB</span><input class="form-control" name="qr_code" type="file" accept="image/png,image/jpeg,image/webp"></label>
                                                <?php if (!empty($config['qr_code_url'])): ?><div class="payment-qr-preview is-span-2"><img src="<?= e((string) $config['qr_code_url']) ?>" alt="Current payment QR"><label><input type="checkbox" name="remove_qr_code" value="1"> Remove current QR code</label></div><?php endif; ?>
                                                <label class="is-span-2"><span>Instructions after Payment</span><textarea class="form-control" name="instructions_after" rows="4" maxlength="5000"><?= e((string) ($config['instructions_after'] ?? '')) ?></textarea></label>
                                                <label class="is-span-2"><span>Payment transfer terms title</span><input class="form-control" name="terms_title" maxlength="160" value="<?= e((string) ($config['terms_title'] ?? 'Payment transfer terms and conditions')) ?>"></label>
                                                <label class="is-span-2"><span>Payment transfer terms and conditions</span><textarea class="form-control" name="terms_content" rows="5" maxlength="10000"><?= e((string) ($config['terms_content'] ?? '')) ?></textarea><button class="btn btn-outline-secondary" type="button" data-payment-terms-preview>Preview terms popup</button></label>
                                                <div class="payment-manual-checks is-span-2"><label><input type="checkbox" name="receipt_required" value="1" <?= !empty($config['receipt_required']) ? 'checked' : '' ?>> Require customer Receipt Upload</label><label><input type="checkbox" name="terms_required" value="1" <?= !empty($config['terms_required']) ? 'checked' : '' ?>> Require transfer-terms agreement checkbox</label><label><input type="checkbox" name="approval_required" value="1" <?= !array_key_exists('approval_required', $config) || !empty($config['approval_required']) ? 'checked' : '' ?>> Require administrator approval</label></div>

                                            <?php else: ?>
                                                <label><span>Display title</span><input class="form-control" name="display_title" maxlength="120" value="<?= e((string) ($config['display_title'] ?? 'Manual Payment')) ?>" required></label>
                                                <label><span>Processing days</span><input class="form-control" name="processing_days" type="number" min="0" max="30" value="<?= e((string) ($config['processing_days'] ?? 1)) ?>" required></label>
                                                <label class="is-span-2"><span>Instructions before Payment selection</span><textarea class="form-control" name="instructions_before" rows="5" maxlength="5000"><?= e((string) ($config['instructions_before'] ?? $config['instructions'] ?? '')) ?></textarea></label>
                                                <label><span>Recipient name</span><input class="form-control" name="recipient_name" maxlength="160" value="<?= e((string) ($config['recipient_name'] ?? $config['account_name'] ?? '')) ?>" required></label>
                                                <label><span>Account number</span><input class="form-control" name="account_number" maxlength="160" value="<?= e((string) ($config['account_number'] ?? '')) ?>" required></label>
                                                <label><span>Currency</span><input class="form-control" name="currency" maxlength="3" value="<?= e((string) ($config['currency'] ?? 'USD')) ?>" required></label>
                                                <label><span>QR code upload</span><input class="form-control" name="qr_code" type="file" accept="image/png,image/jpeg,image/webp"></label>
                                                <?php if (!empty($config['qr_code_url'])): ?><div class="payment-qr-preview is-span-2"><img src="<?= e((string) $config['qr_code_url']) ?>" alt="Current payment QR"><label><input type="checkbox" name="remove_qr_code" value="1"> Remove current QR code</label></div><?php endif; ?>
                                                <label class="is-span-2"><span>Instructions after Payment</span><textarea class="form-control" name="instructions_after" rows="4" maxlength="5000"><?= e((string) ($config['instructions_after'] ?? '')) ?></textarea></label>
                                                <label class="is-span-2"><span>Payment transfer terms title</span><input class="form-control" name="terms_title" maxlength="160" value="<?= e((string) ($config['terms_title'] ?? 'Payment transfer terms and conditions')) ?>"></label>
                                                <label class="is-span-2"><span>Payment transfer terms and conditions</span><textarea class="form-control" name="terms_content" rows="5" maxlength="10000"><?= e((string) ($config['terms_content'] ?? '')) ?></textarea><button class="btn btn-outline-secondary" type="button" data-payment-terms-preview>Preview terms popup</button></label>
                                                <div class="payment-manual-checks is-span-2"><label><input type="checkbox" name="receipt_required" value="1" <?= !empty($config['receipt_required']) ? 'checked' : '' ?>> Require customer Receipt Upload</label><label><input type="checkbox" name="terms_required" value="1" <?= !empty($config['terms_required']) ? 'checked' : '' ?>> Require transfer-terms agreement checkbox</label><label><input type="checkbox" name="approval_required" value="1" <?= !array_key_exists('approval_required', $config) || !empty($config['approval_required']) ? 'checked' : '' ?>> Require administrator approval</label></div>
                                            <?php endif; ?>
                                        </div>

                                        <div class="payment-method-secret-note">
                                            <?= app_icon('shield') ?>
                                            <span><?= e($type === 'automatic'
                                                ? 'Automatic gateway secrets are encrypted with AES-256-GCM and are never rendered back into this page.'
                                                : 'Manual payment methods store customer-facing operational instructions only.') ?></span>
                                        </div>

                                        <div class="payment-method-form__footer">
                                            <button class="btn btn-outline-secondary" type="button" data-payment-method-dialog-close>Cancel</button>
                                            <button class="btn btn-primary" type="submit"><?= app_icon('check') ?> Save Configuration</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </dialog>
        <?php endforeach; ?>

    </section>



    <section class="workspace-table-section payment-provider-registry">
        <div class="workspace-table-head">
            <div>
                <h2>Automatic gateway registry</h2>
                <p>Underlying gateway and webhook infrastructure. Payment methods above remain the customer-facing payment catalog.</p>
            </div>
        </div>

        <div class="workspace-table-wrap">
            <table class="workspace-table">
                <thead>
                    <tr>
                        <th>Provider</th>
                        <th>Environment</th>
                        <th>Webhook endpoint</th>
                        <th>Events</th>
                        <th>Failures</th>
                        <th>Enabled</th>
                        <th>Configure</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($providers === []): ?>
                        <tr><td colspan="7"><div class="payment-provider-empty">No payment providers exist yet. Use Add Payment Provider to create the first one.</div></td></tr>
                    <?php endif; ?>

                    <?php foreach ($providers as $provider): ?>
                        <?php
                        $providerId = (int) ($provider['id'] ?? 0);
                        $status = (string) ($provider['status'] ?? 'inactive');
                        $isActive = $status === 'active';
                        $isArchived = $status === 'archived';
                        $environment = (string) ($provider['environment'] ?? 'sandbox');
                        $dialogId = 'payment-provider-configure-' . $providerId;
                        $tokenInputId = 'payment-provider-token-' . $providerId;
                        ?>
                        <tr>
                            <td>
                                <div class="payment-provider-identity">
                                    <strong><?= e((string) ($provider['name'] ?? 'Provider')) ?></strong>
                                    <small><?= e((string) ($provider['code'] ?? '')) ?> · <?= e((string) ($provider['adapter_key'] ?? 'generic_token')) ?></small>
                                </div>
                            </td>
                            <td><span class="payment-provider-environment<?= $environment === 'live' ? ' is-live' : '' ?>"><?= e(ucfirst($environment)) ?></span></td>
                            <td>
                                <div class="payment-provider-endpoint">
                                    <code><?= e(url('/payments/webhooks/' . (string) ($provider['code'] ?? ''))) ?></code>
                                    <small>X-Barama-Webhook-Token</small>
                                </div>
                            </td>
                            <td><?= e(number_format((int) ($provider['webhook_events'] ?? 0))) ?></td>
                            <td><?= e(number_format((int) ($provider['failed_events'] ?? 0))) ?></td>
                            <td>
                                <form class="payment-provider-switch-form" action="<?= e(url('/dashboard/payment-providers/' . $providerId . '/status')) ?>" method="post" data-workspace-form data-payment-native-confirmed-form="1">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="status" value="<?= e($isActive ? 'inactive' : 'active') ?>">
                                    <label class="payment-provider-switch<?= $isArchived ? ' is-disabled' : '' ?>" title="<?= e($isArchived ? 'Archived providers are read-only' : ($isActive ? 'Turn provider OFF' : 'Turn provider ON')) ?>">
                                        <input type="checkbox" data-provider-toggle <?= $isActive ? 'checked' : '' ?> <?= $isArchived ? 'disabled' : '' ?> aria-label="<?= e($isActive ? 'Disable provider' : 'Enable provider') ?>">
                                        <span class="payment-provider-switch__track" aria-hidden="true"></span>
                                        <span class="payment-provider-switch__label"><?= e($isArchived ? 'Archived' : ($isActive ? 'ON' : 'OFF')) ?></span>
                                    </label>
                                </form>
                            </td>
                            <td>
                                <button class="payment-provider-configure" type="button" data-provider-dialog-open="<?= e($dialogId) ?>">
                                    <?= app_icon('settings') ?><span>Configure</span>
                                </button>
                            </td>
                        </tr>

                        <dialog class="payment-provider-dialog" id="<?= e($dialogId) ?>">
                            <div class="payment-provider-dialog__shell">
                                <header class="payment-provider-dialog__header">
                                    <div>
                                        <span class="payment-provider-dialog__eyebrow">Provider Configuration</span>
                                        <h2><?= e((string) ($provider['name'] ?? 'Payment Provider')) ?></h2>
                                        <p><?= e($isArchived ? 'Archived provider · read-only configuration' : 'Update safe provider settings without changing its permanent webhook identity.') ?></p>
                                    </div>
                                    <button class="payment-provider-dialog__close" type="button" data-provider-dialog-close aria-label="Close"><?= app_icon('close') ?></button>
                                </header>
                                <div class="payment-provider-dialog__body">
                                    <form action="<?= e(url('/dashboard/payment-providers/' . $providerId)) ?>" method="post" data-workspace-form class="payment-provider-form" data-payment-native-confirmed-form="1">
                                        <?= csrf_field() ?>
                                        <div class="payment-provider-form__grid">
                                            <label>
                                                <span>Provider name</span>
                                                <input class="form-control" name="name" maxlength="160" required value="<?= e((string) ($provider['name'] ?? '')) ?>" <?= $isArchived ? 'readonly' : '' ?>>
                                            </label>
                                            <label>
                                                <span>Environment<?= $isActive ? ' · turn OFF to change' : '' ?></span>
                                                <select class="form-select" name="environment" required <?= ($isActive || $isArchived) ? 'disabled' : '' ?>>
                                                    <option value="sandbox" <?= $environment === 'sandbox' ? 'selected' : '' ?>>Sandbox</option>
                                                    <option value="live" <?= $environment === 'live' ? 'selected' : '' ?>>Live</option>
                                                </select>
                                                <?php if ($isActive || $isArchived): ?>
                                                    <input type="hidden" name="environment" value="<?= e($environment) ?>">
                                                <?php endif; ?>
                                            </label>
                                            <div class="payment-provider-readonly">
                                                <strong>Provider code</strong>
                                                <code><?= e((string) ($provider['code'] ?? '')) ?></code>
                                            </div>
                                            <div class="payment-provider-readonly">
                                                <strong>Adapter</strong>
                                                <code><?= e((string) ($provider['adapter_key'] ?? 'generic_token')) ?></code>
                                            </div>
                                            <div class="payment-provider-readonly is-span-2">
                                                <strong>Webhook endpoint</strong>
                                                <code><?= e(url('/payments/webhooks/' . (string) ($provider['code'] ?? ''))) ?></code>
                                                <small>Authentication header: X-Barama-Webhook-Token</small>
                                            </div>
                                            <label class="is-span-2">
                                                <span>Rotate webhook token · optional</span>
                                                <div class="input-group">
                                                    <input class="form-control" id="<?= e($tokenInputId) ?>" name="webhook_token" type="password" minlength="32" maxlength="191" autocomplete="new-password" placeholder="<?= e($isArchived ? 'Archived provider' : 'Leave blank to keep the existing token') ?>" <?= $isArchived ? 'disabled' : '' ?>>
                                                    <button class="btn btn-outline-secondary" type="button" data-provider-token-toggle="<?= e($tokenInputId) ?>" aria-pressed="false" <?= $isArchived ? 'disabled' : '' ?>>Show</button>
                                                </div>
                                            </label>
                                        </div>

                                        <div class="payment-provider-security-note">
                                            <?= app_icon('shield') ?>
                                            <span>Provider code and adapter are permanent. Rotating the webhook token immediately invalidates the previous token.</span>
                                        </div>

                                        <div class="payment-provider-form__footer">
                                            <?php if (!$isArchived): ?>
                                                <?php if (!$isActive): ?>
                                                    <button class="btn btn-outline-danger" type="submit" formaction="<?= e(url('/dashboard/payment-providers/' . $providerId . '/status')) ?>" name="status" value="archived">Archive Provider</button>
                                                <?php else: ?>
                                                    <button class="btn btn-outline-danger" type="button" disabled title="Turn the provider OFF before archiving">Archive Provider</button>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="status-badge status-badge--neutral">Archived</span>
                                            <?php endif; ?>

                                            <div class="payment-provider-form__actions">
                                                <button class="btn btn-outline-secondary" type="button" data-provider-dialog-close>Close</button>
                                                <?php if (!$isArchived): ?>
                                                    <button class="btn btn-primary" type="submit"><?= app_icon('check') ?> Save Configuration</button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </dialog>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <link
    rel="stylesheet"
    href="<?= e(asset('css/finance-payment-providers-webhook-v1811.css') . '?v=1811') ?>"
>

<link
    rel="stylesheet"
    href="<?= e(asset('css/finance-payment-providers-webhook-focus-v1813.css') . '?v=1813') ?>"
>

<section class="workspace-table-section finance-payment-webhook-section">
        <div class="workspace-table-head">
            <div>
                <h2>Webhook inbox</h2>
                <p>Receipts are deduplicated by provider and event ID. Failed receipts remain available for safe provider retry.</p>
            </div>
        </div>

        
        <form
            class="finance-payment-webhook-tools"
            method="get"
            action="<?= e(url('/dashboard/payment-providers')) ?>"
        >
            <label>
                <?= app_icon('search') ?>
                <input
                    class="form-control"
                    type="search"
                    name="webhook_q"
                    value="<?= e($webhookQ) ?>"
                    placeholder="Search provider, event, transaction, hash, or error"
                >
            </label>

            <select class="form-select" name="webhook_status" aria-label="Webhook event status">
                <option value="">All statuses</option>
                <?php foreach ($webhookStatuses as $statusOption): ?>
                    <option
                        value="<?= e((string) $statusOption) ?>"
                        <?= $webhookStatus === (string) $statusOption ? 'selected' : '' ?>
                    ><?= e(ucfirst((string) $statusOption)) ?></option>
                <?php endforeach; ?>
            </select>

            <button class="btn btn-primary" type="submit">Apply</button>
            <a class="btn btn-outline-secondary" href="<?= e(url('/dashboard/payment-providers')) ?>">Reset</a>
        </form>

<div class="workspace-table-wrap">
            <table class="workspace-table finance-payment-webhook-table-focus">
                <thead>
                    <tr>
                        <th>Received</th>
                        <th>Provider</th>
                        <th>Event</th>
                        <th>State</th>
                        <th>Audit</th>
                        <th>Processing</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($visibleWebhookEvents === []): ?>
                        <tr><td colspan="6"><div class="workspace-record-empty">No webhook events have been received yet.</div></td></tr>
                    <?php endif; ?>

                    <?php foreach ($visibleWebhookEvents as $event): ?>
                        <?php
                        $status = (string) ($event['status'] ?? 'received');
                        $tone = $status === 'processed'
                            ? 'approved'
                            : ($status === 'failed' ? 'neutral' : 'pending');
                        ?>
                        <tr>
                            <td><?= e((string) $event['received_at']) ?></td>
                            <td>
                                <div class="finance-payment-webhook-provider">
                                    <strong><?= e((string) $event['provider_name']) ?></strong>
                                    <small><?= e((string) $event['provider_code']) ?></small>
                                </div>
                            </td>
                            <td>
                                <div class="finance-payment-webhook-event">
                                    <strong><?= e((string) $event['event_id']) ?></strong>
                                    <small><?= e((string) $event['event_type']) ?></small>
                                </div>
                            </td>
                            <td>
                                <span class="status-badge status-badge--<?= e($tone) ?>">
                                    <?= e(ucfirst($status)) ?>
                                </span>
                            </td>
                            <td>
                                <div class="finance-payment-webhook-audit">
                                    <small>Transaction: <?= e((string) ($event['transaction_reference'] ?? '—')) ?></small>
                                    <small>Payload: <?= e(substr((string) $event['payload_sha256'], 0, 16)) ?>…</small>
                                </div>
                            </td>
                            <td>
                                <div class="finance-payment-webhook-processing">
                                    <small>Processed: <?= e((string) ($event['processed_at'] ?? '—')) ?></small>
                                    <small>Error: <?= e((string) ($event['error_message'] ?? '—')) ?></small>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="finance-payment-webhook-pager">
            <span>
                Page <?= $webhookPage ?> of <?= $webhookPages ?>
                &middot; <?= $webhookTotal ?> event<?= $webhookTotal === 1 ? '' : 's' ?>
                &middot; 10 per page
            </span>

            <?php if ($webhookPages > 1): ?>
                <nav aria-label="Webhook inbox pages">
                    <?php if ($webhookPage > 1): ?>
                        <a href="<?= e($webhookPageUrl($webhookPage - 1)) ?>">Previous</a>
                    <?php endif; ?>

                    <?php
                    $webhookFrom = max(1, $webhookPage - 2);
                    $webhookTo = min($webhookPages, $webhookPage + 2);
                    ?>
                    <?php for ($i = $webhookFrom; $i <= $webhookTo; $i++): ?>
                        <a href="<?= e($webhookPageUrl($i)) ?>" class="<?= $i === $webhookPage ? 'is-active' : '' ?>"><?= $i ?></a>
                    <?php endfor; ?>

                    <?php if ($webhookPage < $webhookPages): ?>
                        <a href="<?= e($webhookPageUrl($webhookPage + 1)) ?>">Next</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>

            <form class="finance-payment-webhook-goto" method="get" action="<?= e(url('/dashboard/payment-providers')) ?>">
                <?php foreach ($webhookQuery as $key => $value): ?>
                    <input type="hidden" name="<?= e((string) $key) ?>" value="<?= e((string) $value) ?>">
                <?php endforeach; ?>

                <label>
                    <span>Go To</span>
                    <input class="form-control" type="number" name="webhook_page" min="1" max="<?= $webhookPages ?>" value="<?= $webhookPage ?>">
                </label>
                <button class="btn btn-outline-secondary" type="submit">Go</button>
            </form>
        </div>

    </section>

    <section class="workspace-table-section">
        <div class="workspace-table-head">
            <div>
                <h2>Generic webhook contract</h2>
                <p>Provider-specific signature adapters will be added separately. The generic adapter accepts JSON with these fields.</p>
            </div>
        </div>
        <div class="workspace-record-empty">
            <code>event_id</code>, <code>event_type</code>, <code>invoice_number</code>,
            <code>amount</code>, <code>currency</code>, optional <code>reference</code>,
            optional <code>processed_at</code>. Supported event types:
            payment.succeeded, payment.failed, refund.succeeded, refund.failed,
            credit.succeeded, credit.failed.
        </div>
    </section>
</section>
