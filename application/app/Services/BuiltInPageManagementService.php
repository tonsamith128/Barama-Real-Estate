<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Repositories\SiteContentRepository;
use DomainException;
use InvalidArgumentException;

final class BuiltInPageManagementService
{
    private const PAGE_SECTIONS = [
        'about' => ['story', 'mission_vision', 'values', 'stats', 'team', 'cta'],
        'contact' => ['channels', 'office', 'hours', 'form', 'faq', 'socials'],
    ];

    /** @param array<string,mixed> $input */
    public function updateSection(string $page, string $section, array $input): void
    {
        $actor = $this->administrator();
        $page = $this->page($page);
        $section = strtolower(trim($section));

        $repository = new SiteContentRepository();
        $config = $repository->get();
        $defaults = SiteContentRepository::defaults()[$page];

        if (!is_array($config[$page] ?? null)) {
            $config[$page] = $defaults;
        }

        if ($section === 'settings') {
            $config[$page]['enabled'] = $this->boolean($input['enabled'] ?? '0');
        } elseif ($section === 'seo') {
            $config[$page]['seo'] = $this->validatedSeo(
                $input,
                is_array($config[$page]['seo'] ?? null) ? $config[$page]['seo'] : []
            );
        } elseif ($section === 'hero') {
            $config[$page]['hero'] = $this->validatedHero(
                $page,
                $input,
                is_array($config[$page]['hero'] ?? null) ? $config[$page]['hero'] : []
            );
        } else {
            $this->assertSection($page, $section);
            $current = is_array($config[$page]['sections'][$section] ?? null)
                ? $config[$page]['sections'][$section]
                : [];
            $config[$page]['sections'][$section] = $page === 'about'
                ? $this->validatedAboutSection($section, $input, $current)
                : $this->validatedContactSection($section, $input, $current);
            $config[$page]['section_order'] = $this->reorder(
                is_array($config[$page]['section_order'] ?? null)
                    ? array_values($config[$page]['section_order'])
                    : self::PAGE_SECTIONS[$page],
                $section,
                $input['position'] ?? null
            );
        }

        $repository->save($config);

        $this->audit(
            'admin.builtin_page_updated',
            (int) $actor['id'],
            ['page' => $page, 'section' => $section]
        );
    }

    public function toggleSection(string $page, string $section, bool $enabled): void
    {
        $actor = $this->administrator();
        $page = $this->page($page);
        $section = strtolower(trim($section));

        if ($section !== 'hero') {
            $this->assertSection($page, $section);
        }

        $repository = new SiteContentRepository();
        $config = $repository->get();

        if ($section === 'hero') {
            $config[$page]['hero']['enabled'] = $enabled;
        } else {
            $config[$page]['sections'][$section]['enabled'] = $enabled;
        }

        $repository->save($config);

        $this->audit(
            'admin.builtin_page_section_toggled',
            (int) $actor['id'],
            ['page' => $page, 'section' => $section, 'enabled' => $enabled]
        );
    }

    /** @param array<string,mixed> $input @param array<string,mixed> $current @return array<string,mixed> */
    private function validatedSeo(array $input, array $current): array
    {
        $title = $this->text($input['title'] ?? $current['title'] ?? '', 191, 'SEO title');
        $description = $this->text(
            $input['description'] ?? $current['description'] ?? '',
            300,
            'SEO description'
        );
        $robots = strtolower(trim((string) ($input['robots'] ?? $current['robots'] ?? 'index,follow')));

        if (!in_array($robots, ['index,follow', 'noindex,follow', 'noindex,nofollow'], true)) {
            throw new InvalidArgumentException('Choose a supported robots directive.');
        }

        return [
            'title' => $title,
            'description' => $description,
            'robots' => $robots,
        ];
    }

    /** @param array<string,mixed> $input @param array<string,mixed> $current @return array<string,mixed> */
    private function validatedHero(string $page, array $input, array $current): array
    {
        $label = $page === 'about' ? 'About' : 'Contact';
        $title = $this->text($input['title'] ?? $current['title'] ?? '', 180, $label . ' hero title');
        $description = $this->text(
            $input['description'] ?? $current['description'] ?? '',
            700,
            $label . ' hero description'
        );

        if ($title === '' || $description === '') {
            throw new InvalidArgumentException($label . ' hero title and description are required.');
        }

        return [
            'enabled' => !empty($current['enabled']),
            'eyebrow' => $this->text($input['eyebrow'] ?? $current['eyebrow'] ?? '', 80, $label . ' hero eyebrow'),
            'title' => $title,
            'description' => $description,
            'image_url' => $page === 'contact'
                ? $this->contactHeroImageUrl($input['image_url'] ?? $current['image_url'] ?? '')
                : $this->optionalExternalUrl($input['image_url'] ?? $current['image_url'] ?? '', $label . ' hero image URL'),
        ];
    }

