<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Repositories\SecurityAuditRepository;
use JsonException;

final class SecurityAuditService
{
    /** @param array<string,mixed> $metadata */
    public function record(
        string $eventType,
        ?int $actorUserId = null,
        ?int $subjectUserId = null,
        array $metadata = [],
        ?string $ip = null,
        ?string $userAgent = null
    ): void {
        $eventType = strtolower(trim($eventType));

        if (
            $eventType === ''
            || strlen($eventType) > 100
            || preg_match('/^[a-z0-9._-]+$/', $eventType) !== 1
        ) {
            return;
        }

        $ip ??= (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $userAgent ??= (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');

        $safeMetadata = [];

        foreach ($metadata as $key => $value) {
            if (!is_string($key) || strlen($key) > 80) {
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $safeMetadata[$key] = is_string($value)
                    ? mb_substr($value, 0, 500)
                    : $value;
            }
        }

        try {
            $metadataJson = $safeMetadata === []
                ? null
                : json_encode($safeMetadata, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $metadataJson = null;
        }

        (new SecurityAuditRepository())->record([
            'actor_user_id' => $actorUserId,
            'subject_user_id' => $subjectUserId,
            'event_type' => $eventType,
            'ip_hash' => $ip !== '' ? hash('sha256', $ip) : null,
            'user_agent_hash' => $userAgent !== '' ? hash('sha256', $userAgent) : null,
            'metadata_json' => $metadataJson,
        ]);
    }
}
