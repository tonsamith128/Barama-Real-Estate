#!/usr/bin/env python3
from pathlib import Path
import sys

if len(sys.argv) != 2:
    raise SystemExit('Usage: patch.py <webroot>')
root = Path(sys.argv[1]).resolve()
view = root/'application/resources/views/workspace/index.php'
repo = root/'application/app/Repositories/WorkspaceReadRepository.php'
css = root/'assets/css/app.css'
for p in (view, repo, css):
    if not p.is_file():
        raise SystemExit(f'ERROR: required file not found: {p}')

v = view.read_text(encoding='utf-8')
r = repo.read_text(encoding='utf-8')
c = css.read_text(encoding='utf-8')

old = "$showAdminMarketplaceNumbers = $workspaceType === 'admin'\n    && in_array($activeSection, ['companies', 'projects', 'properties'], true);\n"
new = "$showAdminMarketplaceNumbers = $workspaceType === 'admin'\n    && in_array($activeSection, ['companies', 'projects', 'properties', 'listings'], true);\n"
if old in v:
    v = v.replace(old, new, 1)
elif new not in v:
    raise SystemExit('ERROR: admin marketplace numbering anchor not found')

old = '<table class="workspace-table">'
new = '''<table class="workspace-table<?= $workspaceType === 'admin' && $activeSection === 'listings'\n                ? ' workspace-table--admin-listings-v1128'\n                : '' ?>">'''
if old in v:
    v = v.replace(old, new, 1)
elif 'workspace-table--admin-listings-v1128' not in v:
    raise SystemExit('ERROR: workspace table anchor not found')

old = '''<?php if ($showAdminPageNumbers && $key === 'title'): ?>
                                            <a
                                                class="workspace-table__title-link"
                                                href="<?= e(url('/dashboard/pages/' . (int) ($row['id'] ?? 0) . '/edit')) ?>"
                                            ><?= e((string) ($row[$key] ?? '—')) ?></a>
'''
new = '''<?php if (
                                            $workspaceType === 'admin'
                                            && $activeSection === 'listings'
                                            && in_array($key, ['property_title', 'company_name', 'project_name'], true)
                                        ): ?>
                                            <?php
                                            $listingRelation = match ($key) {
                                                'property_title' => ['resource' => 'property', 'id' => (int) ($row['property_id'] ?? 0)],
                                                'company_name' => ['resource' => 'company', 'id' => (int) ($row['company_id'] ?? 0)],
                                                'project_name' => ['resource' => 'project', 'id' => (int) ($row['project_id'] ?? 0)],
                                            };
                                            $listingRelationLabel = trim((string) ($row[$key] ?? ''));
                                            ?>
                                            <?php if ($listingRelation['id'] > 0 && $listingRelationLabel !== ''): ?>
                                                <a
                                                    class="workspace-table__title-link workspace-table__relation-link"
                                                    href="<?= e(url('/dashboard/manage/' . $listingRelation['resource'] . '/' . $listingRelation['id'])) ?>"
                                                ><?= e($listingRelationLabel) ?></a>
                                            <?php else: ?>
                                                <?= $formatCell($key, $row) ?>
                                            <?php endif; ?>
                                        <?php elseif ($showAdminPageNumbers && $key === 'title'): ?>
                                            <a
                                                class="workspace-table__title-link"
                                                href="<?= e(url('/dashboard/pages/' . (int) ($row['id'] ?? 0) . '/edit')) ?>"
                                            ><?= e((string) ($row[$key] ?? '—')) ?></a>
'''
if old in v:
    v = v.replace(old, new, 1)
elif 'workspace-table__relation-link' not in v:
    raise SystemExit('ERROR: table cell rendering anchor not found')

old = ''''SELECT l.id, p.title AS property_title, p.reference_code,
                        p.property_type, p.location_id, p.status AS property_status,
                        p.project_id, c.name AS company_name, c.status AS company_status,
'''
new = ''''SELECT l.id, p.id AS property_id, p.title AS property_title, p.reference_code,
                        p.property_type, p.location_id, p.status AS property_status,
                        p.project_id, c.id AS company_id, c.name AS company_name, c.status AS company_status,
'''
if old in r:
    r = r.replace(old, new, 1)
elif 'p.id AS property_id, p.title AS property_title' not in r or 'c.id AS company_id, c.name AS company_name' not in r:
    raise SystemExit('ERROR: admin Listings SELECT anchor not found')

marker = 'BARAMA_ADMIN_LISTINGS_TABLE_V1128'
block = r'''
/* BARAMA_ADMIN_LISTINGS_TABLE_V1128 */
.workspace-table--admin-listings-v1128 .workspace-table__number{width:58px;min-width:58px;text-align:center;font-variant-numeric:tabular-nums}
.workspace-table--admin-listings-v1128 .workspace-table__relation-link{display:inline-flex;align-items:center;max-width:100%;color:var(--barama-primary,#2563eb);font-weight:700;line-height:1.35;text-decoration:none;text-underline-offset:3px}
.workspace-table--admin-listings-v1128 .workspace-table__relation-link:hover,.workspace-table--admin-listings-v1128 .workspace-table__relation-link:focus-visible{text-decoration:underline}
.workspace-table--admin-listings-v1128 .workspace-row-actions__menu form{width:100%;margin:0}
.workspace-table--admin-listings-v1128 .workspace-row-actions__menu .table-action{display:flex!important;width:100%!important;min-height:40px!important;align-items:center!important;justify-content:flex-start!important;margin:0!important;padding:9px 13px!important;border:0!important;background:transparent!important;font:inherit!important;font-size:.875rem!important;font-weight:700!important;line-height:1.25!important;letter-spacing:0!important;text-align:left!important;white-space:nowrap!important}
.workspace-table--admin-listings-v1128 .workspace-row-actions__menu .table-action--danger{color:var(--barama-danger,#dc3545)!important}
.workspace-table--admin-listings-v1128 .workspace-row-actions__menu .table-action:hover,.workspace-table--admin-listings-v1128 .workspace-row-actions__menu .table-action:focus-visible{background:color-mix(in srgb,var(--barama-primary,#2563eb) 8%,transparent)!important}
'''
if marker not in c:
    c = c.rstrip() + '\n\n' + block.strip() + '\n'

checks = [
    "['companies', 'projects', 'properties', 'listings']" in v,
    'workspace-table--admin-listings-v1128' in v,
    'workspace-table__relation-link' in v,
    "'resource' => 'property'" in v,
    "'resource' => 'company'" in v,
    "'resource' => 'project'" in v,
    'p.id AS property_id, p.title AS property_title' in r,
    'c.id AS company_id, c.name AS company_name' in r,
    marker in c,
]
if not all(checks):
    raise SystemExit('ERROR: post-patch contract failed')
view.write_text(v,encoding='utf-8')
repo.write_text(r,encoding='utf-8')
css.write_text(c,encoding='utf-8')
print('PATCH OK: Admin Listings numbering + relationship links + action typography v1.1.28')
