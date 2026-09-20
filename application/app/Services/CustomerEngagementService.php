<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Domain\RealEstate\DomainRules;
use Barama\Repositories\CustomerEngagementRepository;
use Barama\Support\DomainInput;
use DomainException;
use InvalidArgumentException;

final class CustomerEngagementService
{
    private const COMPARISON_LIMIT = 4;
    private const TIME_WINDOWS = ['morning', 'midday', 'afternoon', 'evening'];
    private const ALERT_FREQUENCIES = ['daily', 'weekly'];

    public function saveListing(int $userId, int $listingId): void
    {
        $repository = new CustomerEngagementRepository();
        $this->requirePublicListing($repository, $listingId);
        $repository->saveListing($userId, $listingId);
    }

    public function removeSavedListing(int $userId, int $listingId): void
    {
        (new CustomerEngagementRepository())->removeSavedListing($userId, $listingId);
    }

    public function addComparison(int $userId, int $listingId): void
    {
        $repository = new CustomerEngagementRepository();
        $this->requirePublicListing($repository, $listingId);

        if ($repository->isCompared($userId, $listingId)) {
            return;
        }

        if ($repository->comparisonCount($userId) >= self::COMPARISON_LIMIT) {
            throw new DomainException(
                'You can compare up to ' . self::COMPARISON_LIMIT . ' properties at a time.'
            );
        }

        $repository->addComparison(
            $userId,
            $listingId,
            $repository->nextComparisonPosition($userId)
        );
    }

    public function removeComparison(int $userId, int $listingId): void
    {
        (new CustomerEngagementRepository())->removeComparison($userId, $listingId);
    }

    public function recordView(int $userId, int $listingId): void
    {
        $repository = new CustomerEngagementRepository();

        if ($repository->publicListing($listingId) !== null) {
            $repository->recordView($userId, $listingId);
        }
    }

    /** @param array<string,mixed> $input */
    public function createSavedSearch(int $userId, array $input): int
    {
        $transactionType = DomainInput::optionalString($input, 'transaction_type', 24);
        $propertyType = DomainInput::optionalString($input, 'property_type', 50);
        $locationId = $this->optionalPositiveInt($input['location_id'] ?? null);
        $bedroomsMin = $this->optionalDecimal($input['bedrooms_min'] ?? null);
        $priceMin = $this->optionalDecimal($input['price_min'] ?? null);
        $priceMax = $this->optionalDecimal($input['price_max'] ?? null);

        if ($transactionType !== null && !in_array($transactionType, ['sale', 'rent'], true)) {
            throw new InvalidArgumentException('Saved search offer type is invalid.');
        }

        if ($propertyType !== null && !in_array($propertyType, DomainRules::PROPERTY_TYPES, true)) {
            throw new InvalidArgumentException('Saved search property type is invalid.');
        }

        $repository = new CustomerEngagementRepository();

        if ($locationId !== null && !$repository->activeLocationExists($locationId)) {
            throw new InvalidArgumentException('Selected location is unavailable.');
        }

        if ($priceMin !== null && $priceMax !== null && $priceMin > $priceMax) {
            throw new InvalidArgumentException('Maximum price must be greater than minimum price.');
        }

        $frequency = trim((string) ($input['alert_frequency'] ?? 'daily'));

        if (!in_array($frequency, self::ALERT_FREQUENCIES, true)) {
            throw new InvalidArgumentException('Alert frequency is invalid.');
        }

        $currency = strtoupper(trim((string) ($input['currency'] ?? 'USD')));

        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new InvalidArgumentException('Currency must use a three-letter code.');
        }

