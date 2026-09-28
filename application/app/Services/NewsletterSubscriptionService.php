<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Repositories\NewsletterSubscriberRepository;
use Barama\Security\NewsletterThrottle;
use DomainException;
use InvalidArgumentException;

final class NewsletterSubscriptionService
{
    /** @param array<string,mixed> $input */
    public function subscribe(array $input, string $ip): int
    {
        if (trim((string) ($input['website'] ?? '')) !== '') {
            throw new InvalidArgumentException('Subscription could not be completed.');
        }

        if (!(new NewsletterThrottle())->allow($ip)) {
            throw new DomainException('Too many subscription attempts. Please try again later.');
        }

        $email = strtolower(trim((string) ($input['email'] ?? '')));

        if (
            $email === ''
            || mb_strlen($email) > 191
            || !filter_var($email, FILTER_VALIDATE_EMAIL)
        ) {
            throw new InvalidArgumentException('Enter a valid email address.');
        }

        $source = strtolower(trim((string) ($input['source'] ?? 'homepage')));
        $source = preg_replace('/[^a-z0-9_-]+/', '-', $source) ?? 'homepage';
        $source = trim($source, '-_');
        $source = $source !== '' ? mb_substr($source, 0, 60) : 'homepage';

        $ipHash = trim($ip) !== ''
            ? hash('sha256', 'newsletter-ip|' . trim($ip))
            : null;

        return (new NewsletterSubscriberRepository())->subscribe(
            $email,
            $source,
            $ipHash
        );
    }
}
