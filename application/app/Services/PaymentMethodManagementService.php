<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Repositories\PaymentMethodRepository;
use DomainException;
use InvalidArgumentException;

final class PaymentMethodManagementService
{
    /* BARAMA_PAYMENT_METHODS_V120 */

    public function setStatus(int $id, string $status): void
    {
        $actor = $this->administrator();
        $repository = new PaymentMethodRepository();
        $method = $repository->find($id);

        if ($method === null) {
            throw new InvalidArgumentException('Payment method was not found.');
        }

        $status = strtolower(trim($status));

        if (!in_array($status, ['active', 'inactive'], true)) {
            throw new InvalidArgumentException('Payment method status is invalid.');
        }

        $current = (string) ($method['status'] ?? 'inactive');

        if ($status === $current) {
            return;
        }

        if ($status === 'active' && (int) ($method['is_configured'] ?? 0) !== 1) {
            throw new DomainException('Configure this payment method before turning it ON.');
        }

        $repository->setStatus($id, $status, (int) $actor['id']);

        (new SecurityAuditService())->record(
            'admin.billing_payment_method_status_changed',
            (int) $actor['id'],
            null,
            [
                'payment_method_id' => $id,
                'code' => (string) ($method['code'] ?? ''),
                'from_status' => $current,
                'to_status' => $status,
            ]
        );
    }

    /** @param array<string,mixed> $input */
    public function configure(int $id, array $input, array $files = []): void
    {
        $actor = $this->administrator();
        $repository = new PaymentMethodRepository();
        $method = $repository->find($id);

        if ($method === null) {
            throw new InvalidArgumentException('Payment method was not found.');
        }

        $code = (string) ($method['code'] ?? '');
        $public = is_array($method['public_config'] ?? null) ? $method['public_config'] : [];
        $box = new PaymentMethodSecretBox();
        $secrets = $box->decrypt(
            is_string($method['secret_config_ciphertext'] ?? null)
                ? (string) $method['secret_config_ciphertext']
                : null
        );

        [$public, $secrets, $configured] = match ($code) {
            'paypal' => $this->paypal($input, $public, $secrets, (string) ($method['status'] ?? 'inactive')),
            'stripe' => $this->stripe($input, $public, $secrets, (string) ($method['status'] ?? 'inactive')),
            'offline' => $this->offline($input, $files, $public),
            'bank_transfer' => $this->bankTransfer($input, $files, $public),
            'manual' => $this->manual($input, $files, $public),
            default => ((string) ($method['method_type'] ?? '')) === 'manual'
                ? $this->manual($input, $files, $public)
                : throw new DomainException('Unsupported payment method configuration.'),
        };

        $ciphertext = $secrets !== []
            ? $box->encrypt($secrets)
            : (
                is_string($method['secret_config_ciphertext'] ?? null)
                && trim((string) $method['secret_config_ciphertext']) !== ''
                    ? (string) $method['secret_config_ciphertext']
                    : null
            );

        $repository->updateConfiguration(
            $id,
            $public,
            $ciphertext,
            $configured,
            (int) $actor['id']
        );

        (new SecurityAuditService())->record(
            'admin.billing_payment_method_configured',
            (int) $actor['id'],
            null,
            [
                'payment_method_id' => $id,
                'code' => $code,
                'configured' => $configured,
                'has_encrypted_secrets' => $secrets !== [],
            ]
        );
    }

    /** @return array{0:array<string,mixed>,1:array<string,string>,2:bool} */
    private function paypal(array $input, array $public, array $secrets, string $status): array
    {
        $environment = strtolower(trim((string) ($input['environment'] ?? 'sandbox')));

        if (!in_array($environment, ['sandbox', 'live'], true)) {
            throw new InvalidArgumentException('PayPal environment must be sandbox or live.');
        }

        if ($status === 'active' && $environment !== (string) ($public['environment'] ?? 'sandbox')) {
            throw new DomainException('Turn PayPal OFF before changing its environment.');
        }

        $secrets = $this->mergeSecrets($secrets, $input, ['client_id', 'client_secret', 'webhook_id']);
        $configured = trim((string) ($secrets['client_id'] ?? '')) !== ''
            && trim((string) ($secrets['client_secret'] ?? '')) !== '';

        return [[
            'environment' => $environment,
            'currency' => $this->currency($input['currency'] ?? 'USD'),
        ], $secrets, $configured];
    }

