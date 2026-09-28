<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Repositories\AppearanceRepository;
use DomainException;
use InvalidArgumentException;

final class AppearanceManagementService
{
    private const FEATURES = [
        'typography',
        'font_family',
        'font_size',
        'color',
        'background',
        'font_style',
        'border',
        'shadow',
        'preset',
        'skin',
        'layout',
        'radius_spacing',
        'buttons',
        'cards',
        'forms',
        'header',
        'footer',
        'motion',
    ];

    /** @param array<string,mixed> $input */
    public function updateFeature(string $feature, array $input): void
    {
        $actor = $this->administrator();
        $feature = $this->feature($feature);
        $repository = new AppearanceRepository();
        $config = $repository->get();
        $current = is_array($config[$feature] ?? null)
            ? $config[$feature]
            : AppearanceRepository::defaults()[$feature];

        $config[$feature] = $this->validated($feature, $input, $current);
        $repository->save($config);

        (new SecurityAuditService())->record(
            'admin.appearance_feature_updated',
            (int) $actor['id'],
            null,
            ['feature' => $feature]
        );
    }

    public function setFeatureEnabled(string $feature, bool $enabled): void
    {
        $actor = $this->administrator();
        $feature = $this->feature($feature);
        $repository = new AppearanceRepository();
        $config = $repository->get();
        $config[$feature]['enabled'] = $enabled;
        $repository->save($config);

        (new SecurityAuditService())->record(
            'admin.appearance_feature_toggled',
            (int) $actor['id'],
            null,
            ['feature' => $feature, 'enabled' => $enabled]
        );
    }

    public function reset(): void
    {
        $actor = $this->administrator();
        (new AppearanceRepository())->save(AppearanceRepository::defaults());

        (new SecurityAuditService())->record(
            'admin.appearance_reset',
            (int) $actor['id'],
            null,
            ['scope' => 'frontend']
        );
    }