        return $repository->createSavedSearch($userId, [
            'name' => DomainInput::requiredString($input, 'name', 120, 2),
            'query_text' => DomainInput::optionalString($input, 'query_text', 100),
            'transaction_type' => $transactionType,
            'property_type' => $propertyType,
            'location_id' => $locationId,
            'bedrooms_min' => $bedroomsMin,
            'price_min' => $priceMin,
            'price_max' => $priceMax,
            'currency' => $currency,
            'alert_frequency' => $frequency,
            'alerts_enabled' => in_array(
                (string) ($input['alerts_enabled'] ?? ''),
                ['1', 'true', 'on'],
                true
            ),
        ]);
    }

    public function deleteSavedSearch(int $userId, int $searchId): void
    {
        (new CustomerEngagementRepository())->deleteSavedSearch($userId, $searchId);
    }

    /** @param array<string,mixed> $input */
    public function updateSavedSearchAlerts(int $userId, int $searchId, array $input): void
    {
        $frequency = trim((string) ($input['alert_frequency'] ?? 'daily'));

        if (!in_array($frequency, self::ALERT_FREQUENCIES, true)) {
            throw new InvalidArgumentException('Alert frequency is invalid.');
        }

        $enabled = in_array(
            (string) ($input['alerts_enabled'] ?? ''),
            ['1', 'true', 'on'],
            true
        );

        (new CustomerEngagementRepository())->updateSavedSearchAlerts(
            $userId,
            $searchId,
            $enabled,
            $frequency
        );
    }

    /** @param array<string,mixed> $input */
    public function createViewingRequest(int $userId, int $listingId, array $input): int
    {
        $repository = new CustomerEngagementRepository();
        $listing = $this->requirePublicListing($repository, $listingId);

        if ($repository->hasActiveViewing($userId, $listingId)) {
            throw new DomainException(
                'You already have an active viewing request for this property.'
            );
        }

        $preferredDate = $this->futureDate($input['preferred_date'] ?? null, 'Preferred date');
        $alternateDate = $this->optionalFutureDate($input['alternate_date'] ?? null, 'Alternate date');
        $preferredWindow = trim((string) ($input['preferred_time_window'] ?? ''));
        $alternateWindow = trim((string) ($input['alternate_time_window'] ?? ''));

        if (!in_array($preferredWindow, self::TIME_WINDOWS, true)) {
            throw new InvalidArgumentException('Preferred viewing time is invalid.');
        }

        if ($alternateDate !== null && !in_array($alternateWindow, self::TIME_WINDOWS, true)) {
            throw new InvalidArgumentException('Alternate viewing time is invalid.');
        }

        if ($alternateDate === null) {
            $alternateWindow = '';
        }

        return $repository->createViewingRequest($userId, [
            'listing_id' => $listingId,
            'company_id' => (int) $listing['company_id'],
            'preferred_date' => $preferredDate,
            'preferred_time_window' => $preferredWindow,
            'alternate_date' => $alternateDate,
            'alternate_time_window' => $alternateWindow !== '' ? $alternateWindow : null,
            'message' => DomainInput::optionalString($input, 'message', 2000),
        ]);
    }

    public function cancelViewingRequest(int $userId, int $viewingId): void
    {
        if (!(new CustomerEngagementRepository())->cancelViewingRequest($userId, $viewingId)) {
            throw new DomainException('This viewing request can no longer be cancelled.');
        }
    }

    /** @return array<string,mixed> */
    private function requirePublicListing(
        CustomerEngagementRepository $repository,
        int $listingId
    ): array {
        if ($listingId < 1) {
            throw new InvalidArgumentException('A valid listing is required.');
        }

        $listing = $repository->publicListing($listingId);

        if ($listing === null) {
            throw new InvalidArgumentException('This property is no longer available.');
        }

        return $listing;
    }

    private function optionalPositiveInt(mixed $value): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $validated = filter_var(
            $value,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($validated === false) {
            throw new InvalidArgumentException('Selected location is invalid.');
        }

        return (int) $validated;
    }

    private function optionalDecimal(mixed $value): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $validated = filter_var($value, FILTER_VALIDATE_FLOAT);

        if ($validated === false || (float) $validated < 0) {
            throw new InvalidArgumentException('A numeric search value is invalid.');
        }

        return (float) $validated;
    }

    private function futureDate(mixed $value, string $label): string
    {
        $date = trim((string) $value);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            throw new InvalidArgumentException($label . ' is invalid.');
        }

        $today = new \DateTimeImmutable('today');
        $candidate = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        if ($candidate === false || $candidate->format('Y-m-d') !== $date || $candidate < $today) {
            throw new InvalidArgumentException($label . ' must be today or later.');
        }

        return $date;
    }

    private function optionalFutureDate(mixed $value, string $label): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return $this->futureDate($value, $label);
    }
}
