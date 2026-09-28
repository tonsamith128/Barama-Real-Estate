<?php

declare(strict_types=1);

$appearance = is_array($appearance ?? null)
    ? $appearance
    : \Barama\Repositories\AppearanceRepository::defaults();

$rows = [
    'typography' => ['label' => 'Typography', 'icon' => 'edit', 'description' => 'Line height and letter spacing for body and headings.', 'summary' => 'Body ' . ($appearance['typography']['body_line_height'] ?? 1.6) . ' · Heading ' . ($appearance['typography']['heading_line_height'] ?? 1.08)],
    'font_family' => ['label' => 'Font Family', 'icon' => 'edit', 'description' => 'Choose safe local font stacks independently for body and headings.', 'summary' => ucfirst((string) ($appearance['font_family']['body'] ?? 'inter')) . ' / ' . ucfirst((string) ($appearance['font_family']['heading'] ?? 'inter'))],
    'font_size' => ['label' => 'Font Size', 'icon' => 'edit', 'description' => 'Global root size plus small-text and navigation scaling.', 'summary' => (string) ($appearance['font_size']['base_px'] ?? 16) . 'px base'],
    'color' => ['label' => 'Color', 'icon' => 'image', 'description' => 'Complete Light and Dark palettes for surfaces, text, borders and brand colors.', 'summary' => (string) ($appearance['color']['light']['primary'] ?? '#165dff') . ' / ' . (string) ($appearance['color']['dark']['primary'] ?? '#6d98ff')],
    'background' => ['label' => 'Background', 'icon' => 'image', 'description' => 'Page background style, gradient target, pattern and fixed behavior.', 'summary' => ucwords(str_replace('-', ' ', (string) ($appearance['background']['style'] ?? 'solid')))],
    'font_style' => ['label' => 'Font Style', 'icon' => 'edit', 'description' => 'Body, heading and navigation weights, heading transform and smoothing.', 'summary' => 'Body ' . ($appearance['font_style']['body_weight'] ?? 400) . ' · Heading ' . ($appearance['font_style']['heading_weight'] ?? 850)],
    'border' => ['label' => 'Border', 'icon' => 'grid', 'description' => 'Global border width, style and visibility strength.', 'summary' => (string) ($appearance['border']['width'] ?? 1) . 'px ' . (string) ($appearance['border']['style'] ?? 'solid')],
    'shadow' => ['label' => 'Shadow', 'icon' => 'layers', 'description' => 'Surface elevation level and hover elevation behavior.', 'summary' => ucfirst((string) ($appearance['shadow']['level'] ?? 'soft'))],
    'preset' => ['label' => 'Preset', 'icon' => 'settings', 'description' => 'Apply a coordinated design preset without deleting custom values.', 'summary' => ucfirst((string) ($appearance['preset']['name'] ?? 'barama'))],
    'skin' => ['label' => 'Skin', 'icon' => 'layers', 'description' => 'Modern, Soft, Sharp, Glass or Luxury surface treatment.', 'summary' => ucfirst((string) ($appearance['skin']['name'] ?? 'modern'))],
    'layout' => ['label' => 'Layout', 'icon' => 'grid', 'description' => 'Container width, page gutter, section rhythm and overall density.', 'summary' => (string) ($appearance['layout']['container_width'] ?? 1220) . 'px · ' . ucfirst((string) ($appearance['layout']['density'] ?? 'comfortable'))],
    'radius_spacing' => ['label' => 'Radius & Spacing', 'icon' => 'grid', 'description' => 'Global corner radii and spacing scale for reusable frontend surfaces.', 'summary' => 'Cards ' . (string) ($appearance['radius_spacing']['card_radius'] ?? 18) . 'px'],
    'buttons' => ['label' => 'Buttons', 'icon' => 'check', 'description' => 'Button treatment, radius, weight and uppercase behavior.', 'summary' => ucfirst((string) ($appearance['buttons']['style'] ?? 'solid'))],
    'cards' => ['label' => 'Cards', 'icon' => 'layers', 'description' => 'Card surface style, radius, border and hover lift.', 'summary' => ucfirst((string) ($appearance['cards']['style'] ?? 'elevated'))],
    'forms' => ['label' => 'Forms', 'icon' => 'edit', 'description' => 'Inputs and selects: style, height, radius and focus ring.', 'summary' => ucfirst((string) ($appearance['forms']['style'] ?? 'outlined')) . ' · ' . (string) ($appearance['forms']['height'] ?? 48) . 'px'],
    'header' => ['label' => 'Header & Navigation', 'icon' => 'menu', 'description' => 'Public header height, sticky mode, blur, shadow and navigation style.', 'summary' => ucfirst((string) ($appearance['header']['nav_style'] ?? 'soft')) . ' navigation'],
    'footer' => ['label' => 'Footer', 'icon' => 'layers', 'description' => 'Public footer surface style, divider and vertical spacing.', 'summary' => ucfirst((string) ($appearance['footer']['style'] ?? 'standard'))],
    'motion' => ['label' => 'Motion & Effects', 'icon' => 'settings', 'description' => 'Transitions, hover movement, smooth scrolling and reduced-motion behavior.', 'summary' => (string) ($appearance['motion']['duration'] ?? 160) . 'ms'],
];

