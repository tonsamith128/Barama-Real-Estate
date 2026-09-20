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
    ];

    /**
     * @param array{
     *   type:string,title:string,message:string,action_url?:?string,
     *   entity_type?:?string,entity_id?:?int,dedupe_key:string,
     *   email_subject?:?string,email_html?:?string,email_text?:?string
     * } $data
     */
    public function notifyUser(int $userId, string $category, array $data): void
    {
        $this->assertCategory($category);

        $repository = new NotificationRepository();
        $user = $repository->user($userId);

        if ($user === null || (string) $user['status'] !== 'active') {
            return;
        }

        $preferences = $repository->preferences($userId);

        if (!$preferences[$category]) {
            return;
        }

        $notificationId = null;

        if ($preferences['in_app_enabled']) {
            $notificationId = $repository->createNotification([
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

        if (!$preferences['email_enabled']) {
            return;
        }

        $email = strtolower(trim((string) $user['email']));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        [$html, $text] = $this->emailBodies($data);

        $repository->queueEmail([
            'user_id' => $userId,
            'notification_id' => $notificationId,
            'to_address' => $email,
            'subject' => $data['email_subject'] ?? $data['title'],
            'html_body' => $html,
            'text_body' => $text,
            'dedupe_key' => 'user:' . $userId . ':' . $data['dedupe_key'],
        ]);
    }

    /**
     * @param array{
     *   type:string,title:string,message:string,action_url?:?string,
     *   entity_type?:?string,entity_id?:?int,dedupe_key:string,
     *   email_subject?:?string,email_html?:?string,email_text?:?string
     * } $data
     */
    public function notifyCompany(int $companyId, string $category, array $data): void
    {
        $this->assertCategory($category);
        $repository = new NotificationRepository();

        foreach ($repository->companyRecipients($companyId) as $recipient) {
            $recipientData = $data;
            $recipientData['dedupe_key'] = $data['dedupe_key']
                . ':recipient:' . (int) $recipient['id'];

            $this->notifyUser((int) $recipient['id'], $category, $recipientData);
        }
    }

    /**
     * Queue a transactional email for a guest/contact address that has no user preferences.
     *
     * @param array{subject:string,html:string,text:string,dedupe_key:string} $data
     */
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

        $html = '<p><strong>' . $title . '</strong></p><p>' . $message . '</p>';
        $text = (string) $data['title'] . "\n\n" . (string) $data['message'];

        if ($action !== '') {
            $absolute = url($action);
            $html .= '<p><a href="' . e($absolute) . '">Open in Barama Real Estate</a></p>';
            $text .= "\n\nOpen: " . $absolute;
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
