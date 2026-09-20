<?php

declare(strict_types=1);

namespace Barama\Services;

use DomainException;
use InvalidArgumentException;
use Throwable;

final class BulkModerationService
{
    private const RESOURCES = ['company', 'project', 'property', 'listing'];
    private const MAX_RECORDS = 50;

    /**
     * @param array<int, mixed> $records
     * @return array{
     *     processed:int,
     *     succeeded:int,
     *     failed:int,
     *     failures:array<int,array{record:string,message:string}>
     * }
     */
    public function moderate(array $records, string $decision, ?string $note = null): array
    {
        $auth = new AuthService();

        if (!$auth->hasRole('administrator') || !$auth->can('dashboard.access')) {
            throw new DomainException('Administrator moderation access is required.');
        }

        if (!in_array($decision, ['approve', 'reject'], true)) {
            throw new InvalidArgumentException('Bulk moderation decision must be approve or reject.');
        }

        $normalized = $this->normalizeRecords($records);

        if ($normalized === []) {
            throw new InvalidArgumentException('Select at least one pending record.');
        }

        if (count($normalized) > self::MAX_RECORDS) {
            throw new InvalidArgumentException(
                'Bulk moderation is limited to ' . self::MAX_RECORDS . ' records per request.'
            );
        }

        if ($decision === 'approve') {
            $order = array_flip(self::RESOURCES);
            usort(
                $normalized,
                static fn (array $a, array $b): int =>
                    ($order[$a['resource']] ?? 99) <=> ($order[$b['resource']] ?? 99)
            );
        }

        $workflow = new DomainWorkflowService();
        $succeeded = 0;
        $failures = [];

        foreach ($normalized as $item) {
            $target = $decision === 'reject'
                ? 'rejected'
                : ($item['resource'] === 'listing' ? 'published' : 'approved');

            try {
                $workflow->transition(
                    $item['resource'],
                    $item['id'],
                    $target,
                    $note
                );
                $succeeded++;
            } catch (Throwable $exception) {
                $failures[] = [
                    'record' => $item['resource'] . ':' . $item['id'],
                    'message' => $exception->getMessage(),
                ];
            }
        }

        return [
            'processed' => count($normalized),
            'succeeded' => $succeeded,
            'failed' => count($failures),
            'failures' => $failures,
        ];
    }

    /**
     * @param array<int, mixed> $records
     * @return array<int,array{resource:string,id:int}>
     */
    private function normalizeRecords(array $records): array
    {
        $normalized = [];
        $seen = [];

        foreach ($records as $record) {
            if (!is_string($record) || preg_match('/^([a-z]+):([1-9][0-9]*)$/', $record, $matches) !== 1) {
                throw new InvalidArgumentException('A selected moderation record is invalid.');
            }

            $resource = $matches[1];
            $id = (int) $matches[2];

            if (!in_array($resource, self::RESOURCES, true)) {
                throw new InvalidArgumentException('A selected moderation resource is unsupported.');
            }

            $key = $resource . ':' . $id;

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $normalized[] = ['resource' => $resource, 'id' => $id];
        }

        return $normalized;
    }
}
