<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Repositories\EmailTemplateLocalizationRepository;
use Barama\Repositories\EmailTemplateRepository;
use DomainException;
use InvalidArgumentException;

final class EmailTemplateManagementService
{
    private const LOCALES = ['en', 'km', 'zh'];
    private const CUSTOM_VARIABLES = [
        'recipient_name', 'title', 'message', 'action_url', 'app_name', 'app_url',
    ];

    /** @return array<string,string> */
    public static function supportedKeys(): array { return EmailTemplateCatalog::labels(); }

    public static function isCustomKey(string $key): bool
    {
        return preg_match('/^custom_[a-z0-9][a-z0-9_]{2,92}$/', strtolower(trim($key))) === 1;
    }

    /** @param array<string,mixed>|null $template @return array<string,mixed>|null */
    public static function definitionForKey(string $key, ?array $template = null): ?array
    {
        $key = strtolower(trim($key));
        $definition = EmailTemplateCatalog::get($key);
        if ($definition !== null) { return $definition; }
        if (!self::isCustomKey($key) || $template === null) { return null; }

        return [
            'label' => (string) ($template['name'] ?? 'Custom email template'),
            'group' => 'Custom',
            'description' => (string) ($template['description'] ?? 'Standalone custom email template created in the Barama Builder.'),
            'security_sensitive' => false,
        ];
    }

    /** @return array<int,string> */
    public static function variablesForKey(string $key): array
    {
        $key = strtolower(trim($key));
        return self::isCustomKey($key) ? self::CUSTOM_VARIABLES : EmailTemplateCatalog::variables($key);
    }

    /** @return array<string,string> */
    public static function sampleVariablesForKey(string $key): array
    {
        $key = strtolower(trim($key));
        if (!self::isCustomKey($key)) { return EmailTemplateCatalog::sampleVariables($key); }
        return [
            'recipient_name' => 'Alex Morgan',
            'title' => 'Your Barama update',
            'message' => 'This is sample content from your custom Barama email template.',
            'action_url' => rtrim((string) config('app.url', ''), '/') . '/dashboard',
            'app_name' => (string) config('app.name', 'Barama Real Estate'),
            'app_url' => rtrim((string) config('app.url', ''), '/'),
        ];
    }

    /** @return array<string,string> */
    public static function customBuilderDefaults(): array
    {
        return [
            'name' => '',
            'description' => 'Reusable custom email template created with the Barama Email Template Builder.',
            'subject' => '{{app_name}} — your update',
            'html' => '<p>Hello {{recipient_name}},</p><h2>{{title}}</h2><p>{{message}}</p><p><a href="{{action_url}}">Open in {{app_name}}</a></p>',
            'text' => "Hello {{recipient_name}},\n\n{{title}}\n\n{{message}}\n\nOpen: {{action_url}}",
        ];
    }

    /** @return array<string,string> */
    public static function supportedLocales(): array
    {
        return ['en' => 'English', 'km' => 'ខ្មែរ', 'zh' => '中文'];
    }

    /** @param array<string,mixed> $input */
    public function create(array $input): int
    {
        $actor = $this->administrator();
        $repository = new EmailTemplateRepository();
        $data = $this->validated($input);
        if ($repository->keyExists($data['template_key'])) {
            throw new InvalidArgumentException('An email template already exists for this notification type.');
        }
        $data['actor_user_id'] = (int) $actor['id'];
        $id = $repository->create($data);
        $created = $repository->find($id);
        if ($created !== null) {
            (new EmailTemplateLocalizationRepository())->snapshotBase($created, (int) $actor['id'], 'created');
        }
        $this->audit('admin.communication_email_template_created', (int) $actor['id'], [
            'email_template_id' => $id, 'template_key' => $data['template_key'], 'locale' => 'en',
        ]);
        return $id;
    }

