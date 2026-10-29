<?php

declare(strict_types=1);

$invoiceTemplateService = new \Barama\Services\InvoiceTemplateService();
$invoiceTemplate = $invoiceTemplateService->get();
$invoiceTemplateVariables = $invoiceTemplateService->variables();
$invoiceTemplateMigrationRequired = !empty($invoiceTemplate['__migration_required']);
$templateNotice = (string) ($_GET['invoice_template'] ?? '');
$templateMessage = trim((string) ($_GET['message'] ?? ''));
$checked = static fn (string $key): string => !empty($invoiceTemplate[$key]) ? ' checked' : '';
?>
<?= barama_css_once('admin') ?>
<?= barama_css_once('admin') ?>
<?= barama_css_once('admin') ?>

<?php if ($templateNotice !== ''): ?>
    <div class="invoice-template-toast <?= $templateNotice === 'error' ? 'is-error' : 'is-success' ?>" data-invoice-template-toast>
        <?php if ($templateNotice === 'saved'): ?>Invoice template saved.
        <?php elseif ($templateNotice === 'reset'): ?>Invoice template reset to defaults.
        <?php else: ?><?= e($templateMessage !== '' ? $templateMessage : 'Invoice template update failed.') ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($invoiceTemplateMigrationRequired): ?>
    <div class="workspace-notice invoice-template-schema-notice" role="status">
        <strong>Invoice template setup is pending.</strong>
        <span> The invoice ledger remains available. Back up this installation and apply the pending database migration through <code>/updater/</code> before editing invoice templates.</span>
    </div>
<?php endif; ?>