    /** @param array<string,mixed> $config */
    public function stylesheet(array $config): string
    {
        $config = $this->effective($config);
        $defaults = AppearanceRepository::defaults();

        foreach (self::FEATURES as $feature) {
            if ($feature === 'preset') {
                continue;
            }

            if (empty($config[$feature]['enabled'])) {
                $config[$feature] = $defaults[$feature];
            }
        }

        $typography = $config['typography'];
        $families = $config['font_family'];
        $sizes = $config['font_size'];
        $colors = $config['color'];
        $background = $config['background'];
        $fontStyle = $config['font_style'];
        $border = $config['border'];
        $shadow = $config['shadow'];
        $layout = $config['layout'];
        $radius = $config['radius_spacing'];
        $buttons = $config['buttons'];
        $cards = $config['cards'];
        $forms = $config['forms'];
        $header = $config['header'];
        $footer = $config['footer'];
        $motion = $config['motion'];
        $skin = !empty($config['skin']['enabled'])
            ? $this->enum((string) ($config['skin']['name'] ?? 'modern'), ['modern', 'soft', 'sharp', 'glass', 'luxury'], 'modern')
            : 'modern';

        $light = $this->safePalette((array) ($colors['light'] ?? []), (array) $defaults['color']['light']);
        $dark = $this->safePalette((array) ($colors['dark'] ?? []), (array) $defaults['color']['dark']);

        $fontBody = $this->fontStack((string) ($families['body'] ?? 'inter'));
        $fontHeading = $this->fontStack((string) ($families['heading'] ?? 'inter'));
        $basePx = $this->boundedInt($sizes['base_px'] ?? 16, 14, 20, 16);
        $smallScale = $this->boundedFloat($sizes['small_scale'] ?? 0.875, 0.72, 1.0, 0.875);
        $navScale = $this->boundedFloat($sizes['nav_scale'] ?? 0.9, 0.78, 1.15, 0.9);
        $bodyLine = $this->boundedFloat($typography['body_line_height'] ?? 1.6, 1.2, 2.0, 1.6);
        $headingLine = $this->boundedFloat($typography['heading_line_height'] ?? 1.08, 0.9, 1.6, 1.08);
        $bodyLetter = $this->boundedFloat($typography['body_letter_spacing'] ?? 0, -0.05, 0.1, 0);
        $headingLetter = $this->boundedFloat($typography['heading_letter_spacing'] ?? -0.035, -0.12, 0.08, -0.035);
        $bodyWeight = $this->allowedInt($fontStyle['body_weight'] ?? 400, [400, 500, 600], 400);
        $headingWeight = $this->allowedInt($fontStyle['heading_weight'] ?? 850, [600, 700, 800, 850, 900], 850);
        $navWeight = $this->allowedInt($fontStyle['nav_weight'] ?? 700, [500, 600, 700, 800, 900], 700);
        $headingTransform = $this->enum((string) ($fontStyle['heading_transform'] ?? 'none'), ['none', 'uppercase'], 'none');
        $smoothing = !empty($fontStyle['smoothing']);

        $container = $this->boundedInt($layout['container_width'] ?? 1220, 1040, 1600, 1220);
        $gutter = $this->boundedInt($layout['page_gutter'] ?? 24, 12, 48, 24);
        $sectionScale = $this->boundedFloat($layout['section_spacing'] ?? 1.0, 0.75, 1.5, 1.0);
        $density = $this->enum((string) ($layout['density'] ?? 'comfortable'), ['compact', 'comfortable', 'spacious'], 'comfortable');
        $densityScale = match ($density) {
            'compact' => 0.88,
            'spacious' => 1.14,
            default => 1.0,
        };

        $radiusSm = $this->boundedInt($radius['radius_sm'] ?? 12, 0, 24, 12);
        $radiusMd = $this->boundedInt($radius['radius_md'] ?? 18, 0, 32, 18);
        $radiusLg = $this->boundedInt($radius['radius_lg'] ?? 28, 0, 48, 28);
        $controlRadius = $this->boundedInt($radius['control_radius'] ?? 12, 0, 24, 12);
        $cardRadius = $this->boundedInt($radius['card_radius'] ?? 18, 0, 32, 18);
        $gapScale = $this->boundedFloat($radius['gap_scale'] ?? 1.0, 0.75, 1.5, 1.0);

        $borderWidth = $this->boundedInt($border['width'] ?? 1, 0, 3, 1);
        $borderStyle = $this->enum((string) ($border['style'] ?? 'solid'), ['solid', 'dashed'], 'solid');
        $borderOpacity = $this->boundedInt($border['opacity'] ?? 100, 0, 100, 100);

        $shadowLevel = $this->enum((string) ($shadow['level'] ?? 'soft'), ['none', 'soft', 'medium', 'strong'], 'soft');
        [$lightShadow, $darkShadow] = $this->shadowPair($shadowLevel);

        $buttonStyle = $this->enum((string) ($buttons['style'] ?? 'solid'), ['solid', 'soft', 'outline', 'gradient'], 'solid');
        $buttonRadius = $this->boundedInt($buttons['radius'] ?? 12, 0, 30, 12);
        $buttonWeight = $this->allowedInt($buttons['weight'] ?? 800, [600, 700, 800, 900], 800);
        $buttonTransform = !empty($buttons['uppercase']) ? 'uppercase' : 'none';

        $cardStyle = $this->enum((string) ($cards['style'] ?? 'elevated'), ['elevated', 'outlined', 'flat', 'glass'], 'elevated');
        $cardConfiguredRadius = $this->boundedInt($cards['radius'] ?? $cardRadius, 0, 32, $cardRadius);
        $cardBorder = !empty($cards['border']);
        $cardHover = !empty($cards['hover_lift']) && !empty($shadow['hover']);

        $formStyle = $this->enum((string) ($forms['style'] ?? 'outlined'), ['outlined', 'filled', 'soft'], 'outlined');
        $formHeight = $this->boundedInt($forms['height'] ?? 48, 40, 60, 48);
        $formRadius = $this->boundedInt($forms['radius'] ?? $controlRadius, 0, 24, $controlRadius);
        $focusRing = !empty($forms['focus_ring']);

        $headerSticky = !empty($header['sticky']);
        $headerBlur = $this->boundedInt($header['blur'] ?? 16, 0, 30, 16);
        $headerHeight = $this->boundedInt($header['height'] ?? 76, 60, 96, 76);
        $navStyle = $this->enum((string) ($header['nav_style'] ?? 'soft'), ['soft', 'pill', 'underline', 'minimal'], 'soft');
        $headerShadow = !empty($header['shadow']);

        $footerStyle = $this->enum((string) ($footer['style'] ?? 'standard'), ['standard', 'minimal', 'contrast'], 'standard');
        $footerBorder = !empty($footer['border']);
        $footerSpacing = $this->boundedInt($footer['spacing'] ?? 48, 24, 96, 48);

        $duration = !empty($motion['transitions'])
            ? $this->boundedInt($motion['duration'] ?? 160, 0, 500, 160)
            : 0;
        $hoverLift = !empty($motion['hover_lift']);
        $smoothScroll = !empty($motion['smooth_scroll']);
        $respectReduced = !empty($motion['respect_reduced_motion']);

        $lightBackground = $this->backgroundCss(
            $this->enum((string) ($background['style'] ?? 'solid'), ['solid', 'gradient', 'soft-gradient'], 'solid'),
            $light['background'],
            $this->color((string) ($background['light_to'] ?? '#eef4ff'), '#eef4ff'),
            $this->enum((string) ($background['pattern'] ?? 'none'), ['none', 'dots', 'grid'], 'none'),
            !empty($background['fixed']),
            $light['border']
        );
        $darkBackground = $this->backgroundCss(
            $this->enum((string) ($background['style'] ?? 'solid'), ['solid', 'gradient', 'soft-gradient'], 'solid'),
            $dark['background'],
            $this->color((string) ($background['dark_to'] ?? '#101d31'), '#101d31'),
            $this->enum((string) ($background['pattern'] ?? 'none'), ['none', 'dots', 'grid'], 'none'),
            !empty($background['fixed']),
            $dark['border']
        );

        $css = [];
        $css[] = '/* Barama dynamic frontend appearance */';
        $css[] = '.barama-public-root{font-size:' . $basePx . 'px;' . ($smoothScroll ? 'scroll-behavior:smooth;' : '') . '}';
        $css[] = 'body.barama-public-surface{'
            . '--barama-bg:' . $light['background'] . ';'
            . '--barama-surface:' . $light['surface'] . ';'
            . '--barama-surface-soft:' . $light['surface_soft'] . ';'
            . '--barama-text:' . $light['text'] . ';'
            . '--barama-muted:' . $light['muted'] . ';'
            . '--barama-border:' . $light['border'] . ';'
            . '--barama-primary:' . $light['primary'] . ';'
            . '--barama-primary-strong:' . $light['primary_strong'] . ';'
            . '--barama-success:' . $light['success'] . ';'
            . '--barama-danger:' . $light['danger'] . ';'
            . '--barama-shadow:' . $lightShadow . ';'
            . '--barama-radius-sm:' . $radiusSm . 'px;'
            . '--barama-radius-md:' . $radiusMd . 'px;'
            . '--barama-radius-lg:' . $radiusLg . 'px;'
            . '--barama-container:' . $container . 'px;'
            . '--appearance-font-body:' . $fontBody . ';'
            . '--appearance-font-heading:' . $fontHeading . ';'
            . '--appearance-small-scale:' . $smallScale . ';'
            . '--appearance-nav-scale:' . $navScale . ';'
            . '--appearance-section-scale:' . $sectionScale . ';'
            . '--appearance-density-scale:' . $densityScale . ';'
            . '--appearance-gap-scale:' . $gapScale . ';'
            . '--appearance-link:' . $light['link'] . ';'
            . '--appearance-border-width:' . $borderWidth . 'px;'
            . '--appearance-border-style:' . $borderStyle . ';'
            . '--appearance-border-opacity:' . $borderOpacity . '%;'
            . '--appearance-control-radius:' . $controlRadius . 'px;'
            . '--appearance-card-radius:' . $cardConfiguredRadius . 'px;'
            . '--appearance-motion:' . $duration . 'ms;'
            . 'font-family:var(--appearance-font-body);'
            . 'font-weight:' . $bodyWeight . ';'
            . 'line-height:' . $bodyLine . ';'
            . 'letter-spacing:' . $bodyLetter . 'em;'
            . $lightBackground
            . ($smoothing ? '-webkit-font-smoothing:antialiased;text-rendering:optimizeLegibility;' : '')
            . '}';
        $css[] = 'html[data-bs-theme="dark"] body.barama-public-surface{'
            . '--barama-bg:' . $dark['background'] . ';'
            . '--barama-surface:' . $dark['surface'] . ';'
            . '--barama-surface-soft:' . $dark['surface_soft'] . ';'
            . '--barama-text:' . $dark['text'] . ';'
            . '--barama-muted:' . $dark['muted'] . ';'
            . '--barama-border:' . $dark['border'] . ';'
            . '--barama-primary:' . $dark['primary'] . ';'
            . '--barama-primary-strong:' . $dark['primary_strong'] . ';'
            . '--barama-success:' . $dark['success'] . ';'
            . '--barama-danger:' . $dark['danger'] . ';'
            . '--barama-shadow:' . $darkShadow . ';'
            . '--appearance-link:' . $dark['link'] . ';'
            . $darkBackground
            . '}';

        $css[] = '.barama-public-surface :where(h1,h2,h3,h4,h5,h6,.section-heading-row h2,.single-detail__title-block h1){font-family:var(--appearance-font-heading);font-weight:' . $headingWeight . ';line-height:' . $headingLine . ';letter-spacing:' . $headingLetter . 'em;text-transform:' . $headingTransform . ';}';
        $css[] = '.barama-public-surface :where(.main-nav a,.site-footer a){font-size:calc(1rem * var(--appearance-nav-scale));font-weight:' . $navWeight . ';}';
        $css[] = '.barama-public-surface small{font-size:calc(1em * var(--appearance-small-scale));}';
        $css[] = '.barama-public-surface .app-container{max-width:var(--barama-container);padding-left:' . $gutter . 'px;padding-right:' . $gutter . 'px;}';
        $css[] = '.barama-public-surface :where(.marketplace-section,.hero-section,.listing-detail,.cms-page,.auth-shell){--appearance-section-gap:calc(1rem * var(--appearance-section-scale));}';

        $commonCards = '.inventory-card,.market-pulse,.market-intent,.detail-summary-card,.inquiry-card,.foundation-panel,.detail-company-link,.auth-card,.cms-content';
        $cardBackground = match ($cardStyle) {
            'flat' => 'background:var(--barama-surface);box-shadow:none;',
            'outlined' => 'background:transparent;box-shadow:none;',
            'glass' => 'background:color-mix(in srgb,var(--barama-surface) 78%,transparent);backdrop-filter:blur(18px);',
            default => 'background:var(--barama-surface);box-shadow:var(--barama-shadow);',
        };
        $css[] = '.barama-public-surface :where(' . $commonCards . '){border-radius:' . $cardConfiguredRadius . 'px;'
            . ($cardBorder ? 'border:' . $borderWidth . 'px ' . $borderStyle . ' color-mix(in srgb,var(--barama-border) ' . $borderOpacity . '%,transparent);' : 'border:0;')
            . $cardBackground
            . 'transition:transform var(--appearance-motion) ease,box-shadow var(--appearance-motion) ease,border-color var(--appearance-motion) ease;}';
        if ($cardHover && $hoverLift) {
            $css[] = '.barama-public-surface :where(.inventory-card,.market-intent,.detail-company-link):hover{transform:translateY(-3px);}';
        }

        $buttonBackground = match ($buttonStyle) {
            'soft' => 'background:color-mix(in srgb,var(--barama-primary) 12%,var(--barama-surface));color:var(--barama-primary);border-color:color-mix(in srgb,var(--barama-primary) 24%,var(--barama-border));',
            'outline' => 'background:transparent;color:var(--barama-primary);border-color:var(--barama-primary);',
            'gradient' => 'background:linear-gradient(135deg,var(--barama-primary),var(--barama-primary-strong));color:#fff;border-color:transparent;',
            default => '',
        };
        $css[] = '.barama-public-surface :where(.btn,.market-searchbar button,.barama-user-trigger){border-radius:' . $buttonRadius . 'px;font-weight:' . $buttonWeight . ';text-transform:' . $buttonTransform . ';transition:all var(--appearance-motion) ease;}';
        if ($buttonBackground !== '') {
            $css[] = '.barama-public-surface .btn-primary{' . $buttonBackground . '}';
        }

        $formBackground = match ($formStyle) {
            'filled' => 'background:var(--barama-surface);',
            'soft' => 'background:var(--barama-surface-soft);border-color:transparent;',
            default => 'background:transparent;',
        };
        $css[] = '.barama-public-surface :where(.form-control,.form-select,input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]),select,textarea){min-height:' . $formHeight . 'px;border-radius:' . $formRadius . 'px;' . $formBackground . 'transition:border-color var(--appearance-motion) ease,box-shadow var(--appearance-motion) ease,background-color var(--appearance-motion) ease;}';
        if (!$focusRing) {
            $css[] = '.barama-public-surface :where(input,select,textarea,button,a):focus-visible{box-shadow:none;}';
        }

        $css[] = '.barama-public-surface .site-header{position:' . ($headerSticky ? 'sticky' : 'relative') . ';top:' . ($headerSticky ? '0' : 'auto') . ';min-height:' . $headerHeight . 'px;backdrop-filter:blur(' . $headerBlur . 'px);' . ($headerShadow ? 'box-shadow:0 10px 30px rgba(8,18,35,.08);' : 'box-shadow:none;') . '}';
        $css[] = '.barama-public-surface .site-header__inner{min-height:' . $headerHeight . 'px;}';
        if ($navStyle === 'pill') {
            $css[] = '.barama-public-surface .main-nav a{border-radius:999px;}';
        } elseif ($navStyle === 'underline') {
            $css[] = '.barama-public-surface .main-nav a{border-radius:0;border-bottom:2px solid transparent;background:transparent!important}.barama-public-surface .main-nav a:is(:hover,.is-active){border-bottom-color:var(--barama-primary);color:var(--barama-primary);}';
        } elseif ($navStyle === 'minimal') {
            $css[] = '.barama-public-surface .main-nav a{background:transparent!important;}';
        }

        $footerBackground = $footerStyle === 'contrast'
            ? 'background:var(--barama-text);color:var(--barama-bg);'
            : ($footerStyle === 'minimal' ? 'background:transparent;' : '');
        $css[] = '.barama-public-surface .site-footer{padding-top:' . $footerSpacing . 'px;padding-bottom:' . $footerSpacing . 'px;' . ($footerBorder ? 'border-top:' . $borderWidth . 'px ' . $borderStyle . ' color-mix(in srgb,var(--barama-border) ' . $borderOpacity . '%,transparent);' : 'border-top:0;') . $footerBackground . '}';

        if ($skin === 'soft') {
            $css[] = '.barama-public-surface :where(' . $commonCards . '){background:linear-gradient(145deg,var(--barama-surface),color-mix(in srgb,var(--barama-primary) 3%,var(--barama-surface)));}';
        } elseif ($skin === 'sharp') {
            $css[] = '.barama-public-surface :where(' . $commonCards . ',.btn,.form-control,.form-select){box-shadow:none;border-radius:min(6px,var(--appearance-card-radius));}';
        } elseif ($skin === 'glass') {
            $css[] = '.barama-public-surface :where(' . $commonCards . '){background:color-mix(in srgb,var(--barama-surface) 72%,transparent);backdrop-filter:blur(20px);border-color:color-mix(in srgb,var(--barama-border) 62%,transparent);}';
        } elseif ($skin === 'luxury') {
            $css[] = '.barama-public-surface :where(.eyebrow,.detail-status){letter-spacing:.14em}.barama-public-surface :where(' . $commonCards . '){box-shadow:0 20px 60px rgba(22,18,12,.1);}';
        }

        if ($respectReduced) {
            $css[] = '@media (prefers-reduced-motion:reduce){.barama-public-root{scroll-behavior:auto!important}.barama-public-surface *{animation-duration:.001ms!important;animation-iteration-count:1!important;transition-duration:.001ms!important;scroll-behavior:auto!important;}}';
        }

        return implode("\n", $css) . "\n";
    }

