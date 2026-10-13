<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Core\Database;
use Barama\Domain\RealEstate\DomainRules;
use InvalidArgumentException;
use PDO;

/**
 * Read-only property readiness evaluator.
 *
 * The evaluator performs SELECT-only lookups. Readiness is computed from
 * canonical property fields, parent lifecycle state and property media.
 * Listing counts remain context only and never contribute to the score.
 * Operational filters, stale-age buckets, CSV export and remediation links remain computed-only.
 */
final class PropertyReadinessService
{
    /**
     * @param array<string,mixed> $record
     * @param array<int,array<string,mixed>> $media
     * @return array<string,mixed>
     */
    public function evaluate(array $record, array $media): array
    {
        $plainDescription = html_entity_decode(
            strip_tags((string) ($record['description'] ?? '')),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );
        $plainDescription = preg_replace('/\s+/u', ' ', trim($plainDescription)) ?? '';
        $descriptionLength = function_exists('mb_strlen')
            ? mb_strlen($plainDescription)
            : strlen($plainDescription);

        $mediaCount = count($media);
        $primaryMediaCount = 0;
        foreach ($media as $item) {
            if ((int) ($item['is_primary'] ?? 0) === 1) {
                $primaryMediaCount++;
            }
        }

        if ($media === [] && isset($record['media_count'])) {
            $mediaCount = max(0, (int) $record['media_count']);
        }
        if ($media === [] && isset($record['primary_media_count'])) {
            $primaryMediaCount = max(0, (int) $record['primary_media_count']);
        }

        $projectLinked = !empty($record['project_id']);
        $companyStatus = strtolower(trim((string) ($record['company_status'] ?? '')));
        $projectStatus = strtolower(trim((string) ($record['project_status'] ?? '')));
        $propertyType = strtolower(trim((string) ($record['property_type'] ?? '')));

        $checks = [
            $this->check('title', 'Property title', 8,
                trim((string) ($record['title'] ?? '')) !== '',
                'A clear property title is present.', 'Add a clear property title.'),
            $this->check('property_type', 'Property type', 7,
                in_array($propertyType, DomainRules::PROPERTY_TYPES, true),
                'The canonical property type is valid.', 'Select a valid property type.'),
            $this->check('location', 'Location', 10,
                (int) ($record['location_id'] ?? 0) > 0,
                'A canonical location is linked.', 'Select the property location.'),
            $this->check('address', 'Address', 10,
                trim((string) ($record['address_line'] ?? '')) !== '',
                'The address is present.', 'Add the property address.'),
            $this->check('area', 'Area', 10,
                (float) ($record['area_value'] ?? 0) > 0
                    && trim((string) ($record['area_unit'] ?? '')) !== '',
                'Area value and unit are present.', 'Add a valid area value and unit.'),
            $this->check('description', 'Description', 15,
                $descriptionLength >= 80,
                'The readable description is at least 80 characters.',
                'Expand the property description to at least 80 readable characters.'),
            $this->check('media', 'Property media', 10,
                $mediaCount > 0,
                'At least one media attachment is available.',
                'Attach at least one property image or media item.'),
            $this->check('primary_media', 'Primary media', 10,
                $primaryMediaCount > 0,
                'A primary media attachment is selected.',
                'Choose a primary property image.'),
            $this->check('company_dependency', 'Parent company', 10,
                $companyStatus === 'approved',
                'The parent company is approved.',
                'The parent company must be approved first.'),
            $this->check('project_dependency', 'Project dependency', 10,
                !$projectLinked || in_array($projectStatus, ['approved', 'published'], true),
                $projectLinked ? 'The linked project is approved or published.' : 'Standalone property — no project dependency.',
                'The linked project must be approved first.'),
        ];

        $score = 0;
        $missing = [];
        foreach ($checks as $check) {
            if (!empty($check['passed'])) {
                $score += (int) $check['weight'];
            } else {
                $missing[] = $check;
            }
        }
        $score = max(0, min(100, $score));

        $band = self::qualityBand($score);
        $level = $score === 100 ? 'ready' : ($score >= 50 ? 'attention' : 'incomplete');
        $label = (string) $band['label'];

        $optionalFields = [
            'bedrooms', 'bathrooms', 'parking_spaces', 'floor_number',
            'total_floors', 'year_built', 'land_area_value', 'land_area_unit',
            'furnishing_status',
        ];
        $optionalFilled = 0;
        foreach ($optionalFields as $field) {
            $value = $record[$field] ?? null;
            if ($value !== null && trim((string) $value) !== '') {
                $optionalFilled++;
            }
        }

        return [
            'score' => $score,
            'level' => $level,
            'label' => $label,
            'band' => (string) $band['key'],
            'band_label' => (string) $band['label'],
            'band_range' => (string) $band['range'],
            'approval_ready' => $missing === [],
            'checks' => $checks,
            'missing' => $missing,
            'first_missing' => $missing[0] ?? null,
            'listing' => [
                'total' => max(0, (int) ($record['listings_count'] ?? 0)),
                'published' => max(0, (int) ($record['published_listings_count'] ?? 0)),
            ],
            'media' => ['total' => $mediaCount, 'primary' => $primaryMediaCount],
            'enrichment' => ['filled' => $optionalFilled, 'total' => count($optionalFields)],
        ];
    }

