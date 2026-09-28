<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Repositories\EmailTemplateRepository;
use DomainException;
use InvalidArgumentException;

final class EmailTemplateManagementService
{
    private const SUPPORTED_KEYS = [
        'inquiry_received',
        'viewing_confirmed',
        'viewing_rescheduled',
        'viewing_completed',
        'viewing_declined',
        'announcement_published',
    ];

    private const ALLOWED_VARIABLES = [
        'title',
        'message',
        'action_url',
        'action_label',
        'recipient_name',
        'app_name',
        'app_url',
    ];

    /** @return array<string,string> */
    public static function supportedKeys(): array
    {
        return [
            'inquiry_received' => 'Inquiry received',
            'viewing_confirmed' => 'Viewing confirmed',
            'viewing_rescheduled' => 'Viewing rescheduled',
            'viewing_completed' => 'Viewing completed',
            'viewing_declined' => 'Viewing declined',
            'announcement_published' => 'Announcement published',
        ];
    }

    /** @param array<string,mixed> $input */
    public function create(array $input): int
    {
        $actor = $this->administrator();
        $repository = new EmailTemplateRepository();
        $data = $this->validated($input);

        if ($repository->keyExists($data['template_key'])) {
            throw new InvalidArgumentException(
                'An email template already exists for this notification type.'
            );
        }

        $data['actor_user_id'] = (int) $actor['id'];
        $id = $repository->create($data);

        (new SecurityAuditService())->record(
            'admin.communication_email_template_created',
            (int) $actor['id'],
            null,
            [
                'email_template_id' => $id,
                'template_key' => $data['template_key'],
            ]
        );

        return $id;
    }

    /** @param array<string,mixed> $input */
    public function update(int $id, array $input): void
    {
        $actor = $this->administrator();
        $repository = new EmailTemplateRepository();
        $template = $this->template($repository, $id);

        if ((string) $template['status'] !== 'inactive') {
            throw new DomainException(
                'Deactivate the email template before editing its content.'
            );
        }

        $data = $this->validated($input);

        if ($repository->keyExists($data['template_key'], $id)) {
            throw new InvalidArgumentException(
                'Another email template already uses this notification type.'
            );
        }

        $data['actor_user_id'] = (int) $actor['id'];
        $repository->update($id, $data);

        (new SecurityAuditService())->record(
            'admin.communication_email_template_updated',
            (int) $actor['id'],
            null,
            [
                'email_template_id' => $id,
                'template_key' => $data['template_key'],
            ]
        );
    }

    public function setStatus(int $id, string $status): void
    {
        $actor = $this->administrator();
        $repository = new EmailTemplateRepository();
        $template = $this->template($repository, $id);
        $current = (string) $template['status'];
        $status = strtolower(trim($status));

        $allowed = match ($current) {
            'inactive' => ['active', 'archived'],
            'active' => ['inactive'],
            'archived' => [],
            default => [],
        };

        if (!in_array($status, $allowed, true)) {
            throw new DomainException(
                'Email template cannot move from '
                . $current . ' to ' . ($status !== '' ? $status : 'an empty status') . '.'
            );
        }

        $repository->setStatus($id, $status, (int) $actor['id']);

        (new SecurityAuditService())->record(
            'admin.communication_email_template_status_changed',
            (int) $actor['id'],
            null,
            [
                'email_template_id' => $id,
                'template_key' => (string) $template['template_key'],
                'from_status' => $current,
                'to_status' => $status,
            ]
        );
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

        if (!in_array($key, self::SUPPORTED_KEYS, true)) {
            throw new InvalidArgumentException(
                'Choose a supported notification email template type.'
            );
        }

        if (mb_strlen($name) < 2 || mb_strlen($name) > 160) {
            throw new InvalidArgumentException(
                'Template name must contain 2 to 160 characters.'
            );
        }

        if (mb_strlen($description) > 2000) {
            throw new InvalidArgumentException(
                'Template description cannot exceed 2000 characters.'
            );
        }

        if ($subject === '' || mb_strlen($subject) > 255) {
            throw new InvalidArgumentException(
                'Subject template is required and cannot exceed 255 characters.'
            );
        }

        if ($html === '' || mb_strlen($html) > 20000) {
            throw new InvalidArgumentException(
                'HTML template is required and cannot exceed 20000 characters.'
            );
        }

        if ($text === '' || mb_strlen($text) > 10000) {
            throw new InvalidArgumentException(
                'Text template is required and cannot exceed 10000 characters.'
            );
        }

        if (
            preg_match('/<\s*script\b/i', $html) === 1
            || preg_match('/javascript\s*:/i', $html) === 1
        ) {
            throw new InvalidArgumentException(
                'Email template HTML cannot contain script content.'
            );
        }

        foreach ([$subject, $html, $text] as $template) {
            preg_match_all(
                '/\{\{\s*([a-z0-9_]+)\s*\}\}/i',
                $template,
                $matches
            );

            foreach ($matches[1] ?? [] as $variable) {
                if (!in_array(strtolower((string) $variable), self::ALLOWED_VARIABLES, true)) {
                    throw new InvalidArgumentException(
                        'Unsupported email template variable: {{' . $variable . '}}.'
                    );
                }
            }
        }

        return [
            'template_key' => $key,
            'name' => $name,
            'description' => $description !== '' ? $description : null,
            'subject_template' => $subject,
            'html_template' => $html,
            'text_template' => $text,
        ];
    }

    /** @return array<string,mixed> */
    private function template(
        EmailTemplateRepository $repository,
        int $id
    ): array {
        if ($id < 1) {
            throw new InvalidArgumentException(
                'A valid email template ID is required.'
            );
        }

        $template = $repository->find($id);

        if ($template === null) {
            throw new InvalidArgumentException('Email template was not found.');
        }

        return $template;
    }

    /** @return array<string,mixed> */
    private function administrator(): array
    {
        $auth = new AuthService();
        $user = $auth->user();

        if (
            $user === null
            || !$auth->hasRole('administrator')
            || !$auth->can('communication.manage')
        ) {
            throw new DomainException(
                'Administrator communications permission is required.'
            );
        }

        return $user;
    }
}