    /** @param array<string,mixed> $input @param array<string,mixed> $current @return array<string,mixed> */
    private function validatedAboutSection(string $section, array $input, array $current): array
    {
        $base = [
            'enabled' => !empty($current['enabled']),
            'eyebrow' => $this->text($input['eyebrow'] ?? $current['eyebrow'] ?? '', 80, 'Section eyebrow'),
            'title' => $this->text($input['title'] ?? $current['title'] ?? '', 160, 'Section title'),
        ];

        if ($section === 'story') {
            return $base + [
                'body' => $this->text($input['body'] ?? $current['body'] ?? '', 6000, 'About story'),
                'image_url' => $this->optionalExternalUrl($input['image_url'] ?? $current['image_url'] ?? '', 'About story image URL'),
            ];
        }

        if ($section === 'mission_vision') {
            return $base + [
                'mission_title' => $this->text($input['mission_title'] ?? $current['mission_title'] ?? '', 100, 'Mission title'),
                'mission_text' => $this->text($input['mission_text'] ?? $current['mission_text'] ?? '', 1200, 'Mission text'),
                'vision_title' => $this->text($input['vision_title'] ?? $current['vision_title'] ?? '', 100, 'Vision title'),
                'vision_text' => $this->text($input['vision_text'] ?? $current['vision_text'] ?? '', 1200, 'Vision text'),
            ];
        }

        if ($section === 'values') {
            return $base + [
                'items' => $this->textCards(array_key_exists('items_present', $input) ? ($input['items'] ?? []) : null, $current['items'] ?? null, 12, 'Value', 110, 360),
            ];
        }

        if ($section === 'stats' || $section === 'team') {
            $rows = array_key_exists('items_present', $input)
                ? ($input['items'] ?? [])
                : ($input['items'] ?? $current['items'] ?? []);
            if (!is_array($rows)) {
                throw new InvalidArgumentException('Invalid About items.');
            }
            $limit = $section === 'stats' ? 8 : 8;
            if (count($rows) > $limit) {
                throw new InvalidArgumentException('Too many About items.');
            }
            $items = [];
            foreach (array_values($rows) as $row) {
                if (!is_array($row)) {
                    throw new InvalidArgumentException('Invalid About item.');
                }
                if ($section === 'stats') {
                    $item = [
                        'value' => $this->text($row['value'] ?? '', 30, 'Stat value'),
                        'label' => $this->text($row['label'] ?? '', 100, 'Stat label'),
                    ];
                } else {
                    $item = [
                        'name' => $this->text($row['name'] ?? '', 120, 'Team member name'),
                        'role' => $this->text($row['role'] ?? '', 120, 'Team member role'),
                        'bio' => $this->text($row['bio'] ?? '', 500, 'Team member bio'),
                        'image_url' => $this->optionalExternalUrl($row['image_url'] ?? '', 'Team member image URL'),
                    ];
                }
                if (implode('', $item) !== '') {
                    $items[] = $item;
                }
            }
            // A26: no padding with synthetic blank profiles. The repository
            // now preserves saved About lists exactly, including empty lists.
            return $base + ['items' => $items];
        }

        if ($section === 'cta') {
            return $base + [
                'description' => $this->text($input['description'] ?? $current['description'] ?? '', 600, 'CTA description'),
                'primary_label' => $this->text($input['primary_label'] ?? $current['primary_label'] ?? '', 70, 'Primary CTA label'),
                'primary_url' => $this->optionalLink($input['primary_url'] ?? $current['primary_url'] ?? ''),
                'secondary_label' => $this->text($input['secondary_label'] ?? $current['secondary_label'] ?? '', 70, 'Secondary CTA label'),
                'secondary_url' => $this->optionalLink($input['secondary_url'] ?? $current['secondary_url'] ?? ''),
            ];
        }

        throw new InvalidArgumentException('Unknown About section.');
    }

