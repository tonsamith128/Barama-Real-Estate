<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Repositories\ContactMessageRepository;
use Barama\Repositories\SiteContentRepository;
use Barama\Security\ContactMessageThrottle;
use Barama\Support\DomainInput;
use DomainException;
use InvalidArgumentException;

final class ContactMessageService
{
    /** @param array<string,mixed> $input */
    public function capture(
        array $input,
        string $ip,
        string $userAgent,
        ?int $userId = null
    ): int {
        if (trim((string) ($input['website'] ?? '')) !== '') {
            throw new InvalidArgumentException('Your message could not be submitted.');
        }

        if (!(new ContactMessageThrottle())->allow($ip)) {
            throw new DomainException('Too many contact attempts. Please try again later.');
        }

        $contactConfig = (new SiteContentRepository())->get()['contact'] ?? [];
        $formConfig = is_array($contactConfig['sections']['form'] ?? null)
            ? $contactConfig['sections']['form']
            : [];

        if (empty($contactConfig['enabled']) || empty($formConfig['enabled'])) {
            throw new DomainException('The contact form is currently unavailable.');
        }

        $name = DomainInput::requiredString($input, 'contact_name', 160, 2);
        $email = strtolower(DomainInput::requiredString($input, 'contact_email', 191, 5));
        $phone = DomainInput::optionalString($input, 'contact_phone', 60);
        $subject = DomainInput::requiredString($input, 'subject', 160, 2);
        $message = DomainInput::requiredString($input, 'message', 5000, 10);

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Contact email address is invalid.');
        }

        if (!empty($formConfig['require_phone']) && $phone === null) {
            throw new InvalidArgumentException('Phone number is required.');
        }

        $consentText = trim((string) ($formConfig['consent_text'] ?? ''));

        if (
            $consentText !== ''
            && !in_array(strtolower(trim((string) ($input['consent'] ?? ''))), ['1', 'true', 'on', 'yes'], true)
        ) {
            throw new InvalidArgumentException('Please confirm the contact-form consent notice.');
        }

        $id = (new ContactMessageRepository())->create([
            'user_id' => $userId,
            'contact_name' => $name,
            'contact_email' => $email,
            'contact_phone' => $phone,
            'subject' => $subject,
            'message' => $message,
            'ip_hash' => hash('sha256', trim($ip)),
            'user_agent_hash' => hash('sha256', trim($userAgent)),
        ]);

        $destination = strtolower(trim((string) ($formConfig['destination_email'] ?? '')));

        if ($destination !== '' && filter_var($destination, FILTER_VALIDATE_EMAIL) !== false) {
            $safeName = e($name);
            $safeEmail = e($email);
            $safePhone = e((string) ($phone ?? 'Not provided'));
            $safeSubject = e($subject);
            $safeMessage = nl2br(e($message));

            (new NotificationService())->notifyAddress($destination, [
                'subject' => 'Contact form: ' . $subject,
                'html' => '<p><strong>New Barama contact message</strong></p>'
                    . '<p><strong>From:</strong> ' . $safeName . '<br>'
                    . '<strong>Email:</strong> ' . $safeEmail . '<br>'
                    . '<strong>Phone:</strong> ' . $safePhone . '<br>'
                    . '<strong>Subject:</strong> ' . $safeSubject . '</p>'
                    . '<p>' . $safeMessage . '</p>',
                'text' => "New Barama contact message\n\n"
                    . "From: {$name}\nEmail: {$email}\nPhone: " . ($phone ?? 'Not provided')
                    . "\nSubject: {$subject}\n\n{$message}",
                'dedupe_key' => 'contact-message:' . $id,
            ]);
        }

        return $id;
    }

    public function setStatus(int $id, string $status): void
    {
        $actor = $this->administrator();
        $repository = new ContactMessageRepository();
        $message = $repository->find($id);

        if ($message === null) {
            throw new InvalidArgumentException('Contact message was not found.');
        }

        $status = strtolower(trim($status));

        if (!in_array($status, ['new', 'read', 'resolved', 'spam'], true)) {
            throw new InvalidArgumentException('Choose a supported contact-message status.');
        }

        $repository->setStatus($id, $status, (int) $actor['id']);

        (new SecurityAuditService())->record(
            'admin.contact_message_status_changed',
            (int) $actor['id'],
            null,
            [
                'contact_message_id' => $id,
                'from_status' => (string) $message['status'],
                'to_status' => $status,
            ]
        );
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
}