$fontOptions = [
    'inter' => 'Inter / UI Sans',
    'system' => 'System UI',
    'geometric' => 'Geometric',
    'humanist' => 'Humanist',
    'serif' => 'Serif',
    'mono' => 'Monospace',
];

$paletteKeys = [
    'background' => 'Page background',
    'surface' => 'Surface',
    'surface_soft' => 'Soft surface',
    'text' => 'Text',
    'muted' => 'Muted text',
    'border' => 'Border',
    'primary' => 'Primary',
    'primary_strong' => 'Primary strong',
    'success' => 'Success',
    'danger' => 'Danger',
    'link' => 'Link',
];
?>
<section class="workspace-page content-config-page appearance-manager">
    <div class="workspace-page__header">
        <div>
            <span class="eyebrow"><?= e($workspaceLabel ?? 'Admin Control Center') ?></span>
            <h1>Appearance</h1>
            <p>Control the complete frontend design system from one place. Disabled modules fall back to Barama defaults and do not affect the Admin workspace.</p>
        </div>
        <div class="appearance-manager__header-actions">
            <a class="content-config-button" href="<?= e(url('/')) ?>" target="_blank" rel="noopener noreferrer"><?= app_icon('globe') ?><span>Preview frontend</span></a>
            <form action="<?= e(url('/dashboard/appearance/reset')) ?>" method="post" data-appearance-form>
                <?= csrf_field() ?>
                <button class="content-config-button table-action--danger" type="submit"><?= app_icon('settings') ?><span>Reset defaults</span></button>
            </form>
        </div>
    </div>

    <div class="appearance-manager__status">
        <span><?= app_icon('check') ?> Frontend stylesheet</span>
        <code>/appearance.css?v=<?= e((string) ($appearanceVersion ?? '')) ?></code>
        <small>Public and authentication pages only · Admin, Developer and Account workspaces keep their canonical UI.</small>
    </div>

    <?php
    $appearanceSaved = (string) ($_GET['appearance_saved'] ?? '');
    $appearanceError = trim((string) ($_GET['appearance_error'] ?? ''));
    ?>
    <div data-workspace-notice<?= ($appearanceSaved === '1' || $appearanceError !== '') ? '' : ' hidden' ?> class="<?= $appearanceError !== '' ? 'is-error' : ($appearanceSaved === '1' ? 'is-success' : '') ?>">
        <?php if ($appearanceError !== ''): ?>
            <?= e($appearanceError) ?>
        <?php elseif ($appearanceSaved === '1'): ?>
            Appearance changes saved successfully.
        <?php endif; ?>
    </div>

    <div class="content-config-table-wrap">
        <table class="content-config-table">
            <thead>
                <tr>
                    <th>Feature</th>
                    <th>Current configuration</th>
                    <th>Status</th>
                    <th class="content-config-table__action">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $key => $row): ?>
                    <?php $enabled = !empty($appearance[$key]['enabled']); ?>
                    <tr>
                        <td>
                            <div class="content-config-feature">
                                <span class="content-config-feature__icon"><?= app_icon((string) $row['icon']) ?></span>
                                <span><strong><?= e((string) $row['label']) ?></strong><small><?= e((string) $row['description']) ?></small></span>
                            </div>
                        </td>
                        <td><span class="content-config-summary"><?= e((string) $row['summary']) ?></span></td>
                        <td>
                            <form action="<?= e(url('/dashboard/appearance/' . $key . '/toggle')) ?>" method="post" data-appearance-form data-toggle-form>
                                <?= csrf_field() ?>
                                <input type="hidden" name="enabled" value="<?= $enabled ? '1' : '0' ?>" data-toggle-value>
                                <label class="premium-switch">
                                    <input type="checkbox" <?= $enabled ? 'checked' : '' ?> data-auto-toggle aria-label="Toggle <?= e((string) $row['label']) ?>">
                                    <span class="premium-switch__track"><span></span></span>
                                    <b><?= $enabled ? 'ON' : 'OFF' ?></b>
                                </label>
                            </form>
                        </td>
                        <td class="content-config-table__action">
                            <button class="content-config-button" type="button" data-configure-open="appearance-config-<?= e($key) ?>"><?= app_icon('settings') ?><span>Configure</span></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <dialog class="config-dialog" id="appearance-config-typography" data-config-dialog>
        <form class="config-dialog__panel" action="<?= e(url('/dashboard/appearance/typography')) ?>" method="post" data-appearance-form>
            <?= csrf_field() ?>
            <header class="config-dialog__head"><div><span class="eyebrow">Appearance</span><h2>Typography</h2><p>Fine-tune reading rhythm and heading character across every frontend page.</p></div><button type="button" data-configure-close aria-label="Close"><?= app_icon('plus') ?></button></header>
            <div class="config-dialog__body config-field-grid">
                <label><span>Body line height</span><input class="form-control" type="number" step="0.05" min="1.2" max="2" name="body_line_height" value="<?= e((string) $appearance['typography']['body_line_height']) ?>"></label>
                <label><span>Heading line height</span><input class="form-control" type="number" step="0.01" min="0.9" max="1.6" name="heading_line_height" value="<?= e((string) $appearance['typography']['heading_line_height']) ?>"></label>
                <label><span>Body letter spacing (em)</span><input class="form-control" type="number" step="0.005" min="-0.05" max="0.1" name="body_letter_spacing" value="<?= e((string) $appearance['typography']['body_letter_spacing']) ?>"></label>
                <label><span>Heading letter spacing (em)</span><input class="form-control" type="number" step="0.005" min="-0.12" max="0.08" name="heading_letter_spacing" value="<?= e((string) $appearance['typography']['heading_letter_spacing']) ?>"></label>
            </div>
            <footer class="config-dialog__footer"><button class="btn btn-outline-secondary" type="button" data-configure-close>Cancel</button><button class="btn btn-primary" type="submit">Save typography</button></footer>
        </form>
    </dialog>

    <dialog class="config-dialog" id="appearance-config-font_family" data-config-dialog>
        <form class="config-dialog__panel" action="<?= e(url('/dashboard/appearance/font_family')) ?>" method="post" data-appearance-form>
            <?= csrf_field() ?>
            <header class="config-dialog__head"><div><span class="eyebrow">Appearance</span><h2>Font Family</h2><p>Uses local system-safe stacks only; no external font dependency is introduced.</p></div><button type="button" data-configure-close aria-label="Close"><?= app_icon('plus') ?></button></header>
            <div class="config-dialog__body config-field-grid">
                <?php foreach (['body' => 'Body font', 'heading' => 'Heading font'] as $field => $label): ?>
                    <label><span><?= e($label) ?></span><select class="form-select" name="<?= e($field) ?>"><?php foreach ($fontOptions as $value => $name): ?><option value="<?= e($value) ?>" <?= (string) $appearance['font_family'][$field] === $value ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?></select></label>
                <?php endforeach; ?>
            </div>
            <footer class="config-dialog__footer"><button class="btn btn-outline-secondary" type="button" data-configure-close>Cancel</button><button class="btn btn-primary" type="submit">Save font family</button></footer>
        </form>
    </dialog>

    <dialog class="config-dialog" id="appearance-config-font_size" data-config-dialog>
        <form class="config-dialog__panel" action="<?= e(url('/dashboard/appearance/font_size')) ?>" method="post" data-appearance-form>
            <?= csrf_field() ?>
            <header class="config-dialog__head"><div><span class="eyebrow">Appearance</span><h2>Font Size</h2><p>The base size changes rem-based typography across the entire frontend.</p></div><button type="button" data-configure-close aria-label="Close"><?= app_icon('plus') ?></button></header>
            <div class="config-dialog__body config-field-grid">
                <label><span>Base size (px)</span><input class="form-control" type="number" min="14" max="20" name="base_px" value="<?= e((string) $appearance['font_size']['base_px']) ?>"></label>
                <label><span>Small-text scale</span><input class="form-control" type="number" step="0.025" min="0.72" max="1" name="small_scale" value="<?= e((string) $appearance['font_size']['small_scale']) ?>"></label>
                <label><span>Navigation scale</span><input class="form-control" type="number" step="0.025" min="0.78" max="1.15" name="nav_scale" value="<?= e((string) $appearance['font_size']['nav_scale']) ?>"></label>
            </div>
            <footer class="config-dialog__footer"><button class="btn btn-outline-secondary" type="button" data-configure-close>Cancel</button><button class="btn btn-primary" type="submit">Save font size</button></footer>
        </form>
    </dialog>

    <dialog class="config-dialog config-dialog--wide" id="appearance-config-color" data-config-dialog>
        <form class="config-dialog__panel" action="<?= e(url('/dashboard/appearance/color')) ?>" method="post" data-appearance-form>
            <?= csrf_field() ?>
            <header class="config-dialog__head"><div><span class="eyebrow">Appearance</span><h2>Color</h2><p>Separate Light and Dark palettes keep both themes fully customizable and accessible.</p></div><button type="button" data-configure-close aria-label="Close"><?= app_icon('plus') ?></button></header>
            <div class="config-dialog__body appearance-color-grid">
                <?php foreach (['light' => 'Light theme', 'dark' => 'Dark theme'] as $theme => $themeLabel): ?>
                    <section><h3><?= e($themeLabel) ?></h3><div class="appearance-color-fields">
                    <?php foreach ($paletteKeys as $field => $label): ?>
                        <label><span><?= e($label) ?></span><div class="appearance-color-control"><input type="color" name="<?= e($theme) ?>[<?= e($field) ?>]" value="<?= e((string) $appearance['color'][$theme][$field]) ?>"><code><?= e((string) $appearance['color'][$theme][$field]) ?></code></div></label>
                    <?php endforeach; ?>
                    </div></section>
                <?php endforeach; ?>
            </div>
            <footer class="config-dialog__footer"><button class="btn btn-outline-secondary" type="button" data-configure-close>Cancel</button><button class="btn btn-primary" type="submit">Save color palette</button></footer>
        </form>
    </dialog>

    <dialog class="config-dialog" id="appearance-config-background" data-config-dialog>
        <form class="config-dialog__panel" action="<?= e(url('/dashboard/appearance/background')) ?>" method="post" data-appearance-form>
            <?= csrf_field() ?>
            <header class="config-dialog__head"><div><span class="eyebrow">Appearance</span><h2>Background</h2></div><button type="button" data-configure-close aria-label="Close"><?= app_icon('plus') ?></button></header>
            <div class="config-dialog__body config-field-grid">
                <label><span>Style</span><select class="form-select" name="style"><?php foreach (['solid'=>'Solid','gradient'=>'Gradient','soft-gradient'=>'Soft Gradient'] as $v=>$l): ?><option value="<?= e($v) ?>" <?= $appearance['background']['style']===$v?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
                <label><span>Pattern</span><select class="form-select" name="pattern"><?php foreach (['none'=>'None','dots'=>'Dots','grid'=>'Grid'] as $v=>$l): ?><option value="<?= e($v) ?>" <?= $appearance['background']['pattern']===$v?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
                <label><span>Light gradient target</span><input class="form-control" type="color" name="light_to" value="<?= e((string) $appearance['background']['light_to']) ?>"></label>
                <label><span>Dark gradient target</span><input class="form-control" type="color" name="dark_to" value="<?= e((string) $appearance['background']['dark_to']) ?>"></label>
                <label class="premium-inline-toggle config-field-grid__wide"><input type="checkbox" name="fixed" value="1" <?= !empty($appearance['background']['fixed'])?'checked':'' ?>><span>Keep page background fixed while scrolling</span></label>
            </div>
            <footer class="config-dialog__footer"><button class="btn btn-outline-secondary" type="button" data-configure-close>Cancel</button><button class="btn btn-primary" type="submit">Save background</button></footer>
        </form>
    </dialog>

    <dialog class="config-dialog" id="appearance-config-font_style" data-config-dialog>
        <form class="config-dialog__panel" action="<?= e(url('/dashboard/appearance/font_style')) ?>" method="post" data-appearance-form>
            <?= csrf_field() ?>
            <header class="config-dialog__head"><div><span class="eyebrow">Appearance</span><h2>Font Style</h2></div><button type="button" data-configure-close aria-label="Close"><?= app_icon('plus') ?></button></header>
            <div class="config-dialog__body config-field-grid">
                <?php foreach (['body_weight'=>['Body weight',[400,500,600]],'heading_weight'=>['Heading weight',[600,700,800,850,900]],'nav_weight'=>['Navigation weight',[500,600,700,800,900]]] as $field=>$spec): ?>
                    <label><span><?= e($spec[0]) ?></span><select class="form-select" name="<?= e($field) ?>"><?php foreach ($spec[1] as $weight): ?><option value="<?= $weight ?>" <?= (int)$appearance['font_style'][$field]===$weight?'selected':'' ?>><?= $weight ?></option><?php endforeach; ?></select></label>
                <?php endforeach; ?>
                <label><span>Heading transform</span><select class="form-select" name="heading_transform"><option value="none" <?= $appearance['font_style']['heading_transform']==='none'?'selected':'' ?>>Normal</option><option value="uppercase" <?= $appearance['font_style']['heading_transform']==='uppercase'?'selected':'' ?>>Uppercase</option></select></label>
                <label class="premium-inline-toggle config-field-grid__wide"><input type="checkbox" name="smoothing" value="1" <?= !empty($appearance['font_style']['smoothing'])?'checked':'' ?>><span>Font smoothing and optimized legibility</span></label>
            </div>
            <footer class="config-dialog__footer"><button class="btn btn-outline-secondary" type="button" data-configure-close>Cancel</button><button class="btn btn-primary" type="submit">Save font style</button></footer>
        </form>
    </dialog>

    <dialog class="config-dialog" id="appearance-config-border" data-config-dialog>
        <form class="config-dialog__panel" action="<?= e(url('/dashboard/appearance/border')) ?>" method="post" data-appearance-form>
            <?= csrf_field() ?><header class="config-dialog__head"><div><span class="eyebrow">Appearance</span><h2>Border</h2></div><button type="button" data-configure-close aria-label="Close"><?= app_icon('plus') ?></button></header>
            <div class="config-dialog__body config-field-grid">
                <label><span>Width (px)</span><input class="form-control" type="number" name="width" min="0" max="3" value="<?= e((string)$appearance['border']['width']) ?>"></label>
                <label><span>Style</span><select class="form-select" name="style"><option value="solid" <?= $appearance['border']['style']==='solid'?'selected':'' ?>>Solid</option><option value="dashed" <?= $appearance['border']['style']==='dashed'?'selected':'' ?>>Dashed</option></select></label>
                <label><span>Opacity (%)</span><input class="form-control" type="number" name="opacity" min="0" max="100" value="<?= e((string)$appearance['border']['opacity']) ?>"></label>
            </div><footer class="config-dialog__footer"><button class="btn btn-outline-secondary" type="button" data-configure-close>Cancel</button><button class="btn btn-primary" type="submit">Save border</button></footer>
        </form>
    </dialog>

    <dialog class="config-dialog" id="appearance-config-shadow" data-config-dialog>
        <form class="config-dialog__panel" action="<?= e(url('/dashboard/appearance/shadow')) ?>" method="post" data-appearance-form>
            <?= csrf_field() ?><header class="config-dialog__head"><div><span class="eyebrow">Appearance</span><h2>Shadow</h2></div><button type="button" data-configure-close aria-label="Close"><?= app_icon('plus') ?></button></header>
            <div class="config-dialog__body config-field-grid">
                <label><span>Elevation</span><select class="form-select" name="level"><?php foreach (['none'=>'None','soft'=>'Soft','medium'=>'Medium','strong'=>'Strong'] as $v=>$l): ?><option value="<?= e($v) ?>" <?= $appearance['shadow']['level']===$v?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
                <label class="premium-inline-toggle"><input type="checkbox" name="hover" value="1" <?= !empty($appearance['shadow']['hover'])?'checked':'' ?>><span>Elevate interactive surfaces on hover</span></label>
            </div><footer class="config-dialog__footer"><button class="btn btn-outline-secondary" type="button" data-configure-close>Cancel</button><button class="btn btn-primary" type="submit">Save shadow</button></footer>
        </form>
    </dialog>

    <dialog class="config-dialog" id="appearance-config-preset" data-config-dialog>
        <form class="config-dialog__panel" action="<?= e(url('/dashboard/appearance/preset')) ?>" method="post" data-appearance-form>
            <?= csrf_field() ?><header class="config-dialog__head"><div><span class="eyebrow">Appearance</span><h2>Preset</h2><p>When Preset is ON it temporarily overrides coordinated tokens; your custom module values remain stored underneath.</p></div><button type="button" data-configure-close aria-label="Close"><?= app_icon('plus') ?></button></header>
            <div class="config-dialog__body"><div class="appearance-preset-grid"><?php foreach (['barama'=>'Barama','ocean'=>'Ocean','luxury'=>'Luxury','warm'=>'Warm','minimal'=>'Minimal','mono'=>'Mono'] as $v=>$l): ?><label class="appearance-preset-option"><input type="radio" name="name" value="<?= e($v) ?>" <?= $appearance['preset']['name']===$v?'checked':'' ?>><span><strong><?= e($l) ?></strong><small><?= e(match($v){'ocean'=>'Blue glass marketplace','luxury'=>'Serif + warm metallic accents','warm'=>'Warm coral surfaces','minimal'=>'Neutral sharp system','mono'=>'Monospace technical skin',default=>'Canonical Barama design'}) ?></small></span></label><?php endforeach; ?></div></div>
            <footer class="config-dialog__footer"><button class="btn btn-outline-secondary" type="button" data-configure-close>Cancel</button><button class="btn btn-primary" type="submit">Save preset</button></footer>
        </form>
    </dialog>

    <dialog class="config-dialog" id="appearance-config-skin" data-config-dialog>
        <form class="config-dialog__panel" action="<?= e(url('/dashboard/appearance/skin')) ?>" method="post" data-appearance-form>
            <?= csrf_field() ?><header class="config-dialog__head"><div><span class="eyebrow">Appearance</span><h2>Skin</h2></div><button type="button" data-configure-close aria-label="Close"><?= app_icon('plus') ?></button></header>
            <div class="config-dialog__body config-field-grid"><label class="config-field-grid__wide"><span>Surface skin</span><select class="form-select" name="name"><?php foreach (['modern'=>'Modern','soft'=>'Soft','sharp'=>'Sharp','glass'=>'Glass','luxury'=>'Luxury'] as $v=>$l): ?><option value="<?= e($v) ?>" <?= $appearance['skin']['name']===$v?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></label></div>
            <footer class="config-dialog__footer"><button class="btn btn-outline-secondary" type="button" data-configure-close>Cancel</button><button class="btn btn-primary" type="submit">Save skin</button></footer>
        </form>
    </dialog>

    <dialog class="config-dialog" id="appearance-config-layout" data-config-dialog>
        <form class="config-dialog__panel" action="<?= e(url('/dashboard/appearance/layout')) ?>" method="post" data-appearance-form>
            <?= csrf_field() ?><header class="config-dialog__head"><div><span class="eyebrow">Appearance</span><h2>Layout</h2></div><button type="button" data-configure-close aria-label="Close"><?= app_icon('plus') ?></button></header>
            <div class="config-dialog__body config-field-grid">
                <label><span>Container width (px)</span><input class="form-control" type="number" min="1040" max="1600" step="10" name="container_width" value="<?= e((string)$appearance['layout']['container_width']) ?>"></label>
                <label><span>Page gutter (px)</span><input class="form-control" type="number" min="12" max="48" name="page_gutter" value="<?= e((string)$appearance['layout']['page_gutter']) ?>"></label>
                <label><span>Section spacing scale</span><input class="form-control" type="number" min="0.75" max="1.5" step="0.05" name="section_spacing" value="<?= e((string)$appearance['layout']['section_spacing']) ?>"></label>
                <label><span>Density</span><select class="form-select" name="density"><?php foreach (['compact'=>'Compact','comfortable'=>'Comfortable','spacious'=>'Spacious'] as $v=>$l): ?><option value="<?= e($v) ?>" <?= $appearance['layout']['density']===$v?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
            </div><footer class="config-dialog__footer"><button class="btn btn-outline-secondary" type="button" data-configure-close>Cancel</button><button class="btn btn-primary" type="submit">Save layout</button></footer>
        </form>
    </dialog>

    <dialog class="config-dialog" id="appearance-config-radius_spacing" data-config-dialog>
        <form class="config-dialog__panel" action="<?= e(url('/dashboard/appearance/radius_spacing')) ?>" method="post" data-appearance-form>
            <?= csrf_field() ?><header class="config-dialog__head"><div><span class="eyebrow">Appearance</span><h2>Radius & Spacing</h2></div><button type="button" data-configure-close aria-label="Close"><?= app_icon('plus') ?></button></header>
            <div class="config-dialog__body config-field-grid">
                <?php foreach (['radius_sm'=>['Small radius',0,24],'radius_md'=>['Medium radius',0,32],'radius_lg'=>['Large radius',0,48],'control_radius'=>['Control radius',0,24],'card_radius'=>['Card radius',0,32]] as $field=>$spec): ?><label><span><?= e($spec[0]) ?> (px)</span><input class="form-control" type="number" name="<?= e($field) ?>" min="<?= $spec[1] ?>" max="<?= $spec[2] ?>" value="<?= e((string)$appearance['radius_spacing'][$field]) ?>"></label><?php endforeach; ?>
                <label><span>Gap scale</span><input class="form-control" type="number" min="0.75" max="1.5" step="0.05" name="gap_scale" value="<?= e((string)$appearance['radius_spacing']['gap_scale']) ?>"></label>
            </div><footer class="config-dialog__footer"><button class="btn btn-outline-secondary" type="button" data-configure-close>Cancel</button><button class="btn btn-primary" type="submit">Save radius & spacing</button></footer>
        </form>
    </dialog>

    <dialog class="config-dialog" id="appearance-config-buttons" data-config-dialog>
        <form class="config-dialog__panel" action="<?= e(url('/dashboard/appearance/buttons')) ?>" method="post" data-appearance-form>
            <?= csrf_field() ?><header class="config-dialog__head"><div><span class="eyebrow">Appearance</span><h2>Buttons</h2></div><button type="button" data-configure-close aria-label="Close"><?= app_icon('plus') ?></button></header>
            <div class="config-dialog__body config-field-grid">
                <label><span>Style</span><select class="form-select" name="style"><?php foreach (['solid'=>'Solid','soft'=>'Soft','outline'=>'Outline','gradient'=>'Gradient'] as $v=>$l): ?><option value="<?= e($v) ?>" <?= $appearance['buttons']['style']===$v?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
                <label><span>Radius (px)</span><input class="form-control" type="number" min="0" max="30" name="radius" value="<?= e((string)$appearance['buttons']['radius']) ?>"></label>
                <label><span>Weight</span><select class="form-select" name="weight"><?php foreach ([600,700,800,900] as $v): ?><option value="<?= $v ?>" <?= (int)$appearance['buttons']['weight']===$v?'selected':'' ?>><?= $v ?></option><?php endforeach; ?></select></label>
                <label class="premium-inline-toggle"><input type="checkbox" name="uppercase" value="1" <?= !empty($appearance['buttons']['uppercase'])?'checked':'' ?>><span>Uppercase button labels</span></label>
            </div><footer class="config-dialog__footer"><button class="btn btn-outline-secondary" type="button" data-configure-close>Cancel</button><button class="btn btn-primary" type="submit">Save buttons</button></footer>
        </form>
    </dialog>

    <dialog class="config-dialog" id="appearance-config-cards" data-config-dialog>
        <form class="config-dialog__panel" action="<?= e(url('/dashboard/appearance/cards')) ?>" method="post" data-appearance-form>
            <?= csrf_field() ?><header class="config-dialog__head"><div><span class="eyebrow">Appearance</span><h2>Cards</h2></div><button type="button" data-configure-close aria-label="Close"><?= app_icon('plus') ?></button></header>
            <div class="config-dialog__body config-field-grid">
                <label><span>Style</span><select class="form-select" name="style"><?php foreach (['elevated'=>'Elevated','outlined'=>'Outlined','flat'=>'Flat','glass'=>'Glass'] as $v=>$l): ?><option value="<?= e($v) ?>" <?= $appearance['cards']['style']===$v?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
                <label><span>Radius (px)</span><input class="form-control" type="number" min="0" max="32" name="radius" value="<?= e((string)$appearance['cards']['radius']) ?>"></label>
                <label class="premium-inline-toggle"><input type="checkbox" name="border" value="1" <?= !empty($appearance['cards']['border'])?'checked':'' ?>><span>Show card borders</span></label>
                <label class="premium-inline-toggle"><input type="checkbox" name="hover_lift" value="1" <?= !empty($appearance['cards']['hover_lift'])?'checked':'' ?>><span>Lift interactive cards on hover</span></label>
            </div><footer class="config-dialog__footer"><button class="btn btn-outline-secondary" type="button" data-configure-close>Cancel</button><button class="btn btn-primary" type="submit">Save cards</button></footer>
        </form>
    </dialog>

    <dialog class="config-dialog" id="appearance-config-forms" data-config-dialog>
        <form class="config-dialog__panel" action="<?= e(url('/dashboard/appearance/forms')) ?>" method="post" data-appearance-form>
            <?= csrf_field() ?><header class="config-dialog__head"><div><span class="eyebrow">Appearance</span><h2>Forms</h2></div><button type="button" data-configure-close aria-label="Close"><?= app_icon('plus') ?></button></header>
            <div class="config-dialog__body config-field-grid">
                <label><span>Control style</span><select class="form-select" name="style"><?php foreach (['outlined'=>'Outlined','filled'=>'Filled','soft'=>'Soft'] as $v=>$l): ?><option value="<?= e($v) ?>" <?= $appearance['forms']['style']===$v?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
                <label><span>Height (px)</span><input class="form-control" type="number" min="40" max="60" name="height" value="<?= e((string)$appearance['forms']['height']) ?>"></label>
                <label><span>Radius (px)</span><input class="form-control" type="number" min="0" max="24" name="radius" value="<?= e((string)$appearance['forms']['radius']) ?>"></label>
                <label class="premium-inline-toggle"><input type="checkbox" name="focus_ring" value="1" <?= !empty($appearance['forms']['focus_ring'])?'checked':'' ?>><span>Visible keyboard focus ring</span></label>
            </div><footer class="config-dialog__footer"><button class="btn btn-outline-secondary" type="button" data-configure-close>Cancel</button><button class="btn btn-primary" type="submit">Save forms</button></footer>
        </form>
    </dialog>

    <dialog class="config-dialog" id="appearance-config-header" data-config-dialog>
        <form class="config-dialog__panel" action="<?= e(url('/dashboard/appearance/header')) ?>" method="post" data-appearance-form>
            <?= csrf_field() ?><header class="config-dialog__head"><div><span class="eyebrow">Appearance</span><h2>Header & Navigation</h2></div><button type="button" data-configure-close aria-label="Close"><?= app_icon('plus') ?></button></header>
            <div class="config-dialog__body config-field-grid">
                <label><span>Header height (px)</span><input class="form-control" type="number" min="60" max="96" name="height" value="<?= e((string)$appearance['header']['height']) ?>"></label>
                <label><span>Backdrop blur (px)</span><input class="form-control" type="number" min="0" max="30" name="blur" value="<?= e((string)$appearance['header']['blur']) ?>"></label>
                <label><span>Navigation style</span><select class="form-select" name="nav_style"><?php foreach (['soft'=>'Soft','pill'=>'Pill','underline'=>'Underline','minimal'=>'Minimal'] as $v=>$l): ?><option value="<?= e($v) ?>" <?= $appearance['header']['nav_style']===$v?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
                <label class="premium-inline-toggle"><input type="checkbox" name="sticky" value="1" <?= !empty($appearance['header']['sticky'])?'checked':'' ?>><span>Sticky header</span></label>
                <label class="premium-inline-toggle"><input type="checkbox" name="shadow" value="1" <?= !empty($appearance['header']['shadow'])?'checked':'' ?>><span>Header shadow</span></label>
            </div><footer class="config-dialog__footer"><button class="btn btn-outline-secondary" type="button" data-configure-close>Cancel</button><button class="btn btn-primary" type="submit">Save header</button></footer>
        </form>
    </dialog>

    <dialog class="config-dialog" id="appearance-config-footer" data-config-dialog>
        <form class="config-dialog__panel" action="<?= e(url('/dashboard/appearance/footer')) ?>" method="post" data-appearance-form>
            <?= csrf_field() ?><header class="config-dialog__head"><div><span class="eyebrow">Appearance</span><h2>Footer</h2></div><button type="button" data-configure-close aria-label="Close"><?= app_icon('plus') ?></button></header>
            <div class="config-dialog__body config-field-grid">
                <label><span>Style</span><select class="form-select" name="style"><?php foreach (['standard'=>'Standard','minimal'=>'Minimal','contrast'=>'Contrast'] as $v=>$l): ?><option value="<?= e($v) ?>" <?= $appearance['footer']['style']===$v?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
                <label><span>Vertical spacing (px)</span><input class="form-control" type="number" min="24" max="96" name="spacing" value="<?= e((string)$appearance['footer']['spacing']) ?>"></label>
                <label class="premium-inline-toggle config-field-grid__wide"><input type="checkbox" name="border" value="1" <?= !empty($appearance['footer']['border'])?'checked':'' ?>><span>Show footer divider</span></label>
            </div><footer class="config-dialog__footer"><button class="btn btn-outline-secondary" type="button" data-configure-close>Cancel</button><button class="btn btn-primary" type="submit">Save footer</button></footer>
        </form>
    </dialog>

    <dialog class="config-dialog" id="appearance-config-motion" data-config-dialog>
        <form class="config-dialog__panel" action="<?= e(url('/dashboard/appearance/motion')) ?>" method="post" data-appearance-form>
            <?= csrf_field() ?><header class="config-dialog__head"><div><span class="eyebrow">Appearance</span><h2>Motion & Effects</h2></div><button type="button" data-configure-close aria-label="Close"><?= app_icon('plus') ?></button></header>
            <div class="config-dialog__body config-field-grid">
                <label><span>Transition duration (ms)</span><input class="form-control" type="number" min="0" max="500" step="10" name="duration" value="<?= e((string)$appearance['motion']['duration']) ?>"></label>
                <label class="premium-inline-toggle"><input type="checkbox" name="transitions" value="1" <?= !empty($appearance['motion']['transitions'])?'checked':'' ?>><span>Transitions</span></label>
                <label class="premium-inline-toggle"><input type="checkbox" name="hover_lift" value="1" <?= !empty($appearance['motion']['hover_lift'])?'checked':'' ?>><span>Hover movement</span></label>
                <label class="premium-inline-toggle"><input type="checkbox" name="smooth_scroll" value="1" <?= !empty($appearance['motion']['smooth_scroll'])?'checked':'' ?>><span>Smooth scrolling</span></label>
                <label class="premium-inline-toggle config-field-grid__wide"><input type="checkbox" name="respect_reduced_motion" value="1" <?= !empty($appearance['motion']['respect_reduced_motion'])?'checked':'' ?>><span>Respect operating-system reduced-motion preference</span></label>
            </div><footer class="config-dialog__footer"><button class="btn btn-outline-secondary" type="button" data-configure-close>Cancel</button><button class="btn btn-primary" type="submit">Save motion</button></footer>
        </form>
    </dialog>
</section>
