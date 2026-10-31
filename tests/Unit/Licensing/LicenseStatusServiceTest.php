<?php

declare(strict_types=1);

namespace Tests\Unit\Licensing;

use App\Services\Licensing\LicenseStatusService;
use App\Services\Licensing\PublisherLicenseClient;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

final class LicenseStatusServiceTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir().'/barama-license-'.bin2hex(random_bytes(8));
        mkdir($this->root.'/storage/app', 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->root.'/storage/app/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->root.'/storage/app');
        @rmdir($this->root.'/storage');
        @rmdir($this->root);
        parent::tearDown();
    }

    private function recorded(string $domain = 'example.com'): array
    {
        $id = PublisherLicenseClient::installationId($this->root);
        $receipt = [
            'product_slug' => 'barama-real-estate',
            'domain' => $domain,
            'installation_id' => $id,
            'status' => 'active',
            'activation_token' => str_repeat('a', 64),
            'source' => 'envato',
            'validated_at' => '2024-01-01T00:00:00+00:00',
        ];
        PublisherLicenseClient::saveReceipt($this->root, $receipt);
        return $receipt;
    }

    public function test_summary_distinguishes_saved_receipt_from_live_validation(): void
    {
        $status = new LicenseStatusService();
        $empty = $status->summary($this->root, 'example.com');
        $this->assertFalse($empty['activated']);
        $this->assertSame('not_activated', $empty['status']);

        $receipt = $this->recorded();
        $summary = $status->summary($this->root, 'www.example.com');
        $this->assertTrue($summary['activated']);
        $this->assertTrue($summary['domain_matches']);
        $this->assertSame('last_recorded_active', $summary['status']);
        $this->assertSame($receipt['validated_at'], $summary['validated_at']);
        $this->assertArrayNotHasKey('activation_token', $summary);
    }

    public function test_revalidation_checks_same_product_domain_installation_and_saves_only_real_reply(): void
    {
        $old = $this->recorded();
        $status = new LicenseStatusService();
        $verified = $status->revalidate(
            $this->root, 'www.example.com',
            static function (string $path, array $payload) use ($old): array {
                self::assertSame('/license/v1/validate', $path);
                self::assertSame($old['activation_token'], $payload['activation_token']);
                self::assertSame($old['installation_id'], $payload['installation_id']);
                return [
                    'success' => true,
                    'license' => [
                        'product_slug' => 'barama-real-estate',
                        'domain' => 'example.com',
                        'installation_id' => $old['installation_id'],
                        'status' => 'active',
                        'source' => 'envato',
                    ],
                ];
            },
        );
        $this->assertSame('active', $verified['status']);
        $this->assertNotSame('2024-01-01T00:00:00+00:00', $verified['validated_at']);
        $this->assertSame($verified['validated_at'], PublisherLicenseClient::receipt($this->root)['validated_at']);
        $this->assertSame('envato', $status->summary($this->root, 'example.com')['source']);
    }

    public function test_revoked_or_invalid_remote_response_never_updates_last_recorded_receipt(): void
    {
        $old = $this->recorded();
        try {
            (new LicenseStatusService())->revalidate(
                $this->root, 'example.com',
                static fn (): array => [
                    'success' => true,
                    'license' => [
                        'product_slug' => 'barama-real-estate',
                        'domain' => 'example.com',
                        'status' => 'revoked',
                    ],
                ],
            );
            self::fail('Revoked license must not count as successful verification.');
        } catch (RuntimeException $exception) {
            self::assertNotSame('', $exception->getMessage());
        }
        $this->assertSame($old, PublisherLicenseClient::receipt($this->root));
    }

    public function test_domain_change_and_installation_identity_mismatch_are_blocked(): void
    {
        $this->recorded();
        $status = new LicenseStatusService();
        try {
            $status->revalidate($this->root, 'other.example.com', static fn () => []);
            self::fail('Domain mismatch must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('license', $exception->errors());
        }
        file_put_contents($this->root.'/storage/app/license-installation-id', str_repeat('f', 32)."\n");
        try {
            $status->revalidate($this->root, 'example.com', static fn () => []);
            self::fail('Installation mismatch must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('license', $exception->errors());
        }
    }

    public function test_recheck_without_saved_activation_is_rejected_without_network(): void
    {
        try {
            (new LicenseStatusService())->revalidate($this->root, 'example.com', static fn () => []);
            self::fail('An unactivated installation must not be validated.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('license', $exception->errors());
        }
    }
}
