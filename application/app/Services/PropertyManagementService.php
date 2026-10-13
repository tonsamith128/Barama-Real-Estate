<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Core\Database;
use Barama\Domain\RealEstate\DomainRules;
use Barama\Repositories\CatalogRepository;
use Barama\Repositories\ProjectWriteRepository;
use Barama\Repositories\PropertyWriteRepository;
use Barama\Support\DomainInput;
use Barama\Support\RichText;
use Barama\Support\IdGenerator;
use Barama\Support\Slugger;
use DomainException;
use InvalidArgumentException;
use Throwable;

final class PropertyManagementService
{
    /** @param array<string, mixed> $input */
    public function create(array $input): int
    {
        $auth = new AuthService();
        $user = $auth->user();

        if ($user === null || !$auth->can('property.manage')) {
            throw new DomainException('Property management permission is required.');
        }

        $companyId = DomainInput::requiredPositiveInt($input, 'company_id');
        (new CompanyAccessService())->assertCanManage($companyId);

        $pdo = Database::connection();

        try {
            $pdo->beginTransaction();
            (new SubscriptionEntitlementService())->assertCanConsume(
                $companyId,
                'property'
            );
            $id = $this->persist(
                null,
                $companyId,
                (int) $user['id'],
                $input
            );
            $pdo->commit();

            return $id;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    /** @param array<string, mixed> $input */
    public function update(int $propertyId, array $input): void
    {
        $properties = new PropertyWriteRepository();
        $property = $properties->findById($propertyId);

        if ($property === null || $property['company_id'] === null) {
            throw new InvalidArgumentException('Property was not found.');
        }

        if (!in_array((string) $property['status'], ['draft', 'rejected'], true)) {
            throw new DomainException('Only draft or rejected properties can be edited in this workflow.');
        }

        (new CompanyAccessService())->assertCanManage((int) $property['company_id']);

        $ownerUserId = $property['owner_user_id'] !== null
            ? (int) $property['owner_user_id']
            : (int) (new AuthService())->user()['id'];

        $this->persist(
            $propertyId,
            (int) $property['company_id'],
            $ownerUserId,
            $input
        );
    }

    /** @param array<string, mixed> $input */
    public function updateAsAdministrator(int $propertyId, array $input): void
    {
        $auth = new AuthService();
        $user = $auth->user();

        if ($user === null || !$auth->can('dashboard.access') || !$auth->can('property.manage')) { // BARAMA_A48D1_PROPERTY_SERVICE
            throw new DomainException('Administrator access is required.');
        }

        $properties = new PropertyWriteRepository();
        $property = $properties->findById($propertyId);

        if ($property === null || $property['company_id'] === null) {
            throw new InvalidArgumentException('Property was not found.');
        }

        $ownerUserId = $property['owner_user_id'] !== null
            ? (int) $property['owner_user_id']
            : (int) $user['id'];

        $this->persist(
            $propertyId,
            (int) $property['company_id'],
            $ownerUserId,
            $input
        );
    }

    public function deleteAsAdministrator(int $propertyId): void
    {
        $auth = new AuthService();
        $user = $auth->user();

        if ($user === null || !$auth->can('dashboard.access') || !$auth->can('property.manage')) { // BARAMA_A48D1_PROPERTY_SERVICE
            throw new DomainException('Administrator access is required.');
        }

        $properties = new PropertyWriteRepository();
        $property = $properties->findById($propertyId);

        if ($property === null) {
            throw new InvalidArgumentException('Property was not found.');
        }

        if ((string) ($property['status'] ?? '') !== 'archived') {
            throw new DomainException('Archive the property before deleting it.');
        }

        if ($properties->activeListingCount($propertyId) > 0) {
            throw new DomainException(
                'Remove or archive the property listings before deleting this property.'
            );
        }

        $properties->softDelete($propertyId);
    }

    /** @param array<string, mixed> $input */
    private function persist(
        ?int $propertyId,
        int $companyId,
        int $ownerUserId,
        array $input
    ): int {
        $title = DomainInput::requiredString($input, 'title', 191, 2);
        $propertyType = DomainInput::requiredString($input, 'property_type', 50);

        if (!in_array($propertyType, DomainRules::PROPERTY_TYPES, true)) {
            throw new InvalidArgumentException('Invalid property type.');
        }

        $projectId = DomainInput::optionalPositiveInt($input, 'project_id');

        if ($projectId !== null) {
            $project = (new ProjectWriteRepository())->findById($projectId);

            if ($project === null || (int) $project['company_id'] !== $companyId) {
                throw new InvalidArgumentException('Project does not belong to this company.');
            }
        }

        $locationId = DomainInput::optionalPositiveInt($input, 'location_id');

        if ($locationId !== null && !(new CatalogRepository())->locationExists($locationId)) {
            throw new InvalidArgumentException('Selected location does not exist.');
        }

        $data = [
            'project_id' => $projectId,
            'location_id' => $locationId,
            'title' => $title,
            'property_type' => $propertyType,
            'bedrooms' => DomainInput::optionalDecimal($input, 'bedrooms'),
            'bathrooms' => DomainInput::optionalDecimal($input, 'bathrooms'),
            'parking_spaces' => DomainInput::optionalNonNegativeInt($input, 'parking_spaces'),
            'floor_number' => ($input['floor_number'] ?? '') !== '' ? (int) $input['floor_number'] : null,
            'total_floors' => DomainInput::optionalPositiveInt($input, 'total_floors'),
            'area_value' => DomainInput::optionalDecimal($input, 'area_value'),
            'area_unit' => DomainInput::optionalString($input, 'area_unit', 20),
            'land_area_value' => DomainInput::optionalDecimal($input, 'land_area_value'),
            'land_area_unit' => DomainInput::optionalString($input, 'land_area_unit', 20),
            'furnishing_status' => DomainInput::optionalString($input, 'furnishing_status', 32),
            'year_built' => DomainInput::optionalPositiveInt($input, 'year_built'),
            'address_line' => DomainInput::optionalString($input, 'address_line', 255),
            'description' => RichText::sanitize(DomainInput::optionalString($input, 'description', 10000)),
        ];

        $properties = new PropertyWriteRepository();

        if ($propertyId !== null) {
            $properties->update($propertyId, $data);
            return $propertyId;
        }

        $data['company_id'] = $companyId;
        $data['owner_user_id'] = $ownerUserId;
        $data['reference_code'] = IdGenerator::reference();
        $data['slug'] = Slugger::unique(
            (string) ($input['slug'] ?? $title),
            fn (string $candidate): bool => $properties->slugExists($candidate)
        );

        return $properties->create($data);
    }
}
