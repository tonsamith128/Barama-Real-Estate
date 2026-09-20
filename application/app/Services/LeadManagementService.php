<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Repositories\InquiryRepository;
use Barama\Repositories\LeadRepository;
use Barama\Support\DomainInput;
use DomainException;
use InvalidArgumentException;

final class LeadManagementService
{
    /** @param array<string, mixed> $input */
    public function convertInquiry(int $inquiryId, array $input = []): int
    {
        $auth = new AuthService();
        $user = $auth->user();

        if ($user === null || !$auth->can('lead.manage')) {
            throw new DomainException('Lead management permission is required.');
        }

        $inquiry = (new InquiryRepository())->findById($inquiryId);

        if ($inquiry === null || $inquiry['company_id'] === null) {
            throw new InvalidArgumentException('Inquiry was not found.');
        }

        (new CompanyAccessService())->assertCanManage((int) $inquiry['company_id']);

        $leads = new LeadRepository();

        if ($leads->inquiryAlreadyConverted($inquiryId)) {
            throw new DomainException('This inquiry has already been converted to a lead.');
        }

        $budgetMin = DomainInput::optionalDecimal($input, 'budget_min');
        $budgetMax = DomainInput::optionalDecimal($input, 'budget_max');

        if ($budgetMin !== null && $budgetMax !== null && (float) $budgetMin > (float) $budgetMax) {
            throw new InvalidArgumentException('Minimum budget cannot exceed maximum budget.');
        }

        $currency = strtoupper(DomainInput::optionalString($input, 'currency', 3) ?? 'USD');

        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new InvalidArgumentException('Currency must use a three-letter code.');
        }

        return $leads->createFromInquiry([
            'company_id' => (int) $inquiry['company_id'],
            'inquiry_id' => $inquiryId,
            'assigned_user_id' => (int) $user['id'],
            'contact_name' => (string) $inquiry['contact_name'],
            'contact_email' => $inquiry['contact_email'],
            'contact_phone' => $inquiry['contact_phone'],
            'transaction_type' => DomainInput::optionalString($input, 'transaction_type', 24),
            'property_type' => DomainInput::optionalString($input, 'property_type', 50),
            'preferred_location_id' => DomainInput::optionalPositiveInt($input, 'preferred_location_id'),
            'budget_min' => $budgetMin,
            'budget_max' => $budgetMax,
            'currency' => $currency,
            'notes' => DomainInput::optionalString($input, 'notes', 10000),
        ]);
    }
}