    /** @param array<string,mixed> $config */
    public function skinName(array $config): string
    {
        if (empty($config['skin']['enabled'])) {
            return 'modern';
        }

        return $this->enum(
            (string) ($config['skin']['name'] ?? 'modern'),
            ['modern', 'soft', 'sharp', 'glass', 'luxury'],
            'modern'
        );
    }

    /** @param array<string,mixed> $config @return array<string,mixed> */
    private function effective(array $config): array
    {
        $defaults = AppearanceRepository::defaults();
        $config = array_replace_recursive($defaults, $config);

        if (!empty($config['preset']['enabled'])) {
            $name = $this->enum(
                (string) ($config['preset']['name'] ?? 'barama'),
                ['barama', 'ocean', 'luxury', 'warm', 'minimal', 'mono'],
                'barama'
            );
            $config = array_replace_recursive($config, $this->presetOverrides($name));
        }

        return $config;
    }

    /** @return array<string,mixed> */
    private function presetOverrides(string $name): array
    {
        return match ($name) {
            'ocean' => [
                'color' => [
                    'light' => ['background' => '#f4f8ff', 'primary' => '#0c63e7', 'primary_strong' => '#084eb9', 'link' => '#0c63e7'],
                    'dark' => ['background' => '#081423', 'surface' => '#0f2034', 'surface_soft' => '#152b45', 'primary' => '#63a4ff', 'primary_strong' => '#8bbcff', 'link' => '#8bbcff'],
                ],
                'background' => ['style' => 'soft-gradient', 'light_to' => '#e7f2ff', 'dark_to' => '#102b48'],
                'skin' => ['name' => 'glass'],
            ],
            'luxury' => [
                'font_family' => ['heading' => 'serif'],
                'color' => [
                    'light' => ['background' => '#fbfaf7', 'surface_soft' => '#f3efe7', 'primary' => '#8a672f', 'primary_strong' => '#6f5122', 'link' => '#8a672f'],
                    'dark' => ['background' => '#15130f', 'surface' => '#1e1a14', 'surface_soft' => '#282219', 'primary' => '#d1aa66', 'primary_strong' => '#e0c28b', 'link' => '#d9ba82'],
                ],
                'shadow' => ['level' => 'medium'],
                'skin' => ['name' => 'luxury'],
            ],
            'warm' => [
                'color' => [
                    'light' => ['background' => '#fff8f3', 'surface_soft' => '#fff0e5', 'primary' => '#c65e38', 'primary_strong' => '#9f4529', 'link' => '#b84f2e'],
                    'dark' => ['background' => '#1a100d', 'surface' => '#251814', 'surface_soft' => '#31201a', 'primary' => '#f2946c', 'primary_strong' => '#ffb08e', 'link' => '#f5a080'],
                ],
                'background' => ['style' => 'soft-gradient', 'light_to' => '#ffe9dc', 'dark_to' => '#322019'],
                'skin' => ['name' => 'soft'],
            ],
            'minimal' => [
                'color' => [
                    'light' => ['background' => '#fafafa', 'surface_soft' => '#f4f4f4', 'text' => '#161616', 'muted' => '#6b6b6b', 'border' => '#dedede', 'primary' => '#232323', 'primary_strong' => '#000000', 'link' => '#232323'],
                    'dark' => ['background' => '#111111', 'surface' => '#171717', 'surface_soft' => '#202020', 'text' => '#f3f3f3', 'muted' => '#a5a5a5', 'border' => '#303030', 'primary' => '#f3f3f3', 'primary_strong' => '#ffffff', 'link' => '#ffffff'],
                ],
                'shadow' => ['level' => 'none'],
                'radius_spacing' => ['radius_sm' => 6, 'radius_md' => 8, 'radius_lg' => 12, 'control_radius' => 7, 'card_radius' => 8],
                'skin' => ['name' => 'sharp'],
            ],
            'mono' => [
                'font_family' => ['body' => 'mono', 'heading' => 'mono'],
                'font_style' => ['heading_weight' => 800],
                'skin' => ['name' => 'sharp'],
            ],
            default => [],
        };
    }