    /** @param array<string,mixed> $input */
    public function update(int $id, array $input): void
    {
        $actor = $this->administrator();
        $repository = new EmailTemplateRepository();
        $template = $this->template($repository, $id);
        if ((string) $template['status'] !== 'inactive') {
            throw new DomainException('Deactivate the email template before editing its content.');
        }
        $data = $this->validated($input);
        if ($repository->keyExists($data['template_key'], $id)) {
            throw new InvalidArgumentException('Another email template already uses this notification type.');
        }
        $history = new EmailTemplateLocalizationRepository();
        $history->snapshotBase($template, (int) $actor['id'], 'before_update');
        $data['actor_user_id'] = (int) $actor['id'];
        $repository->update($id, $data);
        $this->audit('admin.communication_email_template_updated', (int) $actor['id'], [
            'email_template_id' => $id, 'template_key' => $data['template_key'], 'locale' => 'en',
        ]);
    }

    /** @param array<string,mixed> $input */
    public function saveVariant(string $key, string $locale, array $input): int
    {
        $actor = $this->administrator();
        $locale = $this->variantLocale($locale);
        $base = (new EmailTemplateRepository())->findByKey($key);
        if ($base === null) {
            throw new DomainException('Create the base English override before adding language variants.');
        }
        $input['template_key'] = strtolower(trim($key));
        $data = $this->validated($input);
        $repository = new EmailTemplateLocalizationRepository();
        $variant = $repository->variantForKey($key, $locale);
        if ($variant === null) {
            $id = $repository->createVariant((int) $base['id'], $locale, $data, (int) $actor['id']);
            $created = $repository->variantForKey($key, $locale);
            if ($created !== null) $repository->snapshotVariant($created, (int) $actor['id'], 'created');
        } else {
            if ((string) $variant['status'] !== 'inactive') {
                throw new DomainException('Deactivate this language variant before editing its content.');
            }
            $repository->snapshotVariant($variant, (int) $actor['id'], 'before_update');
            $repository->updateVariant((int) $variant['id'], $data, (int) $actor['id']);
            $id = (int) $variant['id'];
        }
        $this->audit('admin.communication_email_template_variant_saved', (int) $actor['id'], [
            'email_template_id' => (int) $base['id'], 'template_key' => (string) $base['template_key'], 'locale' => $locale,
        ]);
        return $id;
    }

    public function setStatus(int $id, string $status): void
    {
        $actor = $this->administrator();
        $repository = new EmailTemplateRepository();
        $template = $this->template($repository, $id);
        $current = (string) $template['status'];
        $status = $this->transition($current, $status);
        (new EmailTemplateLocalizationRepository())->snapshotBase($template, (int) $actor['id'], 'before_status_' . $status);
        $repository->setStatus($id, $status, (int) $actor['id']);
        $this->audit('admin.communication_email_template_status_changed', (int) $actor['id'], [
            'email_template_id' => $id, 'template_key' => (string) $template['template_key'],
            'locale' => 'en', 'from_status' => $current, 'to_status' => $status,
        ]);
    }

    public function setVariantStatus(string $key, string $locale, string $status): void
    {
        $actor = $this->administrator();
        $locale = $this->variantLocale($locale);
        $repository = new EmailTemplateLocalizationRepository();
        $variant = $repository->variantForKey($key, $locale);
        if ($variant === null) throw new InvalidArgumentException('Email language variant was not found.');
        $current = (string) $variant['status'];
        $status = $this->transition($current, $status);
        if ($status === 'active' && (string) ($variant['base_status'] ?? '') !== 'active') {
            throw new DomainException('Activate the English/base template before activating this language variant.');
        }
        $repository->snapshotVariant($variant, (int) $actor['id'], 'before_status_' . $status);
        $repository->setVariantStatus((int) $variant['id'], $status, (int) $actor['id']);
        $this->audit('admin.communication_email_template_variant_status_changed', (int) $actor['id'], [
            'email_template_id' => (int) $variant['template_id'], 'template_key' => strtolower(trim($key)),
            'locale' => $locale, 'from_status' => $current, 'to_status' => $status,
        ]);
    }

