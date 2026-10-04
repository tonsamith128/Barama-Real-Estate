<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Core\Database;
use Throwable;

/**
 * Produces the intentionally coarse public service-status snapshot.
 *
 * This service must never expose infrastructure, credentials, exception
 * messages, database names, hostnames, versions, paths, or other diagnostic
 * details to public visitors.
 */
final class PublicSystemHealthService
{
    /**
     * @return array{
     *     overall: string,
     *     label: string,
     *     message: string,
     *     services: list<array{name:string,description:string,status:string,label:string}>
     * }
     */
    public function snapshot(): array
    {
        $dataServicesAvailable = $this->dataServicesAvailable();

        $services = [
            $this->service(
                'Website',
                'Public pages and navigation',
                true
            ),
            $this->service(
                'Property discovery',
                'Property browsing and search',
                $dataServicesAvailable
            ),
            $this->service(
                'Project discovery',
                'Development and project browsing',
                $dataServicesAvailable
            ),
            $this->service(
                'Company directory',
                'Approved company discovery',
                $dataServicesAvailable
            ),
            $this->service(
                'Account services',
                'Sign-in and customer account access',
                $dataServicesAvailable
            ),
        ];

        $degraded = false;

        foreach ($services as $service) {
            if ($service['status'] !== 'operational') {
                $degraded = true;
                break;
            }
        }

        return [
            'overall' => $degraded ? 'degraded' : 'operational',
            'label' => $degraded
                ? 'Some services are experiencing issues'
                : 'All systems operational',
            'message' => $degraded
                ? 'Some customer-facing services may be temporarily unavailable. Please try again shortly.'
                : 'Barama customer-facing services are available and operating normally.',
            'services' => $services,
        ];
    }

    /**
     * @return array{name:string,description:string,status:string,label:string}
     */
    private function service(
        string $name,
        string $description,
        bool $available
    ): array {
        return [
            'name' => $name,
            'description' => $description,
            'status' => $available ? 'operational' : 'degraded',
            'label' => $available ? 'Operational' : 'Service issue',
        ];
    }

    private function dataServicesAvailable(): bool
    {
        try {
            $statement = Database::connection()->query('SELECT 1');

            return $statement !== false
                && $statement->fetchColumn() !== false;
        } catch (Throwable) {
            return false;
        }
    }
}