    /** @return array<string,mixed> */
    public function evaluatePropertyId(int $propertyId): array
    {
        if ($propertyId < 1) {
            throw new InvalidArgumentException('Property id must be positive.');
        }

        $statement = Database::connection()->prepare(
            'SELECT p.*, c.status AS company_status,
                    pr.status AS project_status,
                    (SELECT COUNT(*) FROM listings li
                     WHERE li.property_id = p.id AND li.deleted_at IS NULL) AS listings_count,
                    (SELECT COUNT(*) FROM listings li
                     WHERE li.property_id = p.id
                       AND li.status = "published" AND li.deleted_at IS NULL) AS published_listings_count,
                    (SELECT COUNT(*) FROM media_attachments ma
                     WHERE ma.entity_type = "property" AND ma.entity_id = p.id) AS media_count,
                    (SELECT COUNT(*) FROM media_attachments ma
                     WHERE ma.entity_type = "property" AND ma.entity_id = p.id
                       AND ma.is_primary = 1) AS primary_media_count
             FROM properties p
             LEFT JOIN companies c ON c.id = p.company_id AND c.deleted_at IS NULL
             LEFT JOIN projects pr ON pr.id = p.project_id AND pr.deleted_at IS NULL
             WHERE p.id = :id AND p.deleted_at IS NULL
             LIMIT 1'
        );
        $statement->execute(['id' => $propertyId]);
        $record = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($record)) {
            throw new InvalidArgumentException('Property was not found.');
        }

