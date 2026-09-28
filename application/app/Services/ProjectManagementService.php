<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Core\Database;
use Barama\Domain\RealEstate\DomainRules;
use Barama\Repositories\CatalogRepository;
use Barama\Repositories\ProjectWriteRepository;
use Barama\Support\DomainInput;
use Barama\Support\RichText;
use Barama\Support\Slugger;
use DomainException;
use InvalidArgumentException;
use Throwable;

final class ProjectManagementService
{
    /** @param array<string, mixed> $input */
    public function create(array $input): int
    {
        $auth = new AuthService();

        if (!$auth->can('project.manage')) {
            throw new DomainException('Project management permission is required.');
        }

        $companyId = DomainInput::requiredPositiveInt($input, 'company_id');
        (new CompanyAccessService())->assertCanManage($companyId);

        $pdo = Database::connection();

        try {
            $pdo->beginTransaction();
            (new SubscriptionEntitlementService())->assertCanConsume(
                $companyId,
                'project'
            );
            $id = $this->persist(null, $companyId, $input);
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
    public function update(int $projectId, array $input): void
    {
        $projects = new ProjectWriteRepository();
        $project = $projects->findById($projectId);

        if ($project === null) {
            throw new InvalidArgumentException('Project was not found.');
        }

        if (!in_array((string) $project['status'], ['draft', 'rejected'], true)) {
            throw new DomainException('Only draft or rejected projects can be edited in this workflow.');
        }

        (new CompanyAccessService())->assertCanManage((int) $project['company_id']);
        $this->persist($projectId, (int) $project['company_id'], $input);
    }

    /** @param array<string, mixed> $input */
    public function updateAsAdministrator(int $projectId, array $input): void
    {
        $auth = new AuthService();
        $user = $auth->user();

        if ($user === null || !$auth->can('dashboard.access')) {
            throw new DomainException('Administrator access is required.');
        }

        $projects = new ProjectWriteRepository();
        $project = $projects->findById($projectId);

        if ($project === null) {
            throw new InvalidArgumentException('Project was not found.');
        }

        $this->persist($projectId, (int) $project['company_id'], $input);
    }

    public function deleteAsAdministrator(int $projectId): void
    {
        $auth = new AuthService();
        $user = $auth->user();

        if ($user === null || !$auth->can('dashboard.access')) {
            throw new DomainException('Administrator access is required.');
        }

        $projects = new ProjectWriteRepository();
        $project = $projects->findById($projectId);

        if ($project === null) {
            throw new InvalidArgumentException('Project was not found.');
        }

        if ((string) ($project['status'] ?? '') !== 'archived') {
            throw new DomainException('Archive the project before deleting it.');
        }

        if ($projects->activePropertyCount($projectId) > 0) {
            throw new DomainException(
                'Remove or archive and delete all project properties before deleting this project.'
            );
        }

        $projects->softDelete($projectId);
    }

    /** @param array<string, mixed> $input */
    private function persist(?int $projectId, int $companyId, array $input): int
    {
        $name = DomainInput::requiredString($input, 'name', 191, 2);
        $type = DomainInput::requiredString($input, 'project_type', 50);
        $completion = DomainInput::requiredString($input, 'completion_status', 40);

        if (!in_array($type, DomainRules::PROJECT_TYPES, true)) {
            throw new InvalidArgumentException('Invalid project type.');
        }

        if (!in_array($completion, DomainRules::PROJECT_COMPLETION_STATUSES, true)) {
            throw new InvalidArgumentException('Invalid project completion status.');
        }

        $locationId = DomainInput::optionalPositiveInt($input, 'location_id');

        if ($locationId !== null && !(new CatalogRepository())->locationExists($locationId)) {
            throw new InvalidArgumentException('Selected location does not exist.');
        }

        $data = [
            'location_id' => $locationId,
            'name' => $name,
            'project_type' => $type,
            'completion_status' => $completion,
            'address_line' => DomainInput::optionalString($input, 'address_line', 255),
            'description' => RichText::sanitize(DomainInput::optionalString($input, 'description', 10000)),
            'launch_date' => DomainInput::optionalString($input, 'launch_date', 10),
            'expected_completion_date' => DomainInput::optionalString($input, 'expected_completion_date', 10),
        ];

        $projects = new ProjectWriteRepository();

        if ($projectId !== null) {
            $projects->update($projectId, $data);
            return $projectId;
        }

        $data['company_id'] = $companyId;
        $data['slug'] = Slugger::unique(
            (string) ($input['slug'] ?? $name),
            fn (string $candidate): bool => $projects->slugExists($candidate)
        );

        return $projects->create($data);
    }
}
