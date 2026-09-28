<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Core\Database;
use Barama\Domain\RealEstate\DomainRules;
use Barama\Repositories\CatalogRepository;
use Barama\Repositories\CompanyMembershipWriteRepository;
use Barama\Repositories\CompanyRepository;
use Barama\Repositories\CompanyWriteRepository;
use Barama\Support\DomainInput;
use Barama\Support\RichText;
use Barama\Support\Slugger;
use DomainException;
use InvalidArgumentException;
use Throwable;

final class CompanyManagementService
{
    /** @param array<string, mixed> $input */
    public function create(array $input): int
    {
        $auth = new AuthService();
        $user = $auth->user();

        if ($user === null || !$auth->can('company.manage')) {
            throw new DomainException('Company management permission is required.');
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

        $companies = new CompanyWriteRepository();
        $slug = Slugger::unique(
            (string) ($input['slug'] ?? $name),
            fn (string $candidate): bool => $companies->slugExists($candidate)
        );

        $email = DomainInput::optionalString($input, 'email', 191);

        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Company email address is invalid.');
        }

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
                'description' => RichText::sanitizeForStorage((string) ($input['description'] ?? ''), 10000),
            ]);

            (new CompanyMembershipWriteRepository())->upsert(
                $companyId,
                (int) $user['id'],
                'owner'
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

    /** @param array<string, mixed> $input */
    public function update(int $companyId, array $input): void
    {
        (new CompanyAccessService())->assertCanManage($companyId);

        $company = (new CompanyRepository())->findById($companyId);

        if ($company === null) {
            throw new InvalidArgumentException('Company was not found.');
        }

        if (!in_array((string) $company['status'], ['draft', 'rejected'], true)) {
            throw new DomainException(
                'Reviewed or published company records are locked. Return the company to an editable review state before changing its profile.'
            );
        }

        $this->persistUpdate($companyId, $input);
    }

    /** @param array<string, mixed> $input */
    public function updateAsAdministrator(int $companyId, array $input): void
    {
        $auth = new AuthService();
        $user = $auth->user();

        if ($user === null || !$auth->can('dashboard.access')) {
            throw new DomainException('Administrator access is required.');
        }

        $company = (new CompanyRepository())->findById($companyId);

        if ($company === null) {
            throw new InvalidArgumentException('Company was not found.');
        }

        $this->persistUpdate($companyId, $input);
    }

    public function deleteAsAdministrator(int $companyId): void
    {
        $auth = new AuthService();
        $user = $auth->user();

        if ($user === null || !$auth->can('dashboard.access')) {
            throw new DomainException('Administrator access is required.');
        }

        $company = (new CompanyRepository())->findById($companyId);

        if ($company === null) {
            throw new InvalidArgumentException('Company was not found.');
        }

        if ((string) ($company['status'] ?? '') !== 'archived') {
            throw new DomainException('Archive the company before deleting it.');
        }

        $companies = new CompanyWriteRepository();

        if ($companies->activeProjectCount($companyId) > 0) {
            throw new DomainException(
                'Remove or archive and delete all company projects before deleting this company.'
            );
        }

        if ($companies->activePropertyCount($companyId) > 0) {
            throw new DomainException(
                'Remove or archive and delete all company properties before deleting this company.'
            );
        }

        $companies->softDelete($companyId);
    }

    /** @param array<string, mixed> $input */
    private function persistUpdate(int $companyId, array $input): void
    {
        $name = DomainInput::requiredString($input, 'name', 180, 2);
        $companyType = DomainInput::requiredString($input, 'company_type', 40);

        if (!in_array($companyType, DomainRules::COMPANY_TYPES, true)) {
            throw new InvalidArgumentException('Invalid company type.');
        }

        $locationId = DomainInput::optionalPositiveInt($input, 'location_id');

        if ($locationId !== null && !(new CatalogRepository())->locationExists($locationId)) {
            throw new InvalidArgumentException('Selected location does not exist.');
        }

        $email = DomainInput::optionalString($input, 'email', 191);

        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Company email address is invalid.');
        }

        (new CompanyWriteRepository())->update($companyId, [
            'location_id' => $locationId,
            'name' => $name,
            'legal_name' => DomainInput::optionalString($input, 'legal_name', 191),
            'company_type' => $companyType,
            'registration_number' => DomainInput::optionalString($input, 'registration_number', 120),
            'tax_number' => DomainInput::optionalString($input, 'tax_number', 120),
            'email' => $email,
            'phone' => DomainInput::optionalString($input, 'phone', 60),
            'website' => DomainInput::optionalString($input, 'website', 255),
            'address_line' => DomainInput::optionalString($input, 'address_line', 255),
            'description' => RichText::sanitizeForStorage((string) ($input['description'] ?? ''), 10000),
        ]);
    }
}