    /** @param array<string,mixed> $input @param array<string,mixed> $current @return array<string,mixed> */
    private function validatedContactSection(string $section, array $input, array $current): array
    {
        $base = [
            'enabled' => !empty($current['enabled']),
            'eyebrow' => $this->text($input['eyebrow'] ?? $current['eyebrow'] ?? '', 80, 'Section eyebrow'),
            'title' => $this->text($input['title'] ?? $current['title'] ?? '', 160, 'Section title'),
        ];

        if ($section === 'channels') {
            $email = strtolower($this->text($input['email'] ?? $current['email'] ?? '', 191, 'Contact email'));

            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new InvalidArgumentException('Contact email address is invalid.');
            }

            return $base + [
                'description' => $this->text($input['description'] ?? $current['description'] ?? '', 500, 'Contact channels description'),
                'phone' => $this->text($input['phone'] ?? $current['phone'] ?? '', 60, 'Contact phone'),
                'email' => $email,
                'telegram_url' => $this->optionalExternalUrl($input['telegram_url'] ?? $current['telegram_url'] ?? '', 'Telegram URL'),
                'whatsapp_url' => $this->optionalExternalUrl($input['whatsapp_url'] ?? $current['whatsapp_url'] ?? '', 'WhatsApp URL'),
            ];
        }

        if ($section === 'office') {
            return $base + [
                'address' => $this->text($input['address'] ?? $current['address'] ?? '', 600, 'Office address'),
                'location_note' => $this->text($input['location_note'] ?? $current['location_note'] ?? '', 400, 'Location note'),
                'map_url' => $this->optionalExternalUrl($input['map_url'] ?? $current['map_url'] ?? '', 'Map URL'),
                'map_label' => $this->text($input['map_label'] ?? $current['map_label'] ?? 'Open map', 70, 'Map button label'),
            ];
        }

        if ($section === 'hours') {
            $rows = is_array($input['items'] ?? null)
                ? $input['items']
                : (is_array($current['items'] ?? null) ? $current['items'] : []);
            $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            $items = [];

            foreach ($days as $index => $day) {
                $row = is_array($rows[$index] ?? null) ? $rows[$index] : [];
                $items[] = [
                    'day' => $day,
                    'hours' => $this->text($row['hours'] ?? '', 80, $day . ' hours'),
                ];
            }

            return $base + [
                'timezone' => $this->text($input['timezone'] ?? $current['timezone'] ?? 'Asia/Phnom_Penh', 80, 'Business-hours timezone'),
                'items' => $items,
            ];
        }

        if ($section === 'form') {
            $destinationEmail = strtolower($this->text(
                $input['destination_email'] ?? $current['destination_email'] ?? '',
                191,
                'Destination email'
            ));

            if ($destinationEmail !== '' && filter_var($destinationEmail, FILTER_VALIDATE_EMAIL) === false) {
                throw new InvalidArgumentException('Destination email address is invalid.');
            }

            $subjects = array_key_exists('subjects', $input)
                ? preg_split('/\R+/', trim((string) $input['subjects']))
                : (is_array($current['subjects'] ?? null) ? $current['subjects'] : []);
            $cleanSubjects = [];

            foreach (array_slice(is_array($subjects) ? $subjects : [], 0, 8) as $subject) {
                $subject = $this->text($subject, 100, 'Contact subject');
                if ($subject !== '') {
                    $cleanSubjects[] = $subject;
                }
            }

            return $base + [
                'description' => $this->text($input['description'] ?? $current['description'] ?? '', 600, 'Contact form description'),
                'button_label' => $this->text($input['button_label'] ?? $current['button_label'] ?? 'Send message', 70, 'Contact form button label'),
                'success_message' => $this->text($input['success_message'] ?? $current['success_message'] ?? '', 260, 'Contact form success message'),
                'destination_email' => $destinationEmail,
                'require_phone' => $this->boolean($input['require_phone'] ?? '0'),
                'consent_text' => $this->text($input['consent_text'] ?? $current['consent_text'] ?? '', 500, 'Contact form consent text'),
                'subjects' => $cleanSubjects,
            ];
        }

        if ($section === 'faq') {
            $rows = is_array($input['items'] ?? null)
                ? $input['items']
                : (is_array($current['items'] ?? null) ? $current['items'] : []);
            $items = [];

            for ($index = 0; $index < 5; $index++) {
                $row = is_array($rows[$index] ?? null) ? $rows[$index] : [];
                $items[] = [
                    'question' => $this->text($row['question'] ?? '', 180, 'FAQ question'),
                    'answer' => $this->text($row['answer'] ?? '', 900, 'FAQ answer'),
                ];
            }

            return $base + ['items' => $items];
        }

