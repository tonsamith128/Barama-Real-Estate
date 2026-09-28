<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Repositories\NotificationRepository;
use InvalidArgumentException;

final class NotificationService
{
    private const CATEGORIES = [
        'saved_search_alerts',
        'viewing_updates',
        'inquiry_updates',
        'announcements',
    ];

    /**
     * @param array{
     *   type:string,title:string,message:string,action_url?:?string,
     *   action_label?:?string,entity_type?:?string,entity_id?:?int,
     *   dedupe_key:string,email_subject?:?string,email_html?:?string,
     *   email_text?:?string,in_app?:bool,email?:bool
     * } $data
     * @return array{notification_id:?int,email_outbox_id:?int}
     */
    public function notifyUser(int $userId, string $category, array $data): array
    {
        $this->assertCategory($category);

        $result = ['notification_id' => null, 'email_outbox_id' => null];
        $repository = new NotificationRepository();
        $user = $repository->user($userId);

        if ($user === null || (string) $user['status'] !== 'active') {
            return $result;
        }

        $preferences = $repository->preferences($userId);

        if ($category !== 'announcements' && empty($preferences[$category])) {
            return $result;
        }

        $allowInApp = !array_key_exists('in_app', $data) || (bool) $data['in_app'];
        $allowEmail = !array_key_exists('email', $data) || (bool) $data['email'];

        if ($allowInApp && $preferences['in_app_enabled']) {
            $result['notification_id'] = $repository->createNotification([
                'user_id' => $userId,
                'category' => $category,
                'type' => $data['type'],
                'title' => $data['title'],
                'message' => $data['message'],
                'action_url' => $data['action_url'] ?? null,
                'entity_type' => $data['entity_type'] ?? null,
                'entity_id' => $data['entity_id'] ?? null,
                'dedupe_key' => $data['dedupe_key'],
            ]);
        }

        if (!$allowEmail || !$preferences['email_enabled']) {
            return $result;
        }

        $email = strtolower(trim((string) $user['email']));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $result;
        }

        [$html, $text] = $this->emailBodies($data);
        $subject = (string) ($data['email_subject'] ?? $data['title']);

        if (!$this->hasExplicitEmailBodies($data)) {
            $action = trim((string) ($data['action_url'] ?? ''));
            $actionLabel = trim((string) ($data['action_label'] ?? ''));
            $rendered = (new EmailTemplateRendererService())->render(
                (string) $data['type'],
                [
                    'title' => (string) $data['title'],
                    'message' => (string) $data['message'],
                    'action_url' => $action !== '' ? url($action) : '',
                    'action_label' => $actionLabel !== '' ? $actionLabel : 'Open in Barama Real Estate',
                    'recipient_name' => (string) $user['name'],
                    'app_name' => (string) config('app.name', 'Barama Real Estate'),
                    'app_url' => rtrim((string) config('app.url', ''), '/'),
                ],
                ['subject' => $subject, 'html' => $html, 'text' => $text]
            );
            $subject = $rendered['subject'];
            $html = $rendered['html'];
            $text = $rendered['text'];
        }

        $result['email_outbox_id'] = $repository->queueEmail([
            'user_id' => $userId,
            'notification_id' => $result['notification_id'],
            'to_address' => $email,
            'subject' => $subject,
            'html_body' => $html,
            'text_body' => $text,
            'dedupe_key' => 'user:' . $userId . ':' . $data['dedupe_key'],
        ]);

        return $result;
    }

    /**
     * @param array{
     *   type:string,title:string,message:string,action_url?:?string,
     *   action_label?:?string,entity_type?:?string,entity_id?:?int,
     *   dedupe_key:string,email_subject?:?string,email_html?:?string,
     *   email_text?:?string,in_app?:bool,email?:bool
     * } $data
     */
    public function notifyCompany(int $companyId, string $category, array $data): void
    {
        $this->assertCategory($category);
        $repository = new NotificationRepository();

        foreach ($repository->companyRecipients($companyId) as $recipient) {
            $recipientData = $data;
            $recipientData['dedupe_key'] = $data['dedupe_key'] . ':recipient:' . (int) $recipient['id'];
            $this->notifyUser((int) $recipient['id'], $category, $recipientData);
        }
    }

    /** @param array{subject:string,html:string,text:string,dedupe_key:string} $data */
    public function notifyAddress(string $email, array $data): void
    {
        $email = strtolower(trim($email));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        (new NotificationRepository())->queueEmail([
            'user_id' => null,
            'notification_id' => null,
            'to_address' => $email,
            'subject' => $data['subject'],
            'html_body' => $data['html'],
            'text_body' => $data['text'],
            'dedupe_key' => 'guest:' . hash('sha256', $email) . ':' . $data['dedupe_key'],
        ]);
    }

    /** @param array<string,mixed> $input */
    public function updatePreferences(int $userId, array $input): void
    {
        $enabled = static fn (string $key): bool => in_array(
            (string) ($input[$key] ?? ''),
            ['1', 'true', 'on'],
            true
        );

        (new NotificationRepository())->savePreferences($userId, [
            'in_app_enabled' => $enabled('in_app_enabled'),
            'email_enabled' => $enabled('email_enabled'),
            'saved_search_alerts' => $enabled('saved_search_alerts'),
            'viewing_updates' => $enabled('viewing_updates'),
            'inquiry_updates' => $enabled('inquiry_updates'),
        ]);
    }

    /** @param array<string,mixed> $data */
    private function hasExplicitEmailBodies(array $data): bool
    {
        return isset($data['email_html'], $data['email_text'])
            && is_string($data['email_html'])
            && is_string($data['email_text']);
    }

    /** @param array<string,mixed> $data */
    private function emailBodies(array $data): array
    {
        if (
            isset($data['email_html'], $data['email_text'])
            && is_string($data['email_html'])
            && is_string($data['email_text'])
        ) {
            return [$data['email_html'], $data['email_text']];
        }

        $title = e((string) $data['title']);
        $message = nl2br(e((string) $data['message']));
        $action = trim((string) ($data['action_url'] ?? ''));
        $actionLabel = trim((string) ($data['action_label'] ?? ''));

        $html = '<p><strong>' . $title . '</strong></p><p>' . $message . '</p>';
        $text = (string) $data['title'] . "\n\n" . (string) $data['message'];

        if ($action !== '') {
            $absolute = url($action);
            $label = $actionLabel !== '' ? $actionLabel : 'Open in Barama Real Estate';
            $html .= '<p><a href="' . e($absolute) . '">' . e($label) . '</a></p>';
            $text .= "\n\n" . $label . ': ' . $absolute;
        }

        return [$html, $text];
    }

    private function assertCategory(string $category): void
    {
        if (!in_array($category, self::CATEGORIES, true)) {
            throw new InvalidArgumentException('Unsupported notification category.');
        }
    }
}
