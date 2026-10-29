<?php

declare(strict_types=1);

$seo = is_array($seoCenter ?? null) ? $seoCenter : [];
$summary = is_array($seo['summary'] ?? null) ? $seo['summary'] : [];
$health = is_array($seo['health'] ?? null) ? $seo['health'] : [];
$records = is_array($seo['records'] ?? null) ? $seo['records'] : [];
$rows = is_array($records['rows'] ?? null) ? $records['rows'] : [];
$filters = is_array($seo['filters'] ?? null) ? $seo['filters'] : [];
$discovery = is_array($seo['discovery'] ?? null) ? $seo['discovery'] : [];

$page = max(1, (int) ($records['page'] ?? 1));
$pages = max(1, (int) ($records['pages'] ?? 1));
$total = max(0, (int) ($records['total'] ?? 0));

$query = [
    'q' => (string) ($filters['q'] ?? ''),
    'status' => (string) ($filters['status'] ?? ''),
    'type' => (string) ($filters['type'] ?? ''),
    'sort' => (string) ($filters['sort'] ?? 'updated_desc'),
];
$query = array_filter(
    $query,
    static fn (string $value): bool => $value !== ''
);

$pageUrl = static function (int $target) use ($query): string {
    return url(
        '/dashboard/seo?'
        . http_build_query(
            array_merge($query, ['page' => max(1, $target)])
        )
    );
};

$quickUrl = static function (
    string $status = '',
    string $type = ''
) use ($query): string {
    $params = $query;
    unset($params['status'], $params['type'], $params['page']);

    if ($status !== '') {
        $params['status'] = $status;
    }

    if ($type !== '') {
        $params['type'] = $type;
    }

    return url('/dashboard/seo?' . http_build_query($params));
};

$exportUrl = url(
    '/dashboard/seo/export?'
    . http_build_query($query)
);
?>
<?= barama_css_once('admin') ?>
<?= barama_js_once('admin') ?><script type="application/x-barama-module" data-barama-module="seo-center"
></script>