    /** @return array{0:array<string,mixed>,1:array<string,string>,2:bool} */
    private function stripe(array $input, array $public, array $secrets, string $status): array
    {
        $environment = strtolower(trim((string) ($input['environment'] ?? 'test')));

        if (!in_array($environment, ['test', 'live'], true)) {
            throw new InvalidArgumentException('Stripe environment must be test or live.');
        }

        if ($status === 'active' && $environment !== (string) ($public['environment'] ?? 'test')) {
            throw new DomainException('Turn Stripe OFF before changing its environment.');
        }

        $publishable = $this->text($input['publishable_key'] ?? '', 'Stripe publishable key', 1, 255);
        $secrets = $this->mergeSecrets($secrets, $input, ['secret_key', 'webhook_secret']);
        $configured = trim((string) ($secrets['secret_key'] ?? '')) !== '';

        return [[
            'environment' => $environment,
            'currency' => $this->currency($input['currency'] ?? 'USD'),
            'publishable_key' => $publishable,
        ], $secrets, $configured];
    }

    /** @return array{0:array<string,mixed>,1:array<string,string>,2:bool} */
    private function offline(array $input, array $files, array $existing): array
    {
        $base = $this->manualProfile($input, $files, $existing, 'Offline Payment');
        return [[...$base,
            'display_title' => $this->text($input['display_title'] ?? ($existing['display_title'] ?? 'Offline Payment'), 'Offline payment title', 2, 120),
            'processing_days' => $this->integer($input['processing_days'] ?? ($existing['processing_days'] ?? 1), 'Processing days', 0, 30),
        ], [], true];
    }

    /** @return array{0:array<string,mixed>,1:array<string,string>,2:bool} */
    private function bankTransfer(array $input, array $files, array $existing): array
    {
        $base = $this->manualProfile($input, $files, $existing, 'Bank Transfer');
        return [[...$base,
            'bank_name' => $this->text($input['bank_name'] ?? ($existing['bank_name'] ?? ''), 'Bank name', 2, 160),
            'account_name' => $base['recipient_name'],
            'swift_code' => $this->text($input['swift_code'] ?? ($existing['swift_code'] ?? ''), 'SWIFT / BIC', 0, 32),
            'branch' => $this->text($input['branch'] ?? ($existing['branch'] ?? ''), 'Bank branch', 0, 160),
        ], [], true];
    }

    /** @return array{0:array<string,mixed>,1:array<string,string>,2:bool} */
    private function manual(array $input, array $files, array $existing): array
    {
        $base = $this->manualProfile($input, $files, $existing, (string) ($existing['display_title'] ?? 'Manual Payment'));
        return [[...$base,
            'display_title' => $this->text($input['display_title'] ?? ($existing['display_title'] ?? 'Manual Payment'), 'Manual payment title', 2, 120),
            'processing_days' => $this->integer($input['processing_days'] ?? ($existing['processing_days'] ?? 1), 'Processing days', 0, 30),
        ], [], true];
    }

    /** @param array<string,mixed> $input @param array<string,mixed> $files */
    public function createManual(array $input, array $files = []): int
    {
        $actor = $this->administrator();
        $repository = new PaymentMethodRepository();
        $name = $this->text($input['name'] ?? $input['display_title'] ?? '', 'Payment method name', 2, 160);
        $code = $this->normalizeCode((string) ($input['code'] ?? $name));
        if ($repository->codeExists($code)) {
            throw new InvalidArgumentException('This payment method code is already in use.');
        }
        $config = $this->manualProfile($input, $files, [], $name);
        $config['display_title'] = $name;
        $config['processing_days'] = $this->integer($input['processing_days'] ?? 1, 'Processing days', 0, 30);
        $id = $repository->createManual([
            'code' => $code,
            'name' => $name,
            'sort_order' => $repository->nextSortOrder(),
            'public_config' => $config,
            'actor_id' => (int) $actor['id'],
        ]);
        (new SecurityAuditService())->record(
            'admin.billing_manual_payment_method_created',
            (int) $actor['id'],
            null,
            ['payment_method_id' => $id, 'code' => $code]
        );
        return $id;
    }

