<?php
declare(strict_types=1);

namespace Barama\Services\Updates;

/**
 * Presentation-only projection of a single session-bound root-worker result.
 * Never grants entitlement, submits a job, changes the installation, or reads
 * untrusted queue files. The caller supplies OneClickUpdateQueueV1::status().
 */
final class OneClickLifecycleV1
{
    public static function project(
        array $status,
        string $installedVersion,
        ?string $expectedVersion,
        bool $pending
    ): array {
        $state = $status['state'] ?? 'not_started';
        $allowed = [
            'not_started', 'checking', 'backing_up', 'installing',
            'verifying', 'completed', 'blocked', 'failed',
            'recovery_review_required', 'unavailable',
        ];
        if (!is_string($state) || !in_array($state, $allowed, true)) {
            $state = 'unavailable';
        }
        $inProgress = in_array($state, ['checking', 'backing_up', 'installing', 'verifying'], true);
        $needsReview = in_array($state, ['blocked', 'failed', 'recovery_review_required'], true);
        // "completed" can describe an older session while a new intent is pending;
        // never grant green lifecycle steps if queue state is ambiguous.
        $completed = $state === 'completed' && !$pending;
        $targetValid = is_string($expectedVersion)
            && preg_match('/^[0-9]{1,4}\.[0-9]{1,4}\.[0-9]{1,4}(?:[-+][A-Za-z0-9.-]{1,32})?$/D', $expectedVersion) === 1;
        $installedValid = preg_match('/^[0-9]{1,4}\.[0-9]{1,4}\.[0-9]{1,4}(?:[-+][A-Za-z0-9.-]{1,32})?$/D', $installedVersion) === 1;
        // A root "completed" receipt alone is not evidence of which version
        // was installed. Exact target+installed equality is required for step 7.
        $verified = $completed && $targetValid && $installedValid
            && hash_equals($expectedVersion, $installedVersion);
        $phase = match (true) {
            $verified => 'Update installed successfully',
            $completed => 'Worker completed — verify version',
            $state === 'recovery_review_required' => 'Recovery review required',
            $state === 'failed' => 'Update failed',
            $state === 'blocked' => 'Update blocked',
            $inProgress => 'Installing verified update',
            $pending => 'Update request pending',
            default => '',
        };
        $note = match (true) {
            $verified => 'The trusted worker completed this job and the installed version matches its requested target.',
            $completed => 'The trusted worker completed this job, but the expected version is not verified in this administrator session.',
            $state === 'recovery_review_required' => 'A protected recovery review is required. Do not resubmit the update.',
            $state === 'failed' => 'The trusted worker reported failure. Preserve recovery evidence before another attempt.',
            $state === 'blocked' => 'A safety check blocked the job; installation success has not been established.',
            $inProgress => 'The trusted worker is processing the bound administrator job; no percentage is simulated.',
            $pending => 'The request awaits an independent trusted worker result.',
            default => '',
        };
        return [
            'state' => $state,
            'in_progress' => $inProgress,
            'needs_review' => $needsReview,
            'completed' => $completed,
            'verified' => $verified,
            'phase' => $phase,
            'note' => $note,
        ];
    }
}