<section class="workspace-page seo-center" data-seo-center>
    <header class="seo-center__hero">
        <div>
            <span class="eyebrow">Admin Control Center</span>
            <h1>SEO Center</h1>
            <p>
                Monitor public discovery health, prioritize SEO coverage,
                inspect individual records, and hand source fixes back to
                the canonical content owner.
            </p>
        </div>

        <div class="seo-center__hero-actions">
            <a
                class="btn btn-outline-secondary"
                href="<?= e(url('/sitemap.xml')) ?>"
                target="_blank"
                rel="noopener"
            >
                <?= app_icon('search') ?>
                <span>Sitemap</span>
            </a>

            <a
                class="btn btn-outline-secondary"
                href="<?= e($exportUrl) ?>"
            >
                <?= app_icon('download') ?>
                <span>Export audit</span>
            </a>

            <a
                class="btn btn-primary"
                href="<?= e(url('/dashboard/seo')) ?>"
            >
                <?= app_icon('search') ?>
                <span>Recheck</span>
            </a>
        </div>
    </header>

    <?php if (trim((string) ($_GET['seo_error'] ?? '')) !== ''): ?>
        <div class="seo-center__notice is-error">
            <?= app_icon('alert') ?>
            <span><?= e((string) $_GET['seo_error']) ?></span>
        </div>
    <?php endif; ?>

    <div class="seo-center__overview">
        <article
            class="seo-center__health"
            data-barama-seo-health="<?= (int) ($health['score'] ?? 0) ?>"
        >
            <div class="seo-center__health-ring">
                <strong><?= e((string) ($health['score'] ?? 0)) ?></strong>
                <span>/ 100</span>
            </div>

            <div>
                <span class="seo-center__overline">SEO health</span>
                <h2><?= e((string) ($health['label'] ?? '—')) ?></h2>
                <p>
                    Weighted coverage across indexability, descriptions,
                    public images, and image alt text.
                </p>
            </div>
        </article>

        <div class="seo-center__coverage">
            <?php foreach ([
                ['Indexability', 'indexability', 'search'],
                ['Descriptions', 'description', 'edit'],
                ['Social images', 'social_image', 'image'],
                ['Image alt text', 'alt_text', 'check'],
            ] as [$label, $key, $icon]): ?>
                <?php $value = (int) ($health[$key] ?? 0); ?>
                <article>
                    <span class="seo-center__coverage-icon">
                        <?= app_icon($icon) ?>
                    </span>
                    <div>
                        <div class="seo-center__coverage-head">
                            <strong><?= e($label) ?></strong>
                            <span><?= $value ?>%</span>
                        </div>
                        <div class="seo-center__bar">
                            <i data-barama-width="<?= $value ?>"></i>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="seo-center__metrics">
        <?php foreach ([
            ['Public records', $summary['total'] ?? 0, 'globe', $quickUrl()],
            ['Attention', $summary['attention'] ?? 0, 'alert', $quickUrl('attention')],
            ['Indexable', $summary['indexable'] ?? 0, 'search', $quickUrl()],
            ['Missing descriptions', $summary['missing_description'] ?? 0, 'edit', $quickUrl('attention')],
            ['Missing social image', $summary['missing_social_image'] ?? 0, 'image', $quickUrl('attention')],
            ['Missing alt text', $summary['missing_alt_text'] ?? 0, 'alert', $quickUrl('attention')],
        ] as [$label, $value, $icon, $href]): ?>
            <a class="seo-center__metric" href="<?= e((string) $href) ?>">
                <span><?= app_icon((string) $icon) ?></span>
                <div>
                    <strong><?= e((string) $value) ?></strong>
                    <small><?= e((string) $label) ?></small>
                </div>
            </a>
        <?php endforeach; ?>
    </div>

    <section class="seo-center__discovery">
        <div class="seo-center__section-head">
            <div>
                <h2>Public discovery</h2>
                <p>
                    Direct access to the canonical crawl and discovery
                    endpoints used by the public site.
                </p>
            </div>
            <span class="seo-center__readonly">
                <?= app_icon('shield') ?>
                Read-only governance
            </span>
        </div>

        <div class="seo-center__discovery-grid">
            <?php foreach ($discovery as $item): ?>
                <article>
                    <span class="seo-center__discovery-icon">
                        <?= app_icon(
                            ($item['kind'] ?? '') === 'sitemap'
                                ? 'search'
                                : 'shield'
                        ) ?>
                    </span>

                    <div>
                        <strong><?= e((string) ($item['title'] ?? '')) ?></strong>
                        <p><?= e((string) ($item['detail'] ?? '')) ?></p>
                    </div>

                    <a
                        href="<?= e(url((string) ($item['path'] ?? '/'))) ?>"
                        target="_blank"
                        rel="noopener"
                    >Open</a>
                </article>
            <?php endforeach; ?>

            <article>
                <span class="seo-center__discovery-icon">
                    <?= app_icon('lock') ?>
                </span>
                <div>
                    <strong>Ownership boundary</strong>
                    <p>
                        SEO Center audits records; source edits remain in
                        Listings, Projects, Companies, and Page Manager.
                    </p>
                </div>
                <span class="seo-center__status is-good">Protected</span>
            </article>
        </div>
    </section>

    <section class="seo-center__operations">
        <div class="seo-center__section-head">
            <div>
                <h2>SEO operations</h2>
                <p>
                    Filter the indexable estate, inspect coverage,
                    and jump directly to the source owner.
                </p>
            </div>

            <span class="seo-center__records-count">
                <?= $total ?> records · 10 per page
            </span>
        </div>

        <nav class="seo-center__quick" aria-label="SEO quick filters">
            <?php foreach ([
                ['All', '', ''],
                ['Attention', 'attention', ''],
                ['Ready', 'ready', ''],
                ['Listings', '', 'listing'],
                ['Projects', '', 'project'],
                ['Companies', '', 'company'],
                ['Pages', '', 'page'],
            ] as [$label, $status, $type]): ?>
                <?php
                $active = (
                    (string) ($filters['status'] ?? '') === $status
                    && (string) ($filters['type'] ?? '') === $type
                );
                ?>
                <a
                    class="<?= $active ? 'is-active' : '' ?>"
                    href="<?= e($quickUrl($status, $type)) ?>"
                ><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>

        <form
            class="seo-center__filters"
            method="get"
            action="<?= e(url('/dashboard/seo')) ?>"
        >
            <label class="seo-center__search">
                <?= app_icon('search') ?>
                <input
                    type="search"
                    name="q"
                    value="<?= e((string) ($filters['q'] ?? '')) ?>"
                    placeholder="Search public record or path"
                >
            </label>

            <select name="status" aria-label="SEO status">
                <option value="">All statuses</option>
                <option
                    value="attention"
                    <?= ($filters['status'] ?? '') === 'attention' ? 'selected' : '' ?>
                >Attention</option>
                <option
                    value="ready"
                    <?= ($filters['status'] ?? '') === 'ready' ? 'selected' : '' ?>
                >Ready</option>
            </select>

            <select name="type" aria-label="Public record type">
                <option value="">All types</option>
                <?php foreach ([
                    'listing' => 'Listing',
                    'project' => 'Project',
                    'company' => 'Company',
                    'page' => 'Page',
                ] as $value => $label): ?>
                    <option
                        value="<?= e($value) ?>"
                        <?= ($filters['type'] ?? '') === $value ? 'selected' : '' ?>
                    ><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>

            <select name="sort" aria-label="Sort SEO records">
                <?php foreach ([
                    'updated_desc' => 'Recently updated',
                    'updated_asc' => 'Oldest updated',
                    'name_asc' => 'Name A–Z',
                    'name_desc' => 'Name Z–A',
                ] as $value => $label): ?>
                    <option
                        value="<?= e($value) ?>"
                        <?= ($filters['sort'] ?? 'updated_desc') === $value ? 'selected' : '' ?>
                    ><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>

            <button class="btn btn-primary" type="submit">
                <?= app_icon('search') ?>
                Apply
            </button>

            <a class="btn btn-outline-secondary" href="<?= e(url('/dashboard/seo')) ?>">
                Reset
            </a>
        </form>

        <?php if ($rows === []): ?>
            <div class="seo-center__empty">
                <?= app_icon('search') ?>
                <strong>No SEO records match this view</strong>
                <span>
                    Adjust the status, record type, search, or sorting options.
                </span>
            </div>
        <?php else: ?>
            <div class="seo-center__table-wrap">
                <table class="seo-center__table">
                    <thead>
                        <tr>
                            <th>Health</th>
                            <th>Public record</th>
                            <th>Public path</th>
                            <th>Index</th>
                            <th>Description</th>
                            <th>Social image</th>
                            <th>Alt text</th>
                            <th>Updated</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <?php
                            $issues = is_array($row['issues'] ?? null)
                                ? $row['issues']
                                : [];
                            $payload = [
                                'id' => (int) ($row['id'] ?? 0),
                                'type' => (string) ($row['entity_type'] ?? ''),
                                'title' => (string) ($row['title'] ?? ''),
                                'path' => (string) ($row['public_path'] ?? ''),
                                'score' => (int) ($row['seo_score'] ?? 0),
                                'label' => (string) ($row['seo_label'] ?? ''),
                                'index' => (string) ($row['index_state'] ?? ''),
                                'description' => (string) ($row['description_state'] ?? ''),
                                'image' => (string) ($row['image_state'] ?? ''),
                                'alt' => (string) ($row['alt_state'] ?? ''),
                                'edit_url' => (string) ($row['edit_url'] ?? ''),
                                'issues' => $issues,
                            ];
                            ?>
                            <tr>
                                <td>
                                    <button
                                        class="seo-center__score"
                                        type="button"
                                        data-seo-inspect="<?= e(json_encode(
                                            $payload,
                                            JSON_UNESCAPED_SLASHES
                                            | JSON_UNESCAPED_UNICODE
                                        )) ?>"
                                        aria-label="Inspect SEO for <?= e((string) ($row['title'] ?? 'record')) ?>"
                                    >
                                        <strong><?= (int) ($row['seo_score'] ?? 0) ?></strong>
                                        <span><?= e((string) ($row['seo_label'] ?? '')) ?></span>
                                    </button>
                                </td>
                                <td>
                                    <span class="seo-center__record-type">
                                        <?= e(ucfirst((string) ($row['entity_type'] ?? 'record'))) ?>
                                    </span>
                                    <button
                                        class="seo-center__record-title"
                                        type="button"
                                        data-seo-inspect="<?= e(json_encode(
                                            $payload,
                                            JSON_UNESCAPED_SLASHES
                                            | JSON_UNESCAPED_UNICODE
                                        )) ?>"
                                    >
                                        <?= e((string) ($row['title'] ?? 'Untitled')) ?>
                                    </button>
                                </td>
                                <td>
                                    <a
                                        class="seo-center__path"
                                        href="<?= e(url((string) ($row['public_path'] ?? '/'))) ?>"
                                        target="_blank"
                                        rel="noopener"
                                        title="<?= e((string) ($row['public_path'] ?? '')) ?>"
                                    >
                                        <?= e((string) ($row['public_path'] ?? '—')) ?>
                                    </a>
                                </td>
                                <?php foreach ([
                                    'index_state',
                                    'description_state',
                                    'image_state',
                                    'alt_state',
                                ] as $stateKey): ?>
                                    <?php
                                    $state = strtolower(
                                        (string) ($row[$stateKey] ?? 'n/a')
                                    );
                                    $good = in_array(
                                        $state,
                                        ['ready', 'indexable', 'n/a'],
                                        true
                                    );
                                    ?>
                                    <td>
                                        <span class="seo-center__status <?= $good ? 'is-good' : 'is-attention' ?>">
                                            <?= e(
                                                $state === 'n/a'
                                                    ? 'N/A'
                                                    : ucfirst(str_replace('_', ' ', $state))
                                            ) ?>
                                        </span>
                                    </td>
                                <?php endforeach; ?>
                                <td>
                                    <span class="seo-center__updated">
                                        <?= e((string) ($row['updated_at'] ?? '—')) ?>
                                    </span>
                                </td>
                                <td class="seo-center__actions">
                                    <button
                                        type="button"
                                        data-seo-inspect="<?= e(json_encode(
                                            $payload,
                                            JSON_UNESCAPED_SLASHES
                                            | JSON_UNESCAPED_UNICODE
                                        )) ?>"
                                        title="Inspect SEO"
                                    >
                                        <?= app_icon('search') ?>
                                    </button>
                                    <a
                                        href="<?= e((string) ($row['edit_url'] ?? '/dashboard/seo')) ?>"
                                        title="Edit source"
                                    >
                                        <?= app_icon('edit') ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="seo-center__pager">
                <span>
                    Page <?= $page ?> of <?= $pages ?>
                    · <?= $total ?> records
                </span>

                <?php if ($pages > 1): ?>
                    <nav aria-label="SEO Center pages">
                        <?php if ($page > 1): ?>
                            <a href="<?= e($pageUrl($page - 1)) ?>">Previous</a>
                        <?php endif; ?>

                        <?php
                        $from = max(1, $page - 2);
                        $to = min($pages, $page + 2);
                        ?>
                        <?php for ($i = $from; $i <= $to; $i++): ?>
                            <a
                                href="<?= e($pageUrl($i)) ?>"
                                class="<?= $i === $page ? 'is-active' : '' ?>"
                            ><?= $i ?></a>
                        <?php endfor; ?>

                        <?php if ($page < $pages): ?>
                            <a href="<?= e($pageUrl($page + 1)) ?>">Next</a>
                        <?php endif; ?>
                    </nav>

                    <form method="get" action="<?= e(url('/dashboard/seo')) ?>">
                        <?php foreach ($query as $key => $value): ?>
                            <input
                                type="hidden"
                                name="<?= e((string) $key) ?>"
                                value="<?= e((string) $value) ?>"
                            >
                        <?php endforeach; ?>
                        <label>
                            <span>Go To</span>
                            <input
                                type="number"
                                name="page"
                                min="1"
                                max="<?= $pages ?>"
                                value="<?= $page ?>"
                            >
                        </label>
                        <button type="submit">Go</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