        if ($section === 'socials') {
            return $base + [
                'description' => $this->text($input['description'] ?? $current['description'] ?? '', 400, 'Social links description'),
                'facebook_url' => $this->optionalExternalUrl($input['facebook_url'] ?? $current['facebook_url'] ?? '', 'Facebook URL'),
                'instagram_url' => $this->optionalExternalUrl($input['instagram_url'] ?? $current['instagram_url'] ?? '', 'Instagram URL'),
                'linkedin_url' => $this->optionalExternalUrl($input['linkedin_url'] ?? $current['linkedin_url'] ?? '', 'LinkedIn URL'),
                'youtube_url' => $this->optionalExternalUrl($input['youtube_url'] ?? $current['youtube_url'] ?? '', 'YouTube URL'),
                'telegram_url' => $this->optionalExternalUrl($input['telegram_url'] ?? $current['telegram_url'] ?? '', 'Telegram URL'),
                'tiktok_url' => $this->optionalExternalUrl($input['tiktok_url'] ?? $current['tiktok_url'] ?? '', 'TikTok URL'),
            ];
        }

        throw new InvalidArgumentException('Unknown Contact section.');
    }

    /** @return array<int,array{title:string,description:string}> */
    private function textCards(mixed $input, mixed $current, int $count, string $label, int $titleMax, int $descriptionMax): array
    {
        $rows = is_array($input) ? $input : (is_array($current) ? $current : []);
        if (count($rows) > $count) {
            throw new InvalidArgumentException('Too many ' . $label . ' items.');
        }
        $items = [];
        foreach (array_values($rows) as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException('Invalid ' . $label . ' item.');
            }
            $item = [
                'title' => $this->text($row['title'] ?? '', $titleMax, $label . ' title'),
                'description' => $this->text($row['description'] ?? '', $descriptionMax, $label . ' description'),
            ];
            if ($item['title'] !== '' || $item['description'] !== '') {
                $items[] = $item;
            }
        }
        // A26: keep intentional deletion/reordering; the repository preserves
        // empty and shortened About lists instead of restoring default entries.
        return $items;
    }

    /** @param array<int,mixed> $order @return array<int,string> */
    private function reorder(array $order, string $section, mixed $position): array
    {
        $order = array_values(array_filter(
            array_map(static fn (mixed $item): string => (string) $item, $order),
            static fn (string $item): bool => $item !== $section
        ));

        $max = count($order) + 1;
        $requested = filter_var(
            $position,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => $max]]
        );
        $requested = $requested === false ? $max : (int) $requested;

        array_splice($order, $requested - 1, 0, [$section]);

        return array_values($order);
    }

    private function page(string $page): string
    {
        $page = strtolower(trim($page));

        if (!array_key_exists($page, self::PAGE_SECTIONS)) {
            throw new InvalidArgumentException('Unknown built-in page.');
        }

        return $page;
    }

    private function assertSection(string $page, string $section): void
    {
        if (!in_array($section, self::PAGE_SECTIONS[$page], true)) {
            throw new InvalidArgumentException('Unknown built-in page section.');
        }
    }

    private function boolean(mixed $value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'on', 'yes'], true);
    }

    private function text(mixed $value, int $max, string $label): string
    {
        $value = trim((string) $value);

        if (mb_strlen($value) > $max) {
            throw new InvalidArgumentException($label . ' cannot exceed ' . $max . ' characters.');
        }

        return $value;
    }

    private function optionalLink(mixed $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        if (str_starts_with($value, '/')) {
            if (
                str_starts_with($value, '//')
                || preg_match('/[\x00-\x1F\x7F]/', $value) === 1
                || mb_strlen($value) > 1000
            ) {
                throw new InvalidArgumentException('Internal link is invalid.');
            }

            return $value;
        }

        return $this->optionalExternalUrl($value, 'Link URL');
    }

    /** Accept only generated local Contact Hero media paths or ordinary HTTP(S) URLs. */
    private function contactHeroImageUrl(mixed $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return ''; // Use bundled default photograph.
        }
        if (preg_match('~^/assets/images/contact/custom/[a-f0-9]{32}\.(?:jpg|png|webp)$~D', $value) === 1) {
            return $value;
        }
        return $this->optionalExternalUrl($value, 'Contact hero image URL');
    }

    private function optionalExternalUrl(mixed $value, string $label): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        if (mb_strlen($value) > 1000) {
            throw new InvalidArgumentException($label . ' cannot exceed 1,000 characters.');
        }

        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException($label . ' is invalid.');
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));

        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException($label . ' must use HTTP or HTTPS.');
        }

        return $value;
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

    /** @param array<string,mixed> $metadata */
    private function audit(string $event, int $actorId, array $metadata): void
    {
        (new SecurityAuditService())->record($event, $actorId, null, $metadata);
    }
}
