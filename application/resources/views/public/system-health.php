<?php

declare(strict_types=1);

$snapshot = is_array($systemHealth ?? null) ? $systemHealth : [];
$overall = (string) ($snapshot['overall'] ?? 'degraded');
$isOperational = $overall === 'operational';
$services = is_array($snapshot['services'] ?? null) ? $snapshot['services'] : [];
?>
<main class="system-status-main" data-system-health>
    <section class="system-status-hero">
        <div class="system-status-container">
            <div class="system-status-hero__eyebrow">SYSTEM STATUS</div>
            <div class="system-status-overview <?= $isOperational ? 'is-operational' : 'is-degraded' ?>">
                <div class="system-status-overview__signal" aria-hidden="true">
                    <span></span>
                </div>
                <div class="system-status-overview__copy">
                    <div class="system-status-overview__meta">
                        <span><?= $isOperational ? 'OPERATING NORMALLY' : 'SERVICE NOTICE' ?></span>
                        <span class="system-status-overview__checked">Checked just now</span>
                    </div>
                    <h1><?= e((string) ($snapshot['label'] ?? 'System status')) ?></h1>
                    <p><?= e((string) ($snapshot['message'] ?? 'Current service status is available below.')) ?></p>
                </div>
                <a class="system-status-refresh" href="<?= e(url('/health')) ?>">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 11a8 8 0 1 0-2.34 5.66M20 5v6h-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <span>Refresh status</span>
                </a>
            </div>
        </div>
    </section>

    <section class="system-status-section" aria-labelledby="current-status-title">
        <div class="system-status-container">
            <div class="system-status-section__heading">
                <div>
                    <span class="system-status-kicker">CURRENT STATUS</span>
                    <h2 id="current-status-title">Customer-facing services</h2>
                </div>
                <span class="system-status-summary-badge <?= $isOperational ? 'is-operational' : 'is-degraded' ?>">
                    <span aria-hidden="true"></span>
                    <?= $isOperational ? 'All operational' : 'Attention needed' ?>
                </span>
            </div>

            <div class="system-status-services">
                <?php foreach ($services as $service): ?>
                    <?php
                    $serviceStatus = (string) ($service['status'] ?? 'degraded');
                    $serviceOperational = $serviceStatus === 'operational';
                    ?>
                    <article class="system-status-service">
                        <div class="system-status-service__identity">
                            <span class="system-status-service__icon <?= $serviceOperational ? 'is-operational' : 'is-degraded' ?>" aria-hidden="true">
                                <?php if ($serviceOperational): ?>
                                    <svg viewBox="0 0 24 24"><path d="M5 12.5 9.2 17 19 7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                <?php else: ?>
                                    <svg viewBox="0 0 24 24"><path d="M12 8v5m0 3.2v.1M4.8 19h14.4a1 1 0 0 0 .87-1.5L12.87 5a1 1 0 0 0-1.74 0L3.93 17.5A1 1 0 0 0 4.8 19Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                <?php endif; ?>
                            </span>
                            <div>
                                <h3><?= e((string) ($service['name'] ?? 'Service')) ?></h3>
                                <p><?= e((string) ($service['description'] ?? '')) ?></p>
                            </div>
                        </div>
                        <span class="system-status-service__badge <?= $serviceOperational ? 'is-operational' : 'is-degraded' ?>">
                            <span aria-hidden="true"></span>
                            <?= e((string) ($service['label'] ?? ($serviceOperational ? 'Operational' : 'Service issue'))) ?>
                        </span>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="system-status-section system-status-section--compact">
        <div class="system-status-container system-status-bottom-grid">
            <article class="system-status-notice">
                <span class="system-status-notice__icon <?= $isOperational ? 'is-operational' : 'is-degraded' ?>" aria-hidden="true">
                    <?php if ($isOperational): ?>
                        <svg viewBox="0 0 24 24"><path d="M5 12.5 9.2 17 19 7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <?php else: ?>
                        <svg viewBox="0 0 24 24"><path d="M12 8v5m0 3.2v.1M4.8 19h14.4a1 1 0 0 0 .87-1.5L12.87 5a1 1 0 0 0-1.74 0L3.93 17.5A1 1 0 0 0 4.8 19Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <?php endif; ?>
                </span>
                <div>
                    <span class="system-status-kicker">SERVICE NOTICES</span>
                    <h2><?= $isOperational ? 'No active service notices' : 'A service notice is active' ?></h2>
                    <p><?= $isOperational
                        ? 'No customer-facing disruption is currently detected.'
                        : 'Some services may be temporarily unavailable. Please refresh this page for the latest status.' ?></p>
                </div>
            </article>

            <article class="system-status-support">
                <div>
                    <span class="system-status-kicker">NEED HELP?</span>
                    <h2>Contact support</h2>
                    <p>If you are experiencing an issue while services are operational, our support team can help.</p>
                </div>
                <a href="<?= e(url('/contact')) ?>">
                    <span>Get support</span>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14m-5-5 5 5-5 5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </a>
            </article>
        </div>
    </section>
</main>