</section>

<div
    class="seo-inspector"
    data-seo-inspector
    hidden
    aria-hidden="true"
>
    <button
        class="seo-inspector__backdrop"
        type="button"
        data-seo-close
        aria-label="Close SEO inspector"
    ></button>

    <aside
        class="seo-inspector__panel"
        role="dialog"
        aria-modal="true"
        aria-labelledby="seo-inspector-title"
    >
        <header>
            <div>
                <span class="eyebrow">SEO Inspector</span>
                <h2 id="seo-inspector-title" data-seo-title>Record</h2>
                <p data-seo-path></p>
            </div>
            <button
                type="button"
                class="seo-inspector__close"
                data-seo-close
                aria-label="Close"
            >×</button>
        </header>

        <div class="seo-inspector__body">
            <section class="seo-inspector__scorecard">
                <div class="seo-inspector__score" data-seo-score>0</div>
                <div>
                    <strong data-seo-label>—</strong>
                    <span>SEO coverage score</span>
                </div>
            </section>

            <section>
                <h3>Coverage checklist</h3>
                <div class="seo-inspector__checklist" data-seo-checklist></div>
            </section>

            <section>
                <h3>Priority recommendations</h3>
                <div class="seo-inspector__issues" data-seo-issues></div>
            </section>

            <section>
                <h3>Search appearance</h3>
                <div class="seo-inspector__serp">
                    <span data-seo-serp-path></span>
                    <strong data-seo-serp-title></strong>
                    <p>
                        Source description coverage is checked by SEO Center.
                        Edit the canonical source record to control the public snippet.
                    </p>
                </div>
            </section>

            <section>
                <h3>Source ownership</h3>
                <p class="seo-inspector__ownership">
                    SEO Center is a governance layer. Metadata/content corrections
                    are made in the canonical source workspace rather than duplicated here.
                </p>
            </section>
        </div>

        <footer>
            <button
                class="btn btn-outline-secondary"
                type="button"
                data-seo-copy
            >Copy public URL</button>
            <a
                class="btn btn-outline-secondary"
                href="#"
                target="_blank"
                rel="noopener"
                data-seo-public
            >Open public page</a>
            <a class="btn btn-primary" href="#" data-seo-edit>
                Edit source
            </a>
        </footer>
    </aside>
</div>