    /** @param array<string,mixed> $input @param array<string,mixed> $current @return array<string,mixed> */
    private function validated(string $feature, array $input, array $current): array
    {
        $enabled = !empty($current['enabled']);

        $value = match ($feature) {
            'typography' => [
                'body_line_height' => $this->floatInput($input, 'body_line_height', 1.2, 2.0, 1.6),
                'heading_line_height' => $this->floatInput($input, 'heading_line_height', 0.9, 1.6, 1.08),
                'body_letter_spacing' => $this->floatInput($input, 'body_letter_spacing', -0.05, 0.1, 0),
                'heading_letter_spacing' => $this->floatInput($input, 'heading_letter_spacing', -0.12, 0.08, -0.035),
            ],
            'font_family' => [
                'body' => $this->enumInput($input, 'body', ['inter', 'system', 'geometric', 'humanist', 'serif', 'mono'], 'inter'),
                'heading' => $this->enumInput($input, 'heading', ['inter', 'system', 'geometric', 'humanist', 'serif', 'mono'], 'inter'),
            ],
            'font_size' => [
                'base_px' => $this->intInput($input, 'base_px', 14, 20, 16),
                'small_scale' => $this->floatInput($input, 'small_scale', 0.72, 1.0, 0.875),
                'nav_scale' => $this->floatInput($input, 'nav_scale', 0.78, 1.15, 0.9),
            ],
            'color' => [
                'light' => $this->paletteInput((array) ($input['light'] ?? []), (array) AppearanceRepository::defaults()['color']['light']),
                'dark' => $this->paletteInput((array) ($input['dark'] ?? []), (array) AppearanceRepository::defaults()['color']['dark']),
            ],
            'background' => [
                'style' => $this->enumInput($input, 'style', ['solid', 'gradient', 'soft-gradient'], 'solid'),
                'light_to' => $this->colorInput($input, 'light_to', '#eef4ff'),
                'dark_to' => $this->colorInput($input, 'dark_to', '#101d31'),
                'pattern' => $this->enumInput($input, 'pattern', ['none', 'dots', 'grid'], 'none'),
                'fixed' => $this->boolInput($input, 'fixed'),
            ],
            'font_style' => [
                'body_weight' => $this->enumIntInput($input, 'body_weight', [400, 500, 600], 400),
                'heading_weight' => $this->enumIntInput($input, 'heading_weight', [600, 700, 800, 850, 900], 850),
                'nav_weight' => $this->enumIntInput($input, 'nav_weight', [500, 600, 700, 800, 900], 700),
                'heading_transform' => $this->enumInput($input, 'heading_transform', ['none', 'uppercase'], 'none'),
                'smoothing' => $this->boolInput($input, 'smoothing'),
            ],
            'border' => [
                'width' => $this->intInput($input, 'width', 0, 3, 1),
                'style' => $this->enumInput($input, 'style', ['solid', 'dashed'], 'solid'),
                'opacity' => $this->intInput($input, 'opacity', 0, 100, 100),
            ],
            'shadow' => [
                'level' => $this->enumInput($input, 'level', ['none', 'soft', 'medium', 'strong'], 'soft'),
                'hover' => $this->boolInput($input, 'hover'),
            ],
            'preset' => [
                'name' => $this->enumInput($input, 'name', ['barama', 'ocean', 'luxury', 'warm', 'minimal', 'mono'], 'barama'),
            ],
            'skin' => [
                'name' => $this->enumInput($input, 'name', ['modern', 'soft', 'sharp', 'glass', 'luxury'], 'modern'),
            ],
            'layout' => [
                'container_width' => $this->intInput($input, 'container_width', 1040, 1600, 1220),
                'page_gutter' => $this->intInput($input, 'page_gutter', 12, 48, 24),
                'section_spacing' => $this->floatInput($input, 'section_spacing', 0.75, 1.5, 1.0),
                'density' => $this->enumInput($input, 'density', ['compact', 'comfortable', 'spacious'], 'comfortable'),
            ],
            'radius_spacing' => [
                'radius_sm' => $this->intInput($input, 'radius_sm', 0, 24, 12),
                'radius_md' => $this->intInput($input, 'radius_md', 0, 32, 18),
                'radius_lg' => $this->intInput($input, 'radius_lg', 0, 48, 28),
                'control_radius' => $this->intInput($input, 'control_radius', 0, 24, 12),
                'card_radius' => $this->intInput($input, 'card_radius', 0, 32, 18),
                'gap_scale' => $this->floatInput($input, 'gap_scale', 0.75, 1.5, 1.0),
            ],
            'buttons' => [
                'style' => $this->enumInput($input, 'style', ['solid', 'soft', 'outline', 'gradient'], 'solid'),
                'radius' => $this->intInput($input, 'radius', 0, 30, 12),
                'weight' => $this->enumIntInput($input, 'weight', [600, 700, 800, 900], 800),
                'uppercase' => $this->boolInput($input, 'uppercase'),
            ],
            'cards' => [
                'style' => $this->enumInput($input, 'style', ['elevated', 'outlined', 'flat', 'glass'], 'elevated'),
                'radius' => $this->intInput($input, 'radius', 0, 32, 18),
                'border' => $this->boolInput($input, 'border'),
                'hover_lift' => $this->boolInput($input, 'hover_lift'),
            ],
            'forms' => [
                'style' => $this->enumInput($input, 'style', ['outlined', 'filled', 'soft'], 'outlined'),
                'height' => $this->intInput($input, 'height', 40, 60, 48),
                'radius' => $this->intInput($input, 'radius', 0, 24, 12),
                'focus_ring' => $this->boolInput($input, 'focus_ring'),
            ],
            'header' => [
                'sticky' => $this->boolInput($input, 'sticky'),
                'blur' => $this->intInput($input, 'blur', 0, 30, 16),
                'height' => $this->intInput($input, 'height', 60, 96, 76),
                'nav_style' => $this->enumInput($input, 'nav_style', ['soft', 'pill', 'underline', 'minimal'], 'soft'),
                'shadow' => $this->boolInput($input, 'shadow'),
            ],
            'footer' => [
                'style' => $this->enumInput($input, 'style', ['standard', 'minimal', 'contrast'], 'standard'),
                'border' => $this->boolInput($input, 'border'),
                'spacing' => $this->intInput($input, 'spacing', 24, 96, 48),
            ],
            'motion' => [
                'transitions' => $this->boolInput($input, 'transitions'),
                'duration' => $this->intInput($input, 'duration', 0, 500, 160),
                'hover_lift' => $this->boolInput($input, 'hover_lift'),
                'smooth_scroll' => $this->boolInput($input, 'smooth_scroll'),
                'respect_reduced_motion' => $this->boolInput($input, 'respect_reduced_motion'),
            ],
            default => throw new InvalidArgumentException('Unknown appearance feature.'),
        };

        return ['enabled' => $enabled] + $value;
    }

