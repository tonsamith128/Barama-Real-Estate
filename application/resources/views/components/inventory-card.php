<?php
declare(strict_types=1);

$kind = (string) ($kind ?? '');
$item = is_array($item ?? null) ? $item : [];
$imageUuid = trim((string) ($item['primary_media_uuid'] ?? ''));
$imageAlt = trim((string) ($item['primary_media_alt'] ?? ''));

$humanize = static fn (mixed $value): string =>
    ucwords(str_replace('_', ' ', trim((string) $value)));

if ($kind === 'listing') {
    $href = url('/properties/' . rawurlencode((string) ($item['slug'] ?? '')));
    $title = (string) ($item['title'] ?? 'Property');
    $meta = array_filter([
        $item['location_name'] ?? null,
        isset($item['bedrooms']) && $item['bedrooms'] !== null ? rtrim(rtrim((string) $item['bedrooms'], '0'), '.') . ' bed' : null,
        isset($item['bathrooms']) && $item['bathrooms'] !== null ? rtrim(rtrim((string) $item['bathrooms'], '0'), '.') . ' bath' : null,
    ]);
    $price = ($item['price'] ?? null) !== null
        ? strtoupper((string) ($item['currency'] ?? 'USD')) . ' ' . number_format((float) $item['price'], 0)
        : 'Price on request';
    if (($item['transaction_type'] ?? '') === 'rent' && !empty($item['pricing_period'])) {
        $price .= ' / ' . $humanize($item['pricing_period']);
    }
    $eyebrow = ($item['transaction_type'] ?? '') === 'rent' ? 'For rent' : 'For sale';
    $footer = (string) ($item['company_name'] ?? '');
    $icon = 'home';
} elseif ($kind === 'project') {
    $href = url('/projects/' . rawurlencode((string) ($item['slug'] ?? '')));
    $title = (string) ($item['name'] ?? 'Project');
    $meta = array_filter([
        $item['location_name'] ?? null,
        $humanize($item['project_type'] ?? ''),
        $humanize($item['completion_status'] ?? ''),
    ]);
    $price = '';
    $eyebrow = 'Development';
    $footer = (string) ($item['company_name'] ?? '');
    $icon = 'layers';
} else {
    $kind = 'company';
    $href = url('/company/' . rawurlencode((string) ($item['slug'] ?? '')));
    $title = (string) ($item['name'] ?? 'Company');
    $meta = array_filter([
        $item['location_name'] ?? null,
        $humanize($item['company_type'] ?? ''),
    ]);
    $price = '';
    $eyebrow = !empty($item['verified_at']) ? 'Verified company' : 'Real estate company';
    $projectCount = (int) ($item['project_count'] ?? 0);
    $listingCount = (int) ($item['listing_count'] ?? 0);
    $footer = $projectCount . ' project' . ($projectCount === 1 ? '' : 's')
        . ' · ' . $listingCount . ' listing' . ($listingCount === 1 ? '' : 's');
    $icon = 'building';
}
?>
<article class="inventory-card inventory-card--<?= e($kind) ?>">
    <a class="inventory-card__media" href="<?= e($href) ?>" aria-label="<?= e($title) ?>">
        <span class="inventory-card__ambient" aria-hidden="true"></span>
        <?php if ($imageUuid !== ''): ?>
            <img
                src="<?= e(url('/media/' . rawurlencode($imageUuid))) ?>"
                alt="<?= e($imageAlt !== '' ? $imageAlt : $title) ?>"
                loading="lazy"
                decoding="async"
            >
        <?php else: ?>
            <span class="inventory-card__placeholder" aria-hidden="true">
                <span class="inventory-card__placeholder-icon"><?= app_icon($icon) ?></span>
            </span>
        <?php endif; ?>
        <span class="inventory-card__eyebrow"><?= e($eyebrow) ?></span>
        <span class="inventory-card__open" aria-hidden="true">↗</span>
    </a>
    <div class="inventory-card__body">
        <div class="inventory-card__heading">
            <h3><a href="<?= e($href) ?>"><?= e($title) ?></a></h3>
            <?php if ($price !== ''): ?><strong><?= e($price) ?></strong><?php endif; ?>
        </div>
        <?php if ($meta !== []): ?>
            <p class="inventory-card__meta"><?= e(implode(' · ', array_map('strval', $meta))) ?></p>
        <?php endif; ?>
        <?php if ($footer !== ''): ?>
            <div class="inventory-card__footer">
                <span class="inventory-card__footer-icon"><?= app_icon($kind === 'company' ? 'check' : 'building') ?></span>
                <span><?= e($footer) ?></span>
            </div>
        <?php endif; ?>
    </div>
</article>
