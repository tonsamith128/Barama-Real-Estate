<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Core\Database;
use Barama\Domain\RealEstate\DomainRules;
use Barama\Repositories\AccountSettingsRepository;
use Barama\Repositories\CatalogRepository;
use Barama\Repositories\CompanyMembershipWriteRepository;
use Barama\Repositories\CompanyWriteRepository;
use Barama\Repositories\WorkflowRepository;
use Barama\Support\DomainInput;
use Barama\Support\Slugger;
use DomainException;
use InvalidArgumentException;
use Throwable;

final class CompanyOnboardingService
{
    /** @param array<string,mixed> $input */
    public function submit(array $input): int
    {
        $auth = new AuthService();
        $user = $auth->user();

        if ($user === null) {
            throw new DomainException('Authentication is required.');
        }

        if ($auth->can('developer.access')) {
            throw new DomainException(
                'Your account already has company workspace access. Create companies from the Company / Vendor workspace.'
            );
        }

        $account = new AccountSettingsRepository();

        foreach ($account->ownedCompanies((int) $user['id']) as $company) {
            if (!in_array((string) $company['status'], ['rejected', 'archived'], true)) {
                throw new DomainException(
                    'A company onboarding request is already associated with your account.'
                );
            }
        }

        $name = DomainInput::requiredString($input, 'name', 180, 2);
        $companyType = DomainInput::requiredString($input, 'company_type', 40);

        if (!in_array($companyType, DomainRules::COMPANY_TYPES, true)) {
            throw new InvalidArgumentException('Invalid company type.');
        }

        $locationId = DomainInput::optionalPositiveInt($input, 'location_id');

        if ($locationId !== null && !(new CatalogRepository())->locationExists($locationId)) {
            throw new InvalidArgumentException('Selected location does not exist.');
        }

        $email = DomainInput::optionalString($input, 'email', 191)
            ?? strtolower((string) $user['email']);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Company email address is invalid.');
        }

        $companies = new CompanyWriteRepository();
        $slug = Slugger::unique(
            (string) ($input['slug'] ?? $name),
            fn (string $candidate): bool => $companies->slugExists($candidate)
        );

        $pdo = Database::connection();

        try {
            $pdo->beginTransaction();

            $companyId = $companies->create([
                'location_id' => $locationId,
                'name' => $name,
                'slug' => $slug,
                'legal_name' => DomainInput::optionalString($input, 'legal_name', 191),
                'company_type' => $companyType,
                'registration_number' => DomainInput::optionalString($input, 'registration_number', 120),
                'tax_number' => DomainInput::optionalString($input, 'tax_number', 120),
                'email' => $email,
                'phone' => DomainInput::optionalString($input, 'phone', 60),
                'website' => DomainInput::optionalString($input, 'website', 255),
                'address_line' => DomainInput::optionalString($input, 'address_line', 255),
                'description' => DomainInput::optionalString($input, 'description', 10000),
            ]);

            (new CompanyMembershipWriteRepository())->upsert(
                $companyId,
                (int) $user['id'],
                'owner'
            );

            $workflow = new WorkflowRepository();
            $workflow->updateStatus('company', $companyId, 'pending');
            $workflow->record(
                'company',
                $companyId,
                (int) $user['id'],
                'draft',
                'pending',
                'Submitted through customer company onboarding.'
            );

            $pdo->commit();

            return $companyId;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }
}