    private function feature(string $feature): string
    {
        $feature = strtolower(trim($feature));

        if (!in_array($feature, self::FEATURES, true)) {
            throw new InvalidArgumentException('Unknown appearance feature.');
        }

        return $feature;
    }

    /** @return array<string,mixed> */
    private function administrator(): array
    {
        $auth = new AuthService();
        $user = $auth->user();

        if (
            $user === null
            || !$auth->hasRole('administrator')
            || !$auth->can('page.manage')
        ) {
            throw new DomainException('Administrator page management permission is required.');
        }

        return $user;
    }

    /** @param array<string,mixed> $input */
    private function intInput(array $input, string $key, int $min, int $max, int $fallback): int
    {
        return $this->boundedInt($input[$key] ?? $fallback, $min, $max, $fallback);
    }

    /** @param array<string,mixed> $input */
    private function floatInput(array $input, string $key, float $min, float $max, float $fallback): float
    {
        return $this->boundedFloat($input[$key] ?? $fallback, $min, $max, $fallback);
    }

    /** @param array<string,mixed> $input @param array<int,string> $allowed */
    private function enumInput(array $input, string $key, array $allowed, string $fallback): string
    {
        return $this->enum((string) ($input[$key] ?? $fallback), $allowed, $fallback);
    }

    /** @param array<string,mixed> $input @param array<int,int> $allowed */
    private function enumIntInput(array $input, string $key, array $allowed, int $fallback): int
    {
        return $this->allowedInt($input[$key] ?? $fallback, $allowed, $fallback);
    }

