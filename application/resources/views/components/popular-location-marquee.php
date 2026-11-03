<?php
/* BARAMA_POPULAR_LOCATION_MARQUEE_V1124 */
$items = is_array($items ?? null) ? array_values($items) : [];
$items = array_slice($items, 0, 20);
if (count($items) < 20) {
    return;
}

$flagFor = static function (string $countryCode): string {
    $countryCode = strtoupper(trim($countryCode));
    if (preg_match('/^[A-Z]{2}$/', $countryCode) !== 1) {
        return '🌐';
    }

    $first = 127397 + ord($countryCode[0]);
    $second = 127397 + ord($countryCode[1]);

    return html_entity_decode('&#' . $first . ';&#' . $second . ';', ENT_QUOTES | ENT_HTML5, 'UTF-8');
};

$rows = [array_slice($items, 0, 10), array_slice($items, 10, 10)];
?>
<div class="popular-location-marquee" data-popular-location-marquee>
    <?php foreach ($rows as $rowIndex => $rowItems): ?>
        <div
            class="popular-location-marquee__row popular-location-marquee__row--<?= $rowIndex === 0 ? 'forward' : 'reverse' ?>"
            data-popular-location-row="<?= e((string) ($rowIndex + 1)) ?>"
        >
            <div class="popular-location-marquee__viewport">
                <div class="popular-location-marquee__track">
                    <?php for ($copy = 0; $copy < 2; $copy++): ?>
                        <div class="popular-location-marquee__set" <?= $copy === 1 ? 'aria-hidden="true"' : '' ?>>
                            <?php foreach ($rowItems as $item): ?>
                                <?php
                                $name = trim((string) ($item['name'] ?? ''));
                                $countryCode = strtoupper(trim((string) ($item['country_code'] ?? '')));
                                $countryName = trim((string) ($item['country_name'] ?? ''));
                                $filterValue = trim((string) ($item['filter_value'] ?? ''));
                                $href = $filterValue !== ''
                                    ? url('/properties?location=' . rawurlencode($filterValue))
                                    : url('/properties');
                                $label = $countryName !== '' && $countryName !== $name
                                    ? $name . ', ' . $countryName
                                    : $name;
                                ?>
                                <a
                                    class="popular-location-card"
                                    href="<?= e($href) ?>"
                                    aria-label="Explore <?= e($label !== '' ? $label : 'location') ?>"
                                    <?= $copy === 1 ? 'tabindex="-1"' : '' ?>
                                >
                                    <span class="popular-location-card__flag" aria-hidden="true"><?= e($flagFor($countryCode)) ?></span>
                                    <strong><?= e($name !== '' ? $name : ($countryName !== '' ? $countryName : 'Location')) ?></strong>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
