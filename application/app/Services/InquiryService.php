<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Core\Database;
use Barama\Repositories\InquiryRepository;
use Barama\Security\InquiryThrottle;
use Barama\Support\DomainInput;
use DomainException;
use InvalidArgumentException;
use Throwable;

final class InquiryService
{
    /** @param array<string, mixed> $input */
    public function capture(array $input, string $ip, ?int $userId = null): int
    {
        if (trim((string) ($input['website'] ?? '')) !== '') {
            throw new InvalidArgumentException('Inquiry could not be submitted.');
        }

        if (!(new InquiryThrottle())->allow($ip)) {
            throw new DomainException('Too many inquiry attempts. Please try again later.');
        }

        $targetType = DomainInput::requiredString($input, 'target_type', 32);
        $targetId = DomainInput::requiredPositiveInt($input, 'target_id');

        if (!in_array($targetType, ['company', 'project', 'property', 'listing'], true)) {
            throw new InvalidArgumentException('Invalid inquiry target.');
        }

        $repository = new InquiryRepository();
        $target = $repository->resolvePublicTarget($targetType, $targetId);

        if ($target === null || $target['company_id'] === null) {
            throw new InvalidArgumentException('The selected inquiry target is unavailable.');
        }

        $email = DomainInput::optionalString($input, 'contact_email', 191);
        $phone = DomainInput::optionalString($input, 'contact_phone', 60);

        if ($email === null && $phone === null) {
            throw new InvalidArgumentException('Provide an email address or phone number.');
        }

        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Contact email address is invalid.');
        }

        $contactName = DomainInput::requiredString($input, 'contact_name', 160, 2);
        $message = DomainInput::requiredString($input, 'message', 5000, 10);
        $companyId = (int) $target['company_id'];
        $pdo = Database::connection();

        try {
            $pdo->beginTransaction();

            $inquiryId = $repository->create([
                'company_id' => $companyId,
                'project_id' => $target['project_id'] !== null ? (int) $target['project_id'] : null,
                'property_id' => $target['property_id'] !== null ? (int) $target['property_id'] : null,
                'listing_id' => $target['listing_id'] !== null ? (int) $target['listing_id'] : null,
                'user_id' => $userId,
                'contact_name' => $contactName,
                'contact_email' => $email,
                'contact_phone' => $phone,
                'preferred_contact' => DomainInput::optionalString($input, 'preferred_contact', 24),
                'subject' => DomainInput::optionalString($input, 'subject', 191),
                'message' => $message,
            ]);

            (new NotificationService())->notifyCompany(
                $companyId,
                'inquiry_updates',
                [
                    'type' => 'inquiry_received',
                    'title' => 'New marketplace inquiry',
                    'message' => $contactName . ' sent a new property inquiry.',
                    'action_url' => '/developer/manage/inquiry/' . $inquiryId,
                    'entity_type' => 'inquiry',
                    'entity_id' => $inquiryId,
                    'dedupe_key' => 'inquiry:' . $inquiryId . ':new',
                ]
            );

            $pdo->commit();

            return $inquiryId;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }
}