    /** @param array<string,mixed> $input */
    private function boolInput(array $input, string $key): bool
    {
        return in_array(
            strtolower(trim((string) ($input[$key] ?? '0'))),
            ['1', 'true', 'on', 'yes'],
            true
        );
    }

    /** @param array<string,mixed> $input */
    private function colorInput(array $input, string $key, string $fallback): string
    {
        return $this->color((string) ($input[$key] ?? $fallback), $fallback);
    }

    /** @param array<string,mixed> $input @param array<string,mixed> $defaults @return array<string,string> */
    private function paletteInput(array $input, array $defaults): array
    {
        $result = [];

        foreach (['background','surface','surface_soft','text','muted','border','primary','primary_strong','success','danger','link'] as $key) {
            $fallback = (string) ($defaults[$key] ?? '#000000');
            $result[$key] = $this->color((string) ($input[$key] ?? $fallback), $fallback);
        }

        return $result;
    }

    /** @param array<string,mixed> $input @param array<string,mixed> $defaults @return array<string,string> */
    private function safePalette(array $input, array $defaults): array
    {
        return $this->paletteInput($input, $defaults);
    }

    private function color(string $value, string $fallback): string
    {
        $value = strtolower(trim($value));

        return preg_match('/^#[0-9a-f]{6}$/', $value) === 1
            ? $value
            : strtolower($fallback);
    }

