<?php

declare(strict_types=1);

$page = is_array($contactPage ?? null)
    ? $contactPage
    : \Barama\Repositories\SiteContentRepository::defaults()['contact'];
$hero = is_array($page['hero'] ?? null) ? $page['hero'] : [];
$sections = is_array($page['sections'] ?? null) ? $page['sections'] : [];
$order = is_array($page['section_order'] ?? null)
    ? array_values($page['section_order'])
    : ['channels', 'office', 'hours', 'form', 'faq', 'socials'];
?>
<main class="builtin-public-page builtin-contact-page">
    <?php if (!empty($hero['enabled'])): ?>
        <section class="builtin-hero builtin-hero--contact">
            <div class="container app-container">
                <div class="builtin-hero__grid">
                    <div class="builtin-hero__copy">
                        <span class="eyebrow"><?= e((string) ($hero['eyebrow'] ?? 'Contact Barama')) ?></span>
                        <h1><?= e((string) ($hero['title'] ?? 'Contact Us')) ?></h1>
                        <p><?= e((string) ($hero['description'] ?? '')) ?></p>
                    </div>
                    <?php if (trim((string) ($hero['image_url'] ?? '')) !== ''): ?>
                        <div class="builtin-hero__media"><img src="<?= e((string) $hero['image_url']) ?>" alt="<?= e((string) ($hero['title'] ?? 'Contact Barama')) ?>" loading="eager"></div>
                    <?php else: ?>
                        <div class="builtin-hero__visual" aria-hidden="true">
                            <span><?= app_icon('mail') ?></span>
                            <span><?= app_icon('message') ?></span>
                            <span><?= app_icon('phone') ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <?php foreach ($order as $sectionKey): ?>
        <?php $section = is_array($sections[$sectionKey] ?? null) ? $sections[$sectionKey] : []; ?>
        <?php if (empty($section['enabled'])) continue; ?>

        <?php if ($sectionKey === 'channels'): ?>
            <?php
            $phone = trim((string) ($section['phone'] ?? ''));
            $email = trim((string) ($section['email'] ?? ''));
            $telegram = trim((string) ($section['telegram_url'] ?? ''));
            $whatsapp = trim((string) ($section['whatsapp_url'] ?? ''));
            ?>
            <section class="builtin-section">
                <div class="container app-container">
                    <div class="builtin-section__head">
                        <span class="eyebrow"><?= e((string) ($section['eyebrow'] ?? 'Contact options')) ?></span>
                        <h2><?= e((string) ($section['title'] ?? '')) ?></h2>
                        <?php if (trim((string) ($section['description'] ?? '')) !== ''): ?><p><?= e((string) $section['description']) ?></p><?php endif; ?>
                    </div>
                    <div class="builtin-contact-channel-grid">
                        <?php if ($email !== ''): ?><a class="builtin-contact-channel" href="mailto:<?= e($email) ?>"><?= app_icon('mail') ?><span><strong>Email</strong><small><?= e($email) ?></small></span></a><?php endif; ?>
                        <?php if ($phone !== ''): ?><a class="builtin-contact-channel" href="tel:<?= e((string) preg_replace('/[^0-9+]/', '', $phone)) ?>"><?= app_icon('phone') ?><span><strong>Phone</strong><small><?= e($phone) ?></small></span></a><?php endif; ?>
                        <?php if ($telegram !== ''): ?><a class="builtin-contact-channel" href="<?= e($telegram) ?>" target="_blank" rel="noopener noreferrer"><?= app_icon('message') ?><span><strong>Telegram</strong><small>Open official channel</small></span></a><?php endif; ?>
                        <?php if ($whatsapp !== ''): ?><a class="builtin-contact-channel" href="<?= e($whatsapp) ?>" target="_blank" rel="noopener noreferrer"><?= app_icon('message') ?><span><strong>WhatsApp</strong><small>Start a conversation</small></span></a><?php endif; ?>
                    </div>
                </div>
            </section>
        <?php elseif ($sectionKey === 'office'): ?>
            <section class="builtin-section builtin-section--soft">
                <div class="container app-container builtin-contact-office">
                    <div>
                        <span class="eyebrow"><?= e((string) ($section['eyebrow'] ?? 'Office')) ?></span>
                        <h2><?= e((string) ($section['title'] ?? '')) ?></h2>
                        <?php if (trim((string) ($section['address'] ?? '')) !== ''): ?><p class="builtin-office-address"><?= app_icon('map') ?><span><?= nl2br(e((string) $section['address'])) ?></span></p><?php endif; ?>
                        <?php if (trim((string) ($section['location_note'] ?? '')) !== ''): ?><p><?= e((string) $section['location_note']) ?></p><?php endif; ?>
                        <?php if (trim((string) ($section['map_url'] ?? '')) !== ''): ?><a class="btn btn-outline-secondary" href="<?= e((string) $section['map_url']) ?>" target="_blank" rel="noopener noreferrer"><?= app_icon('map') ?><span><?= e((string) ($section['map_label'] ?? 'Open map')) ?></span></a><?php endif; ?>
                    </div>
                    <div class="builtin-map-placeholder" aria-hidden="true"><?= app_icon('map') ?><strong>Location</strong><span>Use the configured map link for directions.</span></div>
                </div>
            </section>
        <?php elseif ($sectionKey === 'hours'): ?>
            <?php $items = is_array($section['items'] ?? null) ? array_values($section['items']) : []; ?>
            <section class="builtin-section">
                <div class="container app-container">
                    <div class="builtin-section__head">
                        <span class="eyebrow"><?= e((string) ($section['eyebrow'] ?? 'Business hours')) ?></span>
                        <h2><?= e((string) ($section['title'] ?? '')) ?></h2>
                    </div>
                    <div class="builtin-hours-card">
                        <?php foreach ($items as $item): ?>
                            <div><strong><?= e((string) ($item['day'] ?? '')) ?></strong><span><?= e((string) ($item['hours'] ?? '')) ?></span></div>
                        <?php endforeach; ?>
                        <small><?= app_icon('clock') ?> <?= e((string) ($section['timezone'] ?? 'Asia/Phnom_Penh')) ?></small>
                    </div>
                </div>
            </section>
        <?php elseif ($sectionKey === 'form'): ?>
            <?php $subjects = is_array($section['subjects'] ?? null) ? array_values($section['subjects']) : []; ?>
            <section class="builtin-section builtin-section--soft" id="contact-form">
                <div class="container app-container builtin-contact-form-layout">
                    <div class="builtin-section__copy">
                        <span class="eyebrow"><?= e((string) ($section['eyebrow'] ?? 'Send a message')) ?></span>
                        <h2><?= e((string) ($section['title'] ?? '')) ?></h2>
                        <p><?= e((string) ($section['description'] ?? '')) ?></p>
                        <div class="builtin-contact-form-note"><?= app_icon('shield') ?><span>Messages are rate-limited and stored securely for administrative follow-up.</span></div>
                    </div>
                    <div class="builtin-contact-form-card">
                        <form action="<?= e(url('/contact/messages')) ?>" method="post" data-inquiry-form data-contact-form>
                            <?= csrf_field() ?>
                            <div class="contact-form-honeypot" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>
                            <div class="builtin-form-grid">
                                <label><span>Name</span><input class="form-control" name="contact_name" maxlength="160" required autocomplete="name"></label>
                                <label><span>Email</span><input class="form-control" type="email" name="contact_email" maxlength="191" required autocomplete="email"></label>
                                <label><span>Phone<?= !empty($section['require_phone']) ? '' : ' (optional)' ?></span><input class="form-control" name="contact_phone" maxlength="60" <?= !empty($section['require_phone']) ? 'required' : '' ?> autocomplete="tel"></label>
                                <label><span>Subject</span>
                                    <?php if ($subjects !== []): ?>
                                        <select class="form-select" name="subject" required>
                                            <option value="">Choose a subject</option>
                                            <?php foreach ($subjects as $subject): ?><option value="<?= e((string) $subject) ?>"><?= e((string) $subject) ?></option><?php endforeach; ?>
                                        </select>
                                    <?php else: ?>
                                        <input class="form-control" name="subject" maxlength="160" required>
                                    <?php endif; ?>
                                </label>
                                <label class="builtin-form-grid__wide"><span>Message</span><textarea class="form-control" name="message" rows="7" maxlength="5000" required></textarea></label>
                                <?php if (trim((string) ($section['consent_text'] ?? '')) !== ''): ?>
                                    <label class="builtin-contact-consent builtin-form-grid__wide"><input type="checkbox" name="consent" value="1" required><span><?= e((string) $section['consent_text']) ?></span></label>
                                <?php endif; ?>
                            </div>
                            <button class="btn btn-primary builtin-contact-submit" type="submit"><?= app_icon('mail') ?><span><?= e((string) ($section['button_label'] ?? 'Send message')) ?></span></button>
                        </form>
                        <div class="inquiry-card__notice" data-inquiry-notice hidden></div>
                    </div>
                </div>
            </section>
        <?php elseif ($sectionKey === 'faq'): ?>
            <?php $items = is_array($section['items'] ?? null) ? array_values($section['items']) : []; ?>
            <section class="builtin-section">
                <div class="container app-container">
                    <div class="builtin-section__head">
                        <span class="eyebrow"><?= e((string) ($section['eyebrow'] ?? 'Common questions')) ?></span>
                        <h2><?= e((string) ($section['title'] ?? '')) ?></h2>
                    </div>
                    <div class="builtin-faq-list">
                        <?php foreach ($items as $item): ?>
                            <?php if (trim((string) ($item['question'] ?? '')) === '') continue; ?>
                            <details><summary><?= e((string) $item['question']) ?><span><?= app_icon('plus') ?></span></summary><p><?= e((string) ($item['answer'] ?? '')) ?></p></details>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php elseif ($sectionKey === 'socials'): ?>
            <?php
            $socials = [
                'Facebook' => (string) ($section['facebook_url'] ?? ''),
                'Instagram' => (string) ($section['instagram_url'] ?? ''),
                'LinkedIn' => (string) ($section['linkedin_url'] ?? ''),
                'YouTube' => (string) ($section['youtube_url'] ?? ''),
                'Telegram' => (string) ($section['telegram_url'] ?? ''),
                'TikTok' => (string) ($section['tiktok_url'] ?? ''),
            ];
            ?>
            <section class="builtin-section builtin-section--soft">
                <div class="container app-container">
                    <div class="builtin-section__head">
                        <span class="eyebrow"><?= e((string) ($section['eyebrow'] ?? 'Follow Barama')) ?></span>
                        <h2><?= e((string) ($section['title'] ?? '')) ?></h2>
                        <?php if (trim((string) ($section['description'] ?? '')) !== ''): ?><p><?= e((string) $section['description']) ?></p><?php endif; ?>
                    </div>
                    <div class="builtin-social-grid">
                        <?php foreach ($socials as $label => $href): ?>
                            <?php if (trim($href) === '') continue; ?>
                            <a href="<?= e($href) ?>" target="_blank" rel="noopener noreferrer"><?= app_icon('globe') ?><span><?= e($label) ?></span></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    <?php endforeach; ?>
</main>