    /** @return array<string,mixed> */
    private function manualProfile(array $input, array $files, array $existing, string $defaultTitle): array
    {
        $recipient = $this->text(
            $input['recipient_name'] ?? $input['account_name'] ?? ($existing['recipient_name'] ?? $existing['account_name'] ?? ''),
            'Recipient name', 2, 160
        );
        $accountNumber = $this->text($input['account_number'] ?? ($existing['account_number'] ?? ''), 'Account number', 1, 160);
        $termsRequired = $this->boolean($input['terms_required'] ?? ($existing['terms_required'] ?? null));
        $termsTitle = $this->text($input['terms_title'] ?? ($existing['terms_title'] ?? 'Payment transfer terms and conditions'), 'Terms title', $termsRequired ? 2 : 0, 160);
        $termsContent = $this->text($input['terms_content'] ?? ($existing['terms_content'] ?? ''), 'Payment transfer terms', $termsRequired ? 1 : 0, 10000);
        $qrUrl = is_string($existing['qr_code_url'] ?? null) ? (string) $existing['qr_code_url'] : null;
        $asset = new PaymentMethodAssetService();
        if ($this->boolean($input['remove_qr_code'] ?? null)) {
            $asset->removeQr($qrUrl);
            $qrUrl = null;
        }
        if (isset($files['qr_code']) && is_array($files['qr_code'])) {
            $qrUrl = $asset->storeQr($files['qr_code'], $qrUrl);
        }
        return [
            'display_title' => $this->text($input['display_title'] ?? ($existing['display_title'] ?? $defaultTitle), 'Payment display title', 2, 120),
            'recipient_name' => $recipient,
            'account_name' => $recipient,
            'account_number' => $accountNumber,
            'currency' => $this->currency($input['currency'] ?? ($existing['currency'] ?? 'USD')),
            'qr_code_url' => $qrUrl,
            'receipt_required' => $this->boolean($input['receipt_required'] ?? ($existing['receipt_required'] ?? null)),
            'instructions_before' => $this->text($input['instructions_before'] ?? ($existing['instructions_before'] ?? $existing['instructions'] ?? ''), 'Instructions before payment', 0, 5000),
            'instructions_after' => $this->text($input['instructions_after'] ?? ($existing['instructions_after'] ?? ''), 'Instructions after payment', 0, 5000),
            'instructions' => $this->text($input['instructions_before'] ?? ($existing['instructions_before'] ?? $existing['instructions'] ?? ''), 'Payment instructions', 0, 5000),
            'terms_required' => $termsRequired,
            'terms_title' => $termsTitle,
            'terms_content' => $termsContent,
            'approval_required' => $this->boolean($input['approval_required'] ?? ($existing['approval_required'] ?? '1')),
        ];
    }

    private function normalizeCode(string $value): string
    {
        $code = strtolower(trim($value));
        $code = preg_replace('/[^a-z0-9]+/', '_', $code) ?? '';
        $code = trim($code, '_');
        if (mb_strlen($code) < 2 || mb_strlen($code) > 40 || preg_match('/^[a-z0-9][a-z0-9_]*$/', $code) !== 1) {
            throw new InvalidArgumentException('Payment method code must contain 2 to 40 lowercase letters, numbers, or underscores.');
        }
        return $code;
    }

    /** @return array<string,string> */
    private function mergeSecrets(array $existing, array $input, array $keys): array
    {
        $result = $existing;

        foreach ($keys as $key) {
            $value = trim((string) ($input[$key] ?? ''));

            if ($value === '') {
                continue;
            }

            if (strlen($value) > 500) {
                throw new InvalidArgumentException('A payment credential exceeds the supported length.');
            }

            $result[$key] = $value;
        }

        return $result;
    }

    private function currency(mixed $value): string
    {
        $currency = strtoupper(trim((string) $value));

        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new InvalidArgumentException('Currency must use a three-letter ISO code.');
        }

        return $currency;
    }

    private function text(mixed $value, string $label, int $min, int $max): string
    {
        $text = trim((string) $value);
        $length = mb_strlen($text);

        if ($length < $min || $length > $max) {
            throw new InvalidArgumentException(
                sprintf('%s must contain between %d and %d characters.', $label, $min, $max)
            );
        }

        return $text;
    }

    private function integer(mixed $value, string $label, int $min, int $max): int
    {
        $raw = trim((string) $value);

        if ($raw === '' || preg_match('/^-?\d+$/', $raw) !== 1) {
            throw new InvalidArgumentException($label . ' must be a whole number.');
        }

        $value = (int) $raw;

        if ($value < $min || $value > $max) {
            throw new InvalidArgumentException(
                sprintf('%s must be between %d and %d.', $label, $min, $max)
            );
        }

        return $value;
    }

    private function boolean(mixed $value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }

    /** @return array<string,mixed> */
    private function administrator(): array
    {
        $user = \auth_user();

        if (!is_array($user) || !\auth_can('billing.manage')) {
            throw new DomainException('Administrator billing permission is required.');
        }

        return $user;
    }
}