    public function restoreRevision(string $key, int $revisionId): void
    {
        $actor = $this->administrator();
        $baseRepository = new EmailTemplateRepository();
        $base = $baseRepository->findByKey($key);
        if ($base === null) throw new InvalidArgumentException('Email template override was not found.');
        $repository = new EmailTemplateLocalizationRepository();
        $revision = $repository->revisionById($revisionId);
        if ($revision === null || (string) $revision['template_key'] !== strtolower(trim($key))) {
            throw new InvalidArgumentException('Email template revision was not found.');
        }
        $locale = (string) $revision['locale'];
        $data = [
            'template_key' => (string) $base['template_key'],
            'name' => (string) ($revision['name'] ?? $base['name']),
            'description' => $revision['description'] ?? $base['description'],
            'subject_template' => (string) $revision['subject_template'],
            'html_template' => (string) $revision['html_template'],
            'text_template' => (string) $revision['text_template'],
        ];
        $data = $this->validated($data);
        if ($locale === 'en') {
            if ((string) $base['status'] !== 'inactive') throw new DomainException('Deactivate the base template before restoring a revision.');
            $repository->snapshotBase($base, (int) $actor['id'], 'before_restore');
            $data['actor_user_id'] = (int) $actor['id'];
            $baseRepository->update((int) $base['id'], $data);
        } else {
            $variant = $repository->variantForKey($key, $this->variantLocale($locale));
            if ($variant === null || (string) $variant['status'] !== 'inactive') {
                throw new DomainException('Create and deactivate this language variant before restoring a revision.');
            }
            $repository->snapshotVariant($variant, (int) $actor['id'], 'before_restore');
            $repository->updateVariant((int) $variant['id'], $data, (int) $actor['id']);
        }
        $this->audit('admin.communication_email_template_revision_restored', (int) $actor['id'], [
            'email_template_id' => (int) $base['id'], 'template_key' => (string) $base['template_key'],
            'locale' => $locale, 'revision_id' => $revisionId,
        ]);
    }

