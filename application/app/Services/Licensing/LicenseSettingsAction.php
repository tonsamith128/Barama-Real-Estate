<?php

declare(strict_types=1);

namespace Barama\Services\Licensing;

/**
 * Thin form action orchestration. The application's existing router MUST
 * authorize the administrator and validate the CSRF token before invoking.
 * Domain and installation identifier are injected from trusted server settings.
 */
final class LicenseSettingsAction
{
    public function __construct(
        private readonly CustomerLicenseManager $manager,
        private readonly string $canonicalDomain,
        private readonly string $installationId,
    ) {}

    public function run(string $action, array $form): array
    {
        if ($action === 'activate') {
            $key = $form['license_key'] ?? null;
            if (!is_string($key) || strlen($key) !== 43) {
                throw new LicenseClientException('INVALID_LICENSE_KEY');
            }
            return $this->manager->activate($key, $this->canonicalDomain, $this->installationId);
        }
        if ($action === 'verify') return $this->manager->verify();
        if ($action === 'deactivate') {
            $this->manager->deactivate();
            return ['deactivated' => true];
        }
        throw new LicenseClientException('LICENSE_ACTION_NOT_FOUND', 404);
    }

    public function status(): array
    {
        return $this->manager->status();
    }
}