    /** @param array<int,string> $allowed */
    private function enum(string $value, array $allowed, string $fallback): string
    {
        $value = strtolower(trim($value));

        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    /** @param array<int,int> $allowed */
    private function allowedInt(mixed $value, array $allowed, int $fallback): int
    {
        $value = (int) $value;

        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    private function boundedInt(mixed $value, int $min, int $max, int $fallback): int
    {
        $value = filter_var($value, FILTER_VALIDATE_INT);

        if ($value === false || $value < $min || $value > $max) {
            return $fallback;
        }

        return (int) $value;
    }

    private function boundedFloat(mixed $value, float $min, float $max, float $fallback): float
    {
        if (!is_numeric($value)) {
            return $fallback;
        }

        $value = (float) $value;

        return $value >= $min && $value <= $max ? $value : $fallback;
    }

    private function fontStack(string $key): string
    {
        return match ($key) {
            'system' => 'system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif',
            'geometric' => '"Avenir Next",Avenir,"Century Gothic",Futura,sans-serif',
            'humanist' => 'Optima,Candara,"Segoe UI",sans-serif',
            'serif' => 'Georgia,"Times New Roman",serif',
            'mono' => '"SFMono-Regular",Consolas,"Liberation Mono",monospace',
            default => 'Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif',
        };
    }

    /** @return array{0:string,1:string} */
    private function shadowPair(string $level): array
    {
        return match ($level) {
            'none' => ['none', 'none'],
            'medium' => ['0 20px 58px rgba(25,43,74,.13)', '0 22px 62px rgba(0,0,0,.34)'],
            'strong' => ['0 24px 72px rgba(25,43,74,.2)', '0 28px 78px rgba(0,0,0,.48)'],
            default => ['0 18px 50px rgba(25,43,74,.08)', '0 20px 55px rgba(0,0,0,.24)'],
        };
    }

    private function backgroundCss(
        string $style,
        string $from,
        string $to,
        string $pattern,
        bool $fixed,
        string $patternColor
    ): string {
        $attachment = $fixed ? 'background-attachment:fixed;' : '';
        $patternLayer = match ($pattern) {
            'dots' => 'radial-gradient(circle,color-mix(in srgb,' . $patternColor . ' 34%,transparent) 1px,transparent 1px),',
            'grid' => 'linear-gradient(color-mix(in srgb,' . $patternColor . ' 28%,transparent) 1px,transparent 1px),linear-gradient(90deg,color-mix(in srgb,' . $patternColor . ' 28%,transparent) 1px,transparent 1px),',
            default => '',
        };
        $patternSize = match ($pattern) {
            'dots' => 'background-size:24px 24px,auto;',
            'grid' => 'background-size:24px 24px,24px 24px,auto;',
            default => '',
        };
        $base = match ($style) {
            'gradient' => 'linear-gradient(135deg,' . $from . ',' . $to . ')',
            'soft-gradient' => 'radial-gradient(circle at 82% 8%,color-mix(in srgb,var(--barama-primary) 10%,transparent),transparent 28%),linear-gradient(145deg,' . $from . ',' . $to . ')',
            default => 'linear-gradient(' . $from . ',' . $from . ')',
        };

        return 'background-color:' . $from . ';background-image:' . $patternLayer . $base . ';' . $patternSize . $attachment;
    }
}
