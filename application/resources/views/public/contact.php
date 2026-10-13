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
<main class="builtin-public-page builtin-contact-page barama-a20-contact barama-a21-contact barama-a23-contact">
    <!-- Page-only stylesheet: all Admin section switches/order remain authoritative. -->
    <link rel="stylesheet" href="<?= e(asset('css/contact-premium-v117a20.css')) ?>?v=117a20" media="all">
    <?php if (!empty($hero['enabled'])): ?>
        <?php
        // Contact Hero text always comes from the existing Admin-editable CMS fields.
        $contactHeroRawImage = trim((string) ($hero['image_url'] ?? ''));
        $contactHeroDefaultImage = asset('images/contact/agent-asian-default-v117a17.webp') . '?v=117a19';
        $contactHeroImage = $contactHeroRawImage !== ''
            ? (str_starts_with($contactHeroRawImage, '/') ? url($contactHeroRawImage) : $contactHeroRawImage)
            : $contactHeroDefaultImage;
        $contactHeroCssUrl = json_encode(
            $contactHeroImage,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES
        );
        ?>
        <!-- Versioned Contact-only stylesheet; safe even when the shared head caches A17/A18. -->
        <link rel="stylesheet" href="<?= e(asset('css/contact-hero-v117a17.css')) ?>?v=117a19" media="all">
        <section class="builtin-contact-hero-v117a17 builtin-contact-hero-v117a18 builtin-contact-hero-v117a19<?= $contactHeroRawImage !== '' ? ' has-custom-contact-hero' : '' ?>"
                 style="--barama-contact-photo: url(<?= e((string) $contactHeroCssUrl) ?>)">
            <div class="builtin-contact-hero-v117a18__ambient" aria-hidden="true"></div>
            <div class="builtin-contact-hero-v117a18__subject" aria-hidden="true">
                <img class="builtin-contact-hero-v117a19__photo" src="<?= e($contactHeroImage) ?>" alt="" fetchpriority="high" loading="eager" decoding="async">
            </div>
            <!-- A23 text-side shield: hides legacy decorative backgrounds without covering the photo or the editable copy. -->
            <div class="barama-a23-hero-shield" aria-hidden="true"></div>
            <div class="container app-container builtin-contact-hero-v117a18__container">
                <div class="builtin-hero__copy builtin-contact-hero-v117a18__copy">
                    <span class="eyebrow"><?= e((string) ($hero['eyebrow'] ?? 'Contact Barama')) ?></span>
                    <h1><?= e((string) ($hero['title'] ?? 'Contact Us')) ?></h1>
                    <p><?= e((string) ($hero['description'] ?? '')) ?></p>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <!-- A21 contact-only visual layer, loaded after the A19 Hero styles when present. -->
    <link rel="stylesheet" href="<?= e(asset('css/contact-polish-v117a21.css')) ?>?v=117a21" media="all">
    <!-- A22: remove old decorative Hero pseudo-elements; keep the photographic overlay. -->
    <link rel="stylesheet" href="<?= e(asset('css/contact-hero-circle-fix-v117a22.css')) ?>?v=117a22" media="all">
    <link rel="stylesheet" href="<?= e(asset('css/contact-hero-isolation-v117a23.css')) ?>?v=117a23" media="all">
    <script src="<?= e(asset('js/contact-polish-v117a21.js')) ?>?v=117a21" defer></script>

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
            <section class="builtin-section barama-a20-section barama-a20-optional">
                <div class="container app-container barama-a20-container">
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
            <section class="builtin-section builtin-section--soft barama-a20-section barama-a20-optional">
                <div class="container app-container builtin-contact-office barama-a20-container">
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
            <section class="builtin-section barama-a20-section barama-a20-hours" aria-labelledby="barama-a20-hours-title">
                <div class="container app-container barama-a20-container">
                    <div class="barama-a20-grid barama-a20-hours-grid">
                        <div class="barama-a20-intro">
                            <span class="eyebrow"><?= e((string) ($section['eyebrow'] ?? 'Business hours')) ?></span>
                            <h2 id="barama-a20-hours-title"><?= e((string) ($section['title'] ?? 'When we are available')) ?></h2>
                            <p class="barama-a20-lead">Plan your inquiry around our published business hours. You can send a message using the secure contact form on this page.</p>
                            <?php if (!empty($sections['form']['enabled'])): ?>
                                <div class="barama-a20-inline-note">
                                    <span class="barama-a20-note-icon" aria-hidden="true"><?= app_icon('mail') ?></span>
                                    <span><strong>Send your message anytime</strong><small>We review inquiries during business hours.</small></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="barama-a20-panel barama-a20-schedule">
                            <div class="barama-a20-panel-top">
                                <div><span class="barama-a20-panel-overline">Availability</span><h3>Office schedule</h3></div>
                                <span class="barama-a20-panel-symbol" aria-hidden="true"><?= app_icon('clock') ?></span>
                            </div>
                            <div class="builtin-hours-card barama-a20-hours-rows">
                                <?php foreach ($items as $item): ?>
                                    <?php if (!is_array($item)) continue; ?>
                                    <div><strong><?= e((string) ($item['day'] ?? '')) ?></strong><span><?= e((string) ($item['hours'] ?? '')) ?></span></div>
                                <?php endforeach; ?>
                                <small><?= app_icon('clock') ?> <?= e((string) ($section['timezone'] ?? 'Asia/Phnom_Penh')) ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        <?php elseif ($sectionKey === 'form'): ?>
            <?php $subjects = is_array($section['subjects'] ?? null) ? array_values($section['subjects']) : []; ?>
            <section class="builtin-section builtin-section--soft barama-a20-section barama-a20-message" id="contact-form" aria-labelledby="barama-a20-message-title">
                <div class="container app-container barama-a20-container">
                    <div class="barama-a20-grid barama-a20-form-grid">
                        <div class="builtin-section__copy barama-a20-intro barama-a20-form-intro">
                            <span class="eyebrow"><?= e((string) ($section['eyebrow'] ?? 'Send a message')) ?></span>
                            <h2 id="barama-a20-message-title"><?= e((string) ($section['title'] ?? 'Tell us what you need')) ?></h2>
                            <?php if (trim((string) ($section['description'] ?? '')) !== ''): ?><p class="barama-a20-lead"><?= e((string) $section['description']) ?></p><?php endif; ?>
                            <div class="barama-a20-support-options" aria-label="Ways we can help">
                                <div class="barama-a20-support-option">
                                    <span class="barama-a20-support-icon" aria-hidden="true"><?= app_icon('message') ?></span>
                                    <span><strong>General support</strong><small>Questions about the marketplace and your account.</small></span>
                                </div>
                                <div class="barama-a20-support-option">
                                    <span class="barama-a20-support-icon" aria-hidden="true"><?= app_icon('shield') ?></span>
                                    <span><strong>Technical assistance</strong><small>Report a problem with the website or its features.</small></span>
                                </div>
                                <div class="barama-a20-support-option">
                                    <span class="barama-a20-support-icon" aria-hidden="true"><?= app_icon('mail') ?></span>
                                    <span><strong>Business inquiries</strong><small>Share partnership or collaboration requests.</small></span>
                                </div>
                            </div>
                            <div class="builtin-contact-form-note barama-a20-privacy-note"><?= app_icon('shield') ?><span>Messages are rate-limited and stored securely for administrative follow-up.</span></div>
                        </div>
                        <div class="builtin-contact-form-card barama-a20-form-card">
                            <div class="barama-a20-form-header"><span class="barama-a20-panel-overline">Contact Barama</span><h3>Send your inquiry</h3><p>Complete the fields below and our team can review your message.</p></div>
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
                                    <label class="builtin-contact-consent builtin-form-grid__wide"><input type="checkbox" name="consent" value="1" required aria-describedby="barama-a21-consent-error"><span><?= e((string) $section['consent_text']) ?></span></label>
                                    <p class="barama-a21-consent-error" id="barama-a21-consent-error" role="alert" hidden>Please tick the checkbox to confirm your consent before sending.</p>
                                <?php endif; ?>
                            </div>
                            <button class="btn btn-primary builtin-contact-submit" type="submit"><?= app_icon('mail') ?><span><?= e((string) ($section['button_label'] ?? 'Send message')) ?></span></button>
                        </form>
                            <div class="inquiry-card__notice" data-inquiry-notice hidden></div>
                        </div>
                    </div>
                </div>
            </section>
        <?php elseif ($sectionKey === 'faq'): ?>
            <?php $items = is_array($section['items'] ?? null) ? array_values($section['items']) : []; ?>
            <section class="builtin-section barama-a20-section barama-a20-faq" aria-labelledby="barama-a20-faq-title">
                <div class="container app-container barama-a20-container">
                    <div class="barama-a20-grid barama-a20-faq-grid">
                        <div class="barama-a20-intro">
                            <span class="eyebrow"><?= e((string) ($section['eyebrow'] ?? 'Common questions')) ?></span>
                            <h2 id="barama-a20-faq-title"><?= e((string) ($section['title'] ?? 'Before you send a message')) ?></h2>
                            <p class="barama-a20-lead">Find answers to common questions, or explore the marketplace for information about a property, project, or company.</p>
                            <div class="barama-a20-quick-links" aria-label="Quick help links">
                                <h3>Explore Barama</h3>
                                <a href="<?= e(url('/properties')) ?>"><span><?= app_icon('map') ?> Browse properties</span><span aria-hidden="true">&rarr;</span></a>
                                <a href="<?= e(url('/projects')) ?>"><span><?= app_icon('globe') ?> View projects</span><span aria-hidden="true">&rarr;</span></a>
                                <a href="<?= e(url('/companies')) ?>"><span><?= app_icon('shield') ?> Find companies</span><span aria-hidden="true">&rarr;</span></a>
                                <?php if (!empty($sections['form']['enabled'])): ?>
                                    <a href="#contact-form"><span><?= app_icon('mail') ?> Send a message</span><span aria-hidden="true">&rarr;</span></a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="barama-a20-faq-column">
                            <div class="barama-a20-panel-top barama-a20-faq-head"><div><span class="barama-a20-panel-overline">Help center</span><h3>Frequently asked questions</h3></div><span class="barama-a20-panel-symbol" aria-hidden="true"><?= app_icon('message') ?></span></div>
                            <div class="builtin-faq-list barama-a20-faq-list">
                                <?php foreach ($items as $item): ?>
                                    <?php if (!is_array($item) || trim((string) ($item['question'] ?? '')) === '') continue; ?>
                                    <details><summary><?= e((string) $item['question']) ?><span><?= app_icon('plus') ?></span></summary><p><?= e((string) ($item['answer'] ?? '')) ?></p></details>
                                <?php endforeach; ?>
                            </div>
                        </div>
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
            <section class="builtin-section builtin-section--soft barama-a20-section barama-a20-optional">
                <div class="container app-container barama-a20-container">
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
