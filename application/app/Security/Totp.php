<?php

declare(strict_types=1);

namespace Barama\Security;

use InvalidArgumentException;

final class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generateSecret(int $bytes = 20): string
    {
        $bytes = max(16, min(64, $bytes));
        return self::base32Encode(random_bytes($bytes));
    }

    public static function verify(
        string $secret,
        string $code,
        ?int $timestamp = null,
        int $window = 1
    ): bool {
        $code = preg_replace('/\D+/', '', $code) ?? '';
        if (strlen($code) !== 6) {
            return false;
        }

        $timestamp ??= time();
        $window = max(0, min(2, $window));

        for ($offset = -$window; $offset <= $window; $offset++) {
            if (hash_equals(self::code($secret, $timestamp + ($offset * 30)), $code)) {
                return true;
            }
        }

        return false;
    }

    public static function code(string $secret, ?int $timestamp = null): string
    {
        $timestamp ??= time();
        $counter = intdiv($timestamp, 30);
        $key = self::base32Decode($secret);
        if ($key === '') {
            throw new InvalidArgumentException('The MFA secret is invalid.');
        }

        $high = intdiv($counter, 0x100000000);
        $low = $counter % 0x100000000;
        $binaryCounter = pack('N2', $high, $low);
        $hash = hash_hmac('sha1', $binaryCounter, $key, true);
        $offset = ord($hash[19]) & 0x0f;
        $binary = ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff);

        return str_pad((string) ($binary % 1000000), 6, '0', STR_PAD_LEFT);
    }

    public static function otpauthUri(string $secret, string $account, string $issuer): string
    {
        $issuer = trim($issuer) !== '' ? trim($issuer) : 'Barama';
        $label = rawurlencode($issuer . ':' . $account);

        return 'otpauth://totp/' . $label
            . '?secret=' . rawurlencode($secret)
            . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1&digits=6&period=30';
    }

    private static function base32Encode(string $bytes): string
    {
        $buffer = 0;
        $bits = 0;
        $output = '';

        foreach (unpack('C*', $bytes) ?: [] as $byte) {
            $buffer = ($buffer << 8) | $byte;
            $bits += 8;

            while ($bits >= 5) {
                $bits -= 5;
                $output .= self::ALPHABET[($buffer >> $bits) & 31];
            }
        }

        if ($bits > 0) {
            $output .= self::ALPHABET[($buffer << (5 - $bits)) & 31];
        }

        return $output;
    }

    private static function base32Decode(string $secret): string
    {
        $secret = strtoupper((string) preg_replace('/[^A-Z2-7]/i', '', $secret));
        if ($secret === '') {
            return '';
        }

        $buffer = 0;
        $bits = 0;
        $output = '';

        foreach (str_split($secret) as $char) {
            $value = strpos(self::ALPHABET, $char);
            if ($value === false) {
                return '';
            }

            $buffer = ($buffer << 5) | $value;
            $bits += 5;

            if ($bits >= 8) {
                $bits -= 8;
                $output .= chr(($buffer >> $bits) & 0xff);
            }
        }

        return $output;
    }

    private function __construct()
    {
    }
}