<div class="invoice-template-modal" data-invoice-template-modal aria-hidden="true">
    <button class="invoice-template-modal__backdrop" type="button" data-invoice-template-close aria-label="Close invoice template"></button>

    <section class="invoice-template-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="invoice-template-title">
        <header class="invoice-template-modal__header">
            <div>
                <span>Billing document design</span>
                <h2 id="invoice-template-title">Invoice Template</h2>
                <p>Configure branding, numbering, labels, payment copy and the printable invoice preview.</p>
            </div>
            <button type="button" class="invoice-template-icon-button" data-invoice-template-close aria-label="Close">×</button>
        </header>

        <div class="invoice-template-modal__body">
            <form id="invoice-template-form" class="invoice-template-form" method="post" action="<?= e(url('/dashboard/invoices/template')) ?>" enctype="multipart/form-data" data-invoice-template-form>
                <?= csrf_field() ?>

                <nav class="invoice-template-tabs" aria-label="Invoice template settings">
                    <button type="button" class="is-active" data-template-tab="identity">Identity</button>
                    <button type="button" data-template-tab="labels">Labels</button>
                    <button type="button" data-template-tab="content">Content</button>
                </nav>

                <div class="invoice-template-pane is-active" data-template-pane="identity">
                    <div class="invoice-template-field-grid">
                        <?php foreach ([
                            'template_name' => 'Template name',
                            'invoice_title' => 'Invoice title',
                            'invoice_prefix' => 'Invoice prefix',
                            'default_currency' => 'Default currency',
                        ] as $key => $label): ?>
                            <label>
                                <span><?= e($label) ?></span>
                                <input name="<?= e($key) ?>" value="<?= e((string) $invoiceTemplate[$key]) ?>" maxlength="<?= $key === 'template_name' ? '120' : ($key === 'invoice_title' ? '80' : '20') ?>" required data-preview-source="<?= e($key) ?>">
                            </label>
                        <?php endforeach; ?>

                        <label class="invoice-template-field-grid__wide">
                            <span>Invoice number pattern</span>
                            <input name="number_pattern" value="<?= e((string) $invoiceTemplate['number_pattern']) ?>" maxlength="120" required>
                            <small>Tokens: {{prefix}}, {{year}}, {{month}}, {{sequence}}, {{sequence4}}</small>
                        </label>

                        <label>
                            <span>Default due days</span>
                            <input type="number" name="default_due_days" min="0" max="365" value="<?= e((string) $invoiceTemplate['default_due_days']) ?>" required>
                        </label>

                        <label>
                            <span>Accent color</span>
                            <div class="invoice-template-color">
                                <input type="color" name="accent_color" value="<?= e((string) $invoiceTemplate['accent_color']) ?>" data-preview-source="accent_color">
                                <code data-accent-value><?= e((string) $invoiceTemplate['accent_color']) ?></code>
                            </div>
                        </label>

                        <!-- BARAMA_INVOICE_LOGO_UPLOAD_V152 -->
                        <label class="invoice-template-field-grid__wide">
                            <span>Logo URL / site path</span>
                            <input name="logo_url" value="<?= e((string) $invoiceTemplate['logo_url']) ?>" maxlength="500" placeholder="/assets/images/logo.png or https://..." data-preview-source="logo_url">

                            <div class="invoice-template-logo-upload">
                                <div
                                    class="invoice-template-logo-dropzone"
                                    data-logo-dropzone
                                >
                                    <div class="invoice-template-logo-upload__icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none">
                                            <path d="M12 16V4m0 0L7.5 8.5M12 4l4.5 4.5M5 14.5V19h14v-4.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </div>

                                    <div class="invoice-template-logo-upload__copy">
                                        <strong>Upload | Drag &amp; Drop</strong>
                                        <span>PNG, JPG/JPEG, WebP or GIF · max 5 MB</span>
                                    </div>

                                    <button
                                        type="button"
                                        class="invoice-template-logo-upload__browse"
                                        data-logo-browse
                                    >
                                        Choose file
                                    </button>

                                    <input
                                        type="file"
                                        name="logo_file"
                                        accept="image/png,image/jpeg,image/webp,image/gif"
                                        data-logo-file
                                        hidden
                                    >
                                </div>

                                <div class="invoice-template-logo-upload__meta">
                                    <small
                                        class="invoice-template-logo-upload__status"
                                        data-logo-upload-status
                                    >
                                        No upload selected. You can still enter or paste a URL above.
                                    </small>
                                    <button
                                        type="button"
                                        class="invoice-template-logo-upload__clear"
                                        data-logo-clear
                                    >
                                        Clear upload
                                    </button>
                                </div>
                            </div>
                        </label>
                    </div>

                    <div class="invoice-template-subsection">
                        <h3>Seller identity</h3>
                        <div class="invoice-template-field-grid">
                            <?php foreach ([
                                'seller_name' => 'Business name',
                                'seller_email' => 'Email',
                                'seller_phone' => 'Phone',
                                'seller_tax_id' => 'Tax / registration ID',
                            ] as $key => $label): ?>
                                <label>
                                    <span><?= e($label) ?></span>
                                    <input name="<?= e($key) ?>" value="<?= e((string) $invoiceTemplate[$key]) ?>" maxlength="190" data-preview-source="<?= e($key) ?>">
                                </label>
                            <?php endforeach; ?>
                            <label class="invoice-template-field-grid__wide">
                                <span>Business address</span>
                                <textarea name="seller_address" rows="3" maxlength="1000" data-preview-source="seller_address"><?= e((string) $invoiceTemplate['seller_address']) ?></textarea>
                            </label>
                        </div>
                        <div class="invoice-template-switches">
                            <label><input type="checkbox" name="show_logo" value="1"<?= $checked('show_logo') ?>><span>Show logo</span></label>
                            <label><input type="checkbox" name="show_seller_tax_id" value="1"<?= $checked('show_seller_tax_id') ?>><span>Show tax / registration ID</span></label>
                        </div>
                    </div>
                </div>

                <div class="invoice-template-pane" data-template-pane="labels">
                    <div class="invoice-template-field-grid">
                        <?php foreach ([
                            'customer_label' => 'Customer section',
                            'subtotal_label' => 'Subtotal',
                            'discount_label' => 'Discount',
                            'tax_label' => 'Tax',
                            'total_label' => 'Total',
                        ] as $key => $label): ?>
                            <label>
                                <span><?= e($label) ?></span>
                                <input name="<?= e($key) ?>" value="<?= e((string) $invoiceTemplate[$key]) ?>" maxlength="60" required data-preview-source="<?= e($key) ?>">
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="invoice-template-token-panel">
                        <h3>Available variables</h3>
                        <p>Click a variable to copy it for payment instructions, notes or footer text.</p>
                        <div>
                            <?php foreach ($invoiceTemplateVariables as $variable): ?>
                                <button type="button" data-template-token="<?= e($variable) ?>"><?= e($variable) ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="invoice-template-pane" data-template-pane="content">
                    <label><span>Payment instructions</span><textarea name="payment_instructions" rows="5" maxlength="3000" data-preview-source="payment_instructions"><?= e((string) $invoiceTemplate['payment_instructions']) ?></textarea></label>
                    <label><span>Default notes</span><textarea name="default_notes" rows="4" maxlength="2000" data-preview-source="default_notes"><?= e((string) $invoiceTemplate['default_notes']) ?></textarea></label>
                    <label><span>Footer text</span><textarea name="footer_text" rows="3" maxlength="1000" data-preview-source="footer_text"><?= e((string) $invoiceTemplate['footer_text']) ?></textarea></label>
                    <div class="invoice-template-switches">
                        <label><input type="checkbox" name="show_payment_instructions" value="1"<?= $checked('show_payment_instructions') ?>><span>Show payment instructions</span></label>
                    </div>
                </div>
            </form>

            <aside class="invoice-template-preview-shell" data-barama-invoice-accent="<?= e((string) $invoiceTemplate['accent_color']) ?>">
                <div class="invoice-template-preview-toolbar">
                    <span>Live preview</span>
                    <button type="button" data-template-print>Print preview</button>
                </div>

                <article class="invoice-template-preview" data-invoice-template-preview>
                    <header>
                        <div class="invoice-template-preview__seller">
                            <img src="<?= e((string) $invoiceTemplate['logo_url']) ?>" alt="" data-preview-logo<?= empty($invoiceTemplate['logo_url']) ? ' hidden' : '' ?>>
                            <strong data-preview-target="seller_name"><?= e((string) $invoiceTemplate['seller_name']) ?></strong>
                            <span data-preview-target="seller_address"><?= nl2br(e((string) $invoiceTemplate['seller_address'])) ?></span>
                            <span data-preview-target="seller_email"><?= e((string) $invoiceTemplate['seller_email']) ?></span>
                            <span data-preview-target="seller_phone"><?= e((string) $invoiceTemplate['seller_phone']) ?></span>
                            <small data-preview-target="seller_tax_id"><?= e((string) $invoiceTemplate['seller_tax_id']) ?></small>
                        </div>
                        <div class="invoice-template-preview__identity">
                            <h3 data-preview-target="invoice_title"><?= e((string) $invoiceTemplate['invoice_title']) ?></h3>
                            <strong data-preview-invoice-number><?= e((string) $invoiceTemplate['invoice_prefix']) ?>-2026-0001</strong>
                            <span>Issued: Oct 3, 2026</span>
                            <span>Due: <?= e(date('M j, Y', strtotime('+' . (int) $invoiceTemplate['default_due_days'] . ' days'))) ?></span>
                        </div>
                    </header>

                    <section class="invoice-template-preview__customer">
                        <span data-preview-target="customer_label"><?= e((string) $invoiceTemplate['customer_label']) ?></span>
                        <strong>Sample Customer</strong>
                        <small>customer@example.com</small>
                    </section>

                    <table>
                        <thead><tr><th>Description</th><th>Qty</th><th>Rate</th><th>Amount</th></tr></thead>
                        <tbody><tr><td>Premium Plan</td><td>1</td><td>USD 149.00</td><td>USD 149.00</td></tr></tbody>
                    </table>

                    <div class="invoice-template-preview__totals">
                        <div><span data-preview-target="subtotal_label"><?= e((string) $invoiceTemplate['subtotal_label']) ?></span><strong>USD 149.00</strong></div>
                        <div><span data-preview-target="discount_label"><?= e((string) $invoiceTemplate['discount_label']) ?></span><strong>USD 0.00</strong></div>
                        <div><span data-preview-target="tax_label"><?= e((string) $invoiceTemplate['tax_label']) ?></span><strong>USD 0.00</strong></div>
                        <div class="is-total"><span data-preview-target="total_label"><?= e((string) $invoiceTemplate['total_label']) ?></span><strong>USD 149.00</strong></div>
                    </div>

                    <section class="invoice-template-preview__payment"><strong>Payment instructions</strong><p data-preview-target="payment_instructions"><?= nl2br(e((string) $invoiceTemplate['payment_instructions'])) ?></p></section>
                    <section class="invoice-template-preview__notes"><p data-preview-target="default_notes"><?= nl2br(e((string) $invoiceTemplate['default_notes'])) ?></p></section>
                    <footer data-preview-target="footer_text"><?= nl2br(e((string) $invoiceTemplate['footer_text'])) ?></footer>
                </article>
            </aside>
        </div>

        <footer class="invoice-template-modal__footer">
            <button type="submit" form="invoice-template-reset-form" class="btn btn-outline-secondary" data-template-reset<?= $invoiceTemplateMigrationRequired ? ' disabled title="Apply database migration first"' : '' ?>>Reset defaults</button>
            <div>
                <button type="button" class="btn btn-outline-secondary" data-invoice-template-close>Cancel</button>
                <button type="submit" form="invoice-template-form" class="btn btn-primary"<?= $invoiceTemplateMigrationRequired ? ' disabled title="Apply database migration first"' : '' ?>>Save template</button>
            </div>
        </footer>
    </section>
</div>

<form id="invoice-template-reset-form" method="post" action="<?= e(url('/dashboard/invoices/template/reset')) ?>" hidden>
    <?= csrf_field() ?>
</form>

<?= barama_js_once('admin') ?><script type="application/x-barama-module" data-barama-module="invoice-template"></script>
<?= barama_js_once('admin') ?><script type="application/x-barama-module" data-barama-module="invoice-template-logo-upload"></script>