        return $this->evaluate($record, []);
    }

    /** @return array{key:string,label:string,range:string} */
    public static function qualityBand(int $score): array
    {
        $score = max(0, min(100, $score));

        if ($score === 100) {
            return ['key' => 'excellent', 'label' => 'Excellent', 'range' => '100%'];
        }
        if ($score >= 80) {
            return ['key' => 'good', 'label' => 'Good', 'range' => '80–99%'];
        }
        if ($score >= 50) {
            return ['key' => 'attention', 'label' => 'Needs attention', 'range' => '50–79%'];
        }

        return ['key' => 'incomplete', 'label' => 'Incomplete', 'range' => 'Below 50%'];
    }

    /** @return array{ready:int,attention:int,excellent:int,good:int,needs_attention:int,incomplete:int} */
    public function summaryForAdmin(): array
    {
        return $this->summary(null);
    }

    /** @return array{ready:int,attention:int,excellent:int,good:int,needs_attention:int,incomplete:int} */
    public function summaryForDeveloper(int $userId): array
    {
        if ($userId < 1) {
            return ['ready' => 0, 'attention' => 0, 'excellent' => 0, 'good' => 0, 'needs_attention' => 0, 'incomplete' => 0];
        }
        return $this->summary($userId);
    }

    /** @return array<string,string> */
    public static function sqlIssueExpressions(
        string $propertyAlias = 'p',
        string $companyAlias = 'c',
        string $projectAlias = 'pr'
    ): array {
        foreach ([$propertyAlias, $companyAlias, $projectAlias] as $alias) {
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $alias)) {
                throw new InvalidArgumentException('Invalid SQL alias for readiness issue expression.');
            }
        }

        $types = implode(', ', array_map(
            static fn (string $type): string => "'" . str_replace("'", "''", $type) . "'",
            DomainRules::PROPERTY_TYPES
        ));
        $descriptionReady = "CHAR_LENGTH(TRIM(REGEXP_REPLACE(REGEXP_REPLACE(COALESCE({$propertyAlias}.description, ''), '<[^>]*>', ' '), '[[:space:]]+', ' '))) >= 80";
        $companyBlocked = "({$companyAlias}.id IS NULL OR {$companyAlias}.status <> 'approved')";
        $projectBlocked = "({$propertyAlias}.project_id IS NOT NULL AND ({$projectAlias}.id IS NULL OR {$projectAlias}.status NOT IN ('approved', 'published')))";

        return [
            'missing_title' => "TRIM(COALESCE({$propertyAlias}.title, '')) = ''",
            'invalid_type' => "LOWER(TRIM(COALESCE({$propertyAlias}.property_type, ''))) NOT IN ({$types})",
            'missing_location' => "COALESCE({$propertyAlias}.location_id, 0) < 1",
            'missing_address' => "TRIM(COALESCE({$propertyAlias}.address_line, '')) = ''",
            'missing_area' => "(COALESCE({$propertyAlias}.area_value, 0) <= 0 OR TRIM(COALESCE({$propertyAlias}.area_unit, '')) = '')",
            'missing_description' => "NOT ({$descriptionReady})",
            'missing_media' => "NOT EXISTS (SELECT 1 FROM media_attachments quality_media WHERE quality_media.entity_type = 'property' AND quality_media.entity_id = {$propertyAlias}.id)",
            'missing_primary_media' => "NOT EXISTS (SELECT 1 FROM media_attachments quality_primary WHERE quality_primary.entity_type = 'property' AND quality_primary.entity_id = {$propertyAlias}.id AND quality_primary.is_primary = 1)",
            'company_dependency' => $companyBlocked,
            'project_dependency' => $projectBlocked,
            'parent_blocked' => "({$companyBlocked} OR {$projectBlocked})",
            'stale_draft' => "({$propertyAlias}.status = 'draft' AND {$propertyAlias}.updated_at < (CURRENT_TIMESTAMP - INTERVAL 30 DAY))",
            'stale_30_59' => "({$propertyAlias}.status = 'draft' AND {$propertyAlias}.updated_at < (CURRENT_TIMESTAMP - INTERVAL 30 DAY) AND {$propertyAlias}.updated_at >= (CURRENT_TIMESTAMP - INTERVAL 60 DAY))",
            'stale_60_89' => "({$propertyAlias}.status = 'draft' AND {$propertyAlias}.updated_at < (CURRENT_TIMESTAMP - INTERVAL 60 DAY) AND {$propertyAlias}.updated_at >= (CURRENT_TIMESTAMP - INTERVAL 90 DAY))",
            'stale_90_plus' => "({$propertyAlias}.status = 'draft' AND {$propertyAlias}.updated_at < (CURRENT_TIMESTAMP - INTERVAL 90 DAY))",
        ];
    }

    /** @return array<string,mixed> */
    public function operationalSummaryForAdmin(): array
    {
        return $this->operationalSummary(null);
    }

    /** @return array<string,mixed> */
    public function operationalSummaryForDeveloper(int $userId): array
    {
        if ($userId < 1) {
            return $this->emptyOperationalSummary();
        }

        return $this->operationalSummary($userId);
    }

    /** @param array<string,mixed> $filters */
    public function csvReportForAdmin(array $filters = []): string
    {
        return $this->csvReport(null, $filters);
    }

    /** @param array<string,mixed> $filters */
    public function csvReportForDeveloper(int $userId, array $filters = []): string
    {
        if ($userId < 1) {
            return "\xEF\xBB\xBF";
        }

        return $this->csvReport($userId, $filters);
    }

    /** @return array<string,mixed> */
    private function operationalSummary(?int $userId): array
    {
        $score = self::sqlScoreExpression('p', 'c', 'pr');
        $issues = self::sqlIssueExpressions('p', 'c', 'pr');
        $scope = $userId === null
            ? ''
            : ' AND EXISTS (SELECT 1 FROM company_members quality_cm WHERE quality_cm.company_id = p.company_id AND quality_cm.user_id = :quality_user_id AND quality_cm.status = "active")';

        $issueColumns = [];
        foreach ($issues as $key => $expression) {
            $issueColumns[] = 'CASE WHEN ' . $expression . ' THEN 1 ELSE 0 END AS issue_' . $key;
        }

        $sql = 'SELECT COUNT(*) AS total, '
            . 'ROUND(COALESCE(AVG(readiness_score), 0), 1) AS average_score, '
            . 'COALESCE(SUM(readiness_score = 100), 0) AS excellent, '
            . 'COALESCE(SUM(readiness_score BETWEEN 80 AND 99), 0) AS good, '
            . 'COALESCE(SUM(readiness_score BETWEEN 50 AND 79), 0) AS needs_attention, '
            . 'COALESCE(SUM(readiness_score < 50), 0) AS incomplete, '
            . 'COALESCE(SUM(issue_missing_media), 0) AS missing_media, '
            . 'COALESCE(SUM(issue_missing_primary_media), 0) AS missing_primary_media, '
            . 'COALESCE(SUM(issue_missing_location), 0) AS missing_location, '
            . 'COALESCE(SUM(issue_missing_description), 0) AS missing_description, '
            . 'COALESCE(SUM(issue_parent_blocked), 0) AS parent_blocked, '
            . 'COALESCE(SUM(issue_stale_draft), 0) AS stale_draft, '
            . 'COALESCE(SUM(issue_stale_30_59), 0) AS stale_30_59, '
            . 'COALESCE(SUM(issue_stale_60_89), 0) AS stale_60_89, '
            . 'COALESCE(SUM(issue_stale_90_plus), 0) AS stale_90_plus, '
            . 'COALESCE(SUM(issue_missing_title), 0) AS issue_missing_title, '
            . 'COALESCE(SUM(issue_invalid_type), 0) AS issue_invalid_type, '
            . 'COALESCE(SUM(issue_missing_address), 0) AS issue_missing_address, '
            . 'COALESCE(SUM(issue_missing_area), 0) AS issue_missing_area, '
            . 'COALESCE(SUM(issue_company_dependency), 0) AS issue_company_dependency, '
            . 'COALESCE(SUM(issue_project_dependency), 0) AS issue_project_dependency '
            . 'FROM ('
            . ' SELECT (' . $score . ') AS readiness_score, ' . implode(', ', $issueColumns)
            . ' FROM properties p'
            . ' LEFT JOIN companies c ON c.id = p.company_id AND c.deleted_at IS NULL'
            . ' LEFT JOIN projects pr ON pr.id = p.project_id AND pr.deleted_at IS NULL'
            . ' WHERE p.deleted_at IS NULL' . $scope
            . ') quality_rows';

        $statement = Database::connection()->prepare($sql);
        $statement->execute($userId === null ? [] : ['quality_user_id' => $userId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC) ?: [];

        $metrics = [
            'total' => (int) ($row['total'] ?? 0),
            'average_score' => (float) ($row['average_score'] ?? 0),
            'excellent' => (int) ($row['excellent'] ?? 0),
            'good' => (int) ($row['good'] ?? 0),
            'needs_attention' => (int) ($row['needs_attention'] ?? 0),
            'incomplete' => (int) ($row['incomplete'] ?? 0),
            'missing_media' => (int) ($row['missing_media'] ?? 0),
            'missing_primary_media' => (int) ($row['missing_primary_media'] ?? 0),
            'missing_location' => (int) ($row['missing_location'] ?? 0),
            'missing_description' => (int) ($row['missing_description'] ?? 0),
            'parent_blocked' => (int) ($row['parent_blocked'] ?? 0),
            'stale_draft' => (int) ($row['stale_draft'] ?? 0),
            'stale_30_59' => (int) ($row['stale_30_59'] ?? 0),
            'stale_60_89' => (int) ($row['stale_60_89'] ?? 0),
            'stale_90_plus' => (int) ($row['stale_90_plus'] ?? 0),
        ];

        $issueLabels = [
            'missing_title' => 'Missing property title',
            'invalid_type' => 'Invalid property type',
            'missing_location' => 'Missing canonical location',
            'missing_address' => 'Missing address',
            'missing_area' => 'Missing area value or unit',
            'missing_description' => 'Description below 80 readable characters',
            'missing_media' => 'No property media',
            'missing_primary_media' => 'No primary property media',
            'company_dependency' => 'Parent company not approved',
            'project_dependency' => 'Linked project not approved',
        ];
        $topIssues = [];
        foreach ($issueLabels as $key => $label) {
            $sourceKey = in_array($key, ['missing_media', 'missing_primary_media', 'missing_location', 'missing_description'], true)
                ? $key
                : 'issue_' . $key;
            $count = (int) ($row[$sourceKey] ?? 0);
            if ($count > 0) {
                $topIssues[] = ['key' => $key, 'label' => $label, 'count' => $count];
            }
        }
        usort($topIssues, static fn (array $a, array $b): int => ($b['count'] <=> $a['count']) ?: strcmp((string) $a['label'], (string) $b['label']));
        $topIssues = array_slice($topIssues, 0, 6);

        $companySql = 'SELECT company_id AS id, company_name AS name, COUNT(*) AS properties, '
            . 'ROUND(COALESCE(AVG(readiness_score), 0), 1) AS average_score, '
            . 'COALESCE(SUM(readiness_score < 100), 0) AS attention, '
            . 'COALESCE(SUM(readiness_score < 50), 0) AS incomplete '
            . 'FROM ('
            . ' SELECT c.id AS company_id, c.name AS company_name, (' . $score . ') AS readiness_score'
            . ' FROM properties p'
            . ' INNER JOIN companies c ON c.id = p.company_id AND c.deleted_at IS NULL'
            . ' LEFT JOIN projects pr ON pr.id = p.project_id AND pr.deleted_at IS NULL'
            . ' WHERE p.deleted_at IS NULL' . $scope
            . ') company_quality '
            . 'GROUP BY company_id, company_name '
            . 'ORDER BY incomplete DESC, attention DESC, average_score ASC, properties DESC, company_name ASC '
            . 'LIMIT 6';
        $companyStatement = Database::connection()->prepare($companySql);
        $companyStatement->execute($userId === null ? [] : ['quality_user_id' => $userId]);
        $companies = array_map(static fn (array $item): array => [
            'id' => (int) ($item['id'] ?? 0),
            'name' => (string) ($item['name'] ?? ''),
            'properties' => (int) ($item['properties'] ?? 0),
            'average_score' => (float) ($item['average_score'] ?? 0),
            'attention' => (int) ($item['attention'] ?? 0),
            'incomplete' => (int) ($item['incomplete'] ?? 0),
        ], $companyStatement->fetchAll(PDO::FETCH_ASSOC));

        $firstIssueCase = 'CASE '
            . 'WHEN ' . $issues['missing_title'] . ' THEN "title" '
            . 'WHEN ' . $issues['invalid_type'] . ' THEN "property_type" '
            . 'WHEN ' . $issues['missing_location'] . ' THEN "location" '
            . 'WHEN ' . $issues['missing_address'] . ' THEN "address" '
            . 'WHEN ' . $issues['missing_area'] . ' THEN "area" '
            . 'WHEN ' . $issues['missing_description'] . ' THEN "description" '
            . 'WHEN ' . $issues['missing_media'] . ' THEN "media" '
            . 'WHEN ' . $issues['missing_primary_media'] . ' THEN "primary_media" '
            . 'WHEN ' . $issues['company_dependency'] . ' THEN "company_dependency" '
            . 'WHEN ' . $issues['project_dependency'] . ' THEN "project_dependency" '
            . 'ELSE "" END';
        $queueSql = 'SELECT * FROM ('
            . ' SELECT p.id, p.reference_code, p.title, p.status, p.updated_at, c.name AS company_name, '
            . '(' . $score . ') AS readiness_score, ' . $firstIssueCase . ' AS first_issue'
            . ' FROM properties p'
            . ' LEFT JOIN companies c ON c.id = p.company_id AND c.deleted_at IS NULL'
            . ' LEFT JOIN projects pr ON pr.id = p.project_id AND pr.deleted_at IS NULL'
            . ' WHERE p.deleted_at IS NULL' . $scope
            . ') property_quality_queue '
            . 'WHERE readiness_score < 100 '
            . 'ORDER BY readiness_score ASC, updated_at ASC, id ASC LIMIT 6';
        $queueStatement = Database::connection()->prepare($queueSql);
        $queueStatement->execute($userId === null ? [] : ['quality_user_id' => $userId]);
        $actionQueue = array_map(static fn (array $item): array => [
            'id' => (int) ($item['id'] ?? 0),
            'reference_code' => (string) ($item['reference_code'] ?? ''),
            'title' => (string) ($item['title'] ?? ''),
            'status' => (string) ($item['status'] ?? ''),
            'company_name' => (string) ($item['company_name'] ?? ''),
            'readiness_score' => (int) ($item['readiness_score'] ?? 0),
            'first_issue' => (string) ($item['first_issue'] ?? ''),
            'updated_at' => (string) ($item['updated_at'] ?? ''),
        ], $queueStatement->fetchAll(PDO::FETCH_ASSOC));

        return [
            'metrics' => $metrics,
            'top_issues' => $topIssues,
            'companies' => $companies,
            'action_queue' => $actionQueue,
            'stale_days' => 30,
            'stale_buckets' => [
                '30_59' => (int) ($metrics['stale_30_59'] ?? 0),
                '60_89' => (int) ($metrics['stale_60_89'] ?? 0),
                '90_plus' => (int) ($metrics['stale_90_plus'] ?? 0),
            ],
            'health' => [
                'ready' => (int) ($metrics['excellent'] ?? 0),
                'workload' => (int) (($metrics['needs_attention'] ?? 0) + ($metrics['incomplete'] ?? 0)),
                'total' => (int) ($metrics['total'] ?? 0),
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function emptyOperationalSummary(): array
    {
        return [
            'metrics' => [
                'total' => 0, 'average_score' => 0.0, 'excellent' => 0, 'good' => 0,
                'needs_attention' => 0, 'incomplete' => 0, 'missing_media' => 0,
                'missing_primary_media' => 0, 'missing_location' => 0,
                'missing_description' => 0, 'parent_blocked' => 0, 'stale_draft' => 0,
                'stale_30_59' => 0, 'stale_60_89' => 0, 'stale_90_plus' => 0,
            ],
            'top_issues' => [],
            'companies' => [],
            'action_queue' => [],
            'stale_days' => 30,
            'stale_buckets' => ['30_59' => 0, '60_89' => 0, '90_plus' => 0],
            'health' => ['ready' => 0, 'workload' => 0, 'total' => 0],
        ];
    }

    private function csvCell(string $value): string
    {
        $value = str_replace("\0", '', $value);
        if ($value !== '' && preg_match('/^[=+\-@]/u', $value) === 1) {
            return "'" . $value;
        }

        return $value;
    }

    /** @param array<string,mixed> $filters */
    private function csvReport(?int $userId, array $filters = []): string
    {
        $score = self::sqlScoreExpression('p', 'c', 'pr');
        $issues = self::sqlIssueExpressions('p', 'c', 'pr');
        $where = ['p.deleted_at IS NULL'];
        $params = [];
        if ($userId !== null) {
            $where[] = 'EXISTS (SELECT 1 FROM company_members csv_cm WHERE csv_cm.company_id = p.company_id AND csv_cm.user_id = :csv_user_id AND csv_cm.status = "active")';
            $params['csv_user_id'] = $userId;
        }

        $q = mb_substr(trim((string) ($filters['q'] ?? '')), 0, 120);
        if ($q !== '') {
            $where[] = 'CONCAT_WS(" ", p.title, p.reference_code, c.name, pr.name) LIKE :csv_q';
            $params['csv_q'] = '%' . $q . '%';
        }
        $status = mb_substr(trim((string) ($filters['status'] ?? '')), 0, 32);
        if ($status !== '') {
            $where[] = 'p.status = :csv_status';
            $params['csv_status'] = $status;
        }
        $type = mb_substr(trim((string) ($filters['type'] ?? '')), 0, 40);
        if ($type !== '') {
            $where[] = 'p.property_type = :csv_type';
            $params['csv_type'] = $type;
        }
        $location = (int) ($filters['location'] ?? 0);
        if ($location > 0) {
            $where[] = 'p.location_id = :csv_location';
            $params['csv_location'] = $location;
        }
        $readiness = trim((string) ($filters['readiness'] ?? ''));
        if ($readiness === 'ready') {
            $where[] = '(' . $score . ') = 100';
        } elseif ($readiness === 'attention') {
            $where[] = '(' . $score . ') < 100';
        }
        $quality = trim((string) ($filters['quality'] ?? ''));
        $qualityMap = [
            'missing_media' => 'missing_media',
            'missing_primary_media' => 'missing_primary_media',
            'missing_location' => 'missing_location',
            'missing_description' => 'missing_description',
            'parent_blocked' => 'parent_blocked',
            'stale_draft' => 'stale_draft',
            'stale_30_59' => 'stale_30_59',
            'stale_60_89' => 'stale_60_89',
            'stale_90_plus' => 'stale_90_plus',
        ];
        if (isset($qualityMap[$quality])) {
            $where[] = '(' . $issues[$qualityMap[$quality]] . ')';
        }

        $issueColumns = [];
        foreach ($issues as $key => $expression) {
            if ($key === 'parent_blocked' || str_starts_with($key, 'stale_')) {
                continue;
            }
            $issueColumns[] = 'CASE WHEN ' . $expression . ' THEN 1 ELSE 0 END AS issue_' . $key;
        }

        $staleAgeBucket = 'CASE '
            . 'WHEN ' . $issues['stale_90_plus'] . ' THEN "90+ days" '
            . 'WHEN ' . $issues['stale_60_89'] . ' THEN "60-89 days" '
            . 'WHEN ' . $issues['stale_30_59'] . ' THEN "30-59 days" '
            . 'ELSE "" END';
        $sql = 'SELECT p.id, p.reference_code, p.title, c.name AS company_name, p.status, p.updated_at, '
            . '(' . $score . ') AS readiness_score, '
            . 'CASE WHEN ' . $issues['stale_draft'] . ' THEN 1 ELSE 0 END AS stale_draft, '
            . $staleAgeBucket . ' AS stale_age_bucket, '
            . implode(', ', $issueColumns)
            . ' FROM properties p'
            . ' LEFT JOIN companies c ON c.id = p.company_id AND c.deleted_at IS NULL'
            . ' LEFT JOIN projects pr ON pr.id = p.project_id AND pr.deleted_at IS NULL'
            . ' WHERE ' . implode(' AND ', $where)
            . ' ORDER BY c.name ASC, p.title ASC, p.id ASC';
        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);

        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            throw new InvalidArgumentException('Unable to prepare property readiness CSV report.');
        }

        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, [
            'ID', 'Reference', 'Property', 'Company', 'Status', 'Readiness Score', 'Quality Band',
            'Missing Requirements', 'Stale Draft 30+ Days', 'Stale Age Bucket', 'Updated At',
        ], ',', '"', '');

        $labels = [
            'missing_title' => 'Property title', 'invalid_type' => 'Property type',
            'missing_location' => 'Location', 'missing_address' => 'Address', 'missing_area' => 'Area',
            'missing_description' => 'Description', 'missing_media' => 'Property media',
            'missing_primary_media' => 'Primary media', 'company_dependency' => 'Parent company',
            'project_dependency' => 'Project dependency',
        ];

        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $missing = [];
            foreach ($labels as $key => $label) {
                if (!empty($row['issue_' . $key])) {
                    $missing[] = $label;
                }
            }
            $scoreValue = max(0, min(100, (int) ($row['readiness_score'] ?? 0)));
            $band = self::qualityBand($scoreValue);
            fputcsv($stream, [
                (int) ($row['id'] ?? 0),
                $this->csvCell((string) ($row['reference_code'] ?? '')),
                $this->csvCell((string) ($row['title'] ?? '')),
                $this->csvCell((string) ($row['company_name'] ?? '')),
                $this->csvCell((string) ($row['status'] ?? '')),
                $scoreValue,
                (string) $band['label'],
                implode('; ', $missing),
                !empty($row['stale_draft']) ? 'Yes' : 'No',
                $this->csvCell((string) ($row['stale_age_bucket'] ?? '')),
                $this->csvCell((string) ($row['updated_at'] ?? '')),
            ], ',', '"', '');
        }

        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);
        return is_string($content) ? $content : '';
    }

    public static function sqlScoreExpression(
        string $propertyAlias = 'p',
        string $companyAlias = 'c',
        string $projectAlias = 'pr'
    ): string {
        foreach ([$propertyAlias, $companyAlias, $projectAlias] as $alias) {
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $alias)) {
                throw new InvalidArgumentException('Invalid SQL alias for readiness expression.');
            }
        }

        $types = implode(', ', array_map(
            static fn (string $type): string => "'" . str_replace("'", "''", $type) . "'",
            DomainRules::PROPERTY_TYPES
        ));

        $descriptionReady = "CHAR_LENGTH(TRIM(REGEXP_REPLACE(REGEXP_REPLACE(COALESCE({$propertyAlias}.description, ''), '<[^>]*>', ' '), '[[:space:]]+', ' '))) >= 80";

        return '('
            . "CASE WHEN TRIM(COALESCE({$propertyAlias}.title, '')) <> '' THEN 8 ELSE 0 END + "
            . "CASE WHEN LOWER(TRIM(COALESCE({$propertyAlias}.property_type, ''))) IN ({$types}) THEN 7 ELSE 0 END + "
            . "CASE WHEN COALESCE({$propertyAlias}.location_id, 0) > 0 THEN 10 ELSE 0 END + "
            . "CASE WHEN TRIM(COALESCE({$propertyAlias}.address_line, '')) <> '' THEN 10 ELSE 0 END + "
            . "CASE WHEN COALESCE({$propertyAlias}.area_value, 0) > 0 AND TRIM(COALESCE({$propertyAlias}.area_unit, '')) <> '' THEN 10 ELSE 0 END + "
            . "CASE WHEN {$descriptionReady} THEN 15 ELSE 0 END + "
            . "CASE WHEN EXISTS (SELECT 1 FROM media_attachments readiness_media WHERE readiness_media.entity_type = 'property' AND readiness_media.entity_id = {$propertyAlias}.id) THEN 10 ELSE 0 END + "
            . "CASE WHEN EXISTS (SELECT 1 FROM media_attachments readiness_primary WHERE readiness_primary.entity_type = 'property' AND readiness_primary.entity_id = {$propertyAlias}.id AND readiness_primary.is_primary = 1) THEN 10 ELSE 0 END + "
            . "CASE WHEN {$companyAlias}.status = 'approved' THEN 10 ELSE 0 END + "
            . "CASE WHEN {$propertyAlias}.project_id IS NULL OR {$projectAlias}.status IN ('approved', 'published') THEN 10 ELSE 0 END"
            . ')';
    }

    /** @return array{ready:int,attention:int,excellent:int,good:int,needs_attention:int,incomplete:int} */
    private function summary(?int $userId): array
    {
        $score = self::sqlScoreExpression('p', 'c', 'pr');
        $membershipJoin = $userId === null
            ? ''
            : ' INNER JOIN company_members readiness_cm ON readiness_cm.company_id = p.company_id AND readiness_cm.user_id = :user_id AND readiness_cm.status = "active"';

        $sql = 'SELECT '
            . 'COALESCE(SUM(readiness_score = 100), 0) AS ready, '
            . 'COALESCE(SUM(readiness_score < 100), 0) AS attention, '
            . 'COALESCE(SUM(readiness_score = 100), 0) AS excellent, '
            . 'COALESCE(SUM(readiness_score BETWEEN 80 AND 99), 0) AS good, '
            . 'COALESCE(SUM(readiness_score BETWEEN 50 AND 79), 0) AS needs_attention, '
            . 'COALESCE(SUM(readiness_score < 50), 0) AS incomplete '
            . 'FROM ('
            . ' SELECT (' . $score . ') AS readiness_score'
            . ' FROM properties p'
            . ' LEFT JOIN companies c ON c.id = p.company_id AND c.deleted_at IS NULL'
            . ' LEFT JOIN projects pr ON pr.id = p.project_id AND pr.deleted_at IS NULL'
            . $membershipJoin
            . ' WHERE p.deleted_at IS NULL'
            . ') property_readiness_summary';

        $statement = Database::connection()->prepare($sql);
        $statement->execute($userId === null ? [] : ['user_id' => $userId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'ready' => (int) ($row['ready'] ?? 0),
            'attention' => (int) ($row['attention'] ?? 0),
            'excellent' => (int) ($row['excellent'] ?? 0),
            'good' => (int) ($row['good'] ?? 0),
            'needs_attention' => (int) ($row['needs_attention'] ?? 0),
            'incomplete' => (int) ($row['incomplete'] ?? 0),
        ];
    }

    /** @return array<string,mixed> */
    private function check(
        string $key,
        string $label,
        int $weight,
        bool $passed,
        string $passedMessage,
        string $missingMessage
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'weight' => $weight,
            'passed' => $passed,
            'message' => $passed ? $passedMessage : $missingMessage,
        ];
    }
}