    /** @param array<string,mixed> $input */
    public function sendTest(array $input, string $recipient): void
    {
        $actor = $this->administrator();
        $recipient = strtolower(trim($recipient));
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Enter a valid recipient email address for the test message.');
        }
        $data = $this->validated($input);
        $locale = strtolower(trim((string) ($input['locale'] ?? 'en')));
        if (!in_array($locale, self::LOCALES, true)) $locale = 'en';
        $samples = self::sampleVariablesForKey($data['template_key']);
        $appName = trim((string) config('app.name', 'Barama Real Estate'));
        $appUrl = rtrim(trim((string) config('app.url', '')), '/');
        if ($appName !== '') $samples['app_name'] = $appName;
        if ($appUrl !== '') $samples['app_url'] = $appUrl;
        $render = static function (string $template) use ($samples): string {
            return (string) preg_replace_callback('/\\{\\{\\s*([a-z0-9_]+)\\s*\\}\\}/i', static function (array $matches) use ($samples): string {
                $key = strtolower((string) ($matches[1] ?? ''));
                return array_key_exists($key, $samples) ? (string) $samples[$key] : '';
            }, $template);
        };
        $subject = '[TEST][' . strtoupper($locale) . '] ' . $render($data['subject_template']);
        $mailer = new MailService();
        if (!$mailer->send($recipient, $subject, $render($data['html_template']), $render($data['text_template']))) {
            throw new DomainException('Test email could not be sent: ' . ($mailer->lastError() ?? 'mail delivery failed'));
        }
        $this->audit('admin.communication_email_template_test_sent', (int) $actor['id'], [
            'template_key' => $data['template_key'], 'locale' => $locale,
            'recipient_hash' => hash('sha256', $recipient),
        ]);
    }

    /** @return array<string,mixed> */
    private function validated(array $input): array
    {
        $key = strtolower(trim((string) ($input['template_key'] ?? '')));
        $name = trim((string) ($input['name'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));
        $subject = trim((string) ($input['subject_template'] ?? ''));
        $html = trim((string) ($input['html_template'] ?? ''));
        $text = trim((string) ($input['text_template'] ?? ''));
        if (str_starts_with($key, 'custom_') && !self::isCustomKey($key)) {
            throw new InvalidArgumentException('Custom template keys must start with custom_ and use at least 3 lowercase letters, numbers, or underscores after the prefix.');
        }
        if (EmailTemplateCatalog::get($key) === null && !self::isCustomKey($key)) {
            throw new InvalidArgumentException('Choose a supported notification email type or use a custom_* template key.');
        }
        if (mb_strlen($name) < 2 || mb_strlen($name) > 160) throw new InvalidArgumentException('Template name must contain 2 to 160 characters.');
        if (mb_strlen($description) > 2000) throw new InvalidArgumentException('Template description cannot exceed 2000 characters.');
        if ($subject === '' || mb_strlen($subject) > 255) throw new InvalidArgumentException('Subject template is required and cannot exceed 255 characters.');
        if ($html === '' || mb_strlen($html) > 20000) throw new InvalidArgumentException('HTML template is required and cannot exceed 20000 characters.');
        if ($text === '' || mb_strlen($text) > 10000) throw new InvalidArgumentException('Text template is required and cannot exceed 10000 characters.');
        if (preg_match('/<\\s*(?:script|iframe|object|embed|form)\\b/i', $html) === 1 || preg_match('/javascript\\s*:/i', $html) === 1 || preg_match('/\\son[a-z]+\\s*=/i', $html) === 1) {
            throw new InvalidArgumentException('Email template HTML cannot contain scripts, embedded documents, forms, or event handlers.');
        }
        $allowedVariables = self::variablesForKey($key);
        foreach ([$subject, $html, $text] as $value) {
            preg_match_all('/\\{\\{\\s*([a-z0-9_]+)\\s*\\}\\}/i', $value, $matches);
            foreach ($matches[1] ?? [] as $variable) {
                if (!in_array(strtolower((string) $variable), $allowedVariables, true)) {
                    throw new InvalidArgumentException('Unsupported variable for this email type: {{' . $variable . '}}.');
                }
            }
        }
        return [
            'template_key' => $key, 'name' => $name,
            'description' => $description !== '' ? $description : null,
            'subject_template' => $subject, 'html_template' => $html, 'text_template' => $text,
        ];
    }

    private function variantLocale(string $locale): string
    {
        $locale = strtolower(trim($locale));
        if (!in_array($locale, ['km', 'zh'], true)) throw new InvalidArgumentException('Choose Khmer or Chinese for a translated email variant.');
        return $locale;
    }

    private function transition(string $current, string $status): string
    {
        $status = strtolower(trim($status));
        $allowed = match ($current) {
            'inactive' => ['active', 'archived'], 'active' => ['inactive'], 'archived' => [], default => [],
        };
        if (!in_array($status, $allowed, true)) {
            throw new DomainException('Email template cannot move from ' . $current . ' to ' . ($status !== '' ? $status : 'an empty status') . '.');
        }
        return $status;
    }

    /** @return array<string,mixed> */
    private function template(EmailTemplateRepository $repository, int $id): array
    {
        if ($id < 1) throw new InvalidArgumentException('A valid email template ID is required.');
        $template = $repository->find($id);
        if ($template === null) throw new InvalidArgumentException('Email template was not found.');
        return $template;
    }

    /** @param array<string,mixed> $context */
    private function audit(string $event, int $actorUserId, array $context): void
    {
        (new SecurityAuditService())->record($event, $actorUserId, null, $context);
    }

    /** @return array<string,mixed> */
    private function administrator(): array
    {
        $auth = new AuthService(); $user = $auth->user();
        if ($user === null || !$auth->hasRole('administrator') || !$auth->can('communication.manage')) {
            throw new DomainException('Administrator communications permission is required.');
        }
        return $user;
    }
}
