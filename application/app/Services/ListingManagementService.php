<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Domain\RealEstate\DomainRules;
use Barama\Repositories\ListingWriteRepository;
use Barama\Repositories\PropertyWriteRepository;
use Barama\Support\DomainInput;
use Barama\Support\Slugger;
use DomainException;
use InvalidArgumentException;

final class ListingManagementService
{
    /** @param array<string, mixed> $input */
    public function create(array $input): int
    {
        $auth = new AuthService();
        $user = $auth->user();

        if ($user === null || !$auth->can('listing.manage')) {
            throw new DomainException('Listing management permission is required.');
        }

        $propertyId = DomainInput::requiredPositiveInt($input, 'property_id');
        $property = (new PropertyWriteRepository())->findById($propertyId);

        if ($property === null || $property['company_id'] === null) {
            throw new InvalidArgumentException('Property was not found.');
        }

        (new CompanyAccessService())->assertCanManage((int) $property['company_id']);

        return $this->persist(null, $property, (int) $user['id'], $input);
    }

    /** @param array<string, mixed> $input */
    public function update(int $listingId, array $input): void
    {
        $listings = new ListingWriteRepository();
        $listing = $listings->findById($listingId);

        if ($listing === null || $listing['company_id'] === null) {
            throw new InvalidArgumentException('Listing was not found.');
        }

        (new CompanyAccessService())->assertCanManage((int) $listing['company_id']);

        if (!in_array((string) $listing['status'], ['draft', 'rejected', 'paused'], true)) {
            throw new DomainException('Only draft, rejected, or paused listings can be edited.');
        }

        $property = (new PropertyWriteRepository())->findById((int) $listing['property_id']);

        if ($property === null) {
            throw new InvalidArgumentException('Listing property was not found.');
        }

        $this->persist($listingId, $property, (int) $listing['created_by_user_id'], $input);
    }

    /** @param array<string, mixed> $property @param array<string, mixed> $input */
    private function persist(
        ?int $listingId,
        array $property,
        int $userId,
        array $input
    ): int {
        $transactionType = DomainInput::requiredString($input, 'transaction_type', 24);

        if (!in_array($transactionType, DomainRules::LISTING_TRANSACTION_TYPES, true)) {
            throw new InvalidArgumentException('Invalid listing transaction type.');
        }

        $price = DomainInput::optionalDecimal($input, 'price');

        if ($price === null) {
            throw new InvalidArgumentException('Listing price is required.');
        }

        $currency = strtoupper(DomainInput::optionalString($input, 'currency', 3) ?? 'USD');

        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new InvalidArgumentException('Currency must use a three-letter code.');
        }

        $pricingPeriod = DomainInput::optionalString($input, 'pricing_period', 24);

        if ($transactionType === 'rent' && $pricingPeriod === null) {
            throw new InvalidArgumentException('Rental listings require a pricing period.');
        }

        if ($transactionType === 'sale') {
            $pricingPeriod = null;
        }

        $data = [
            'transaction_type' => $transactionType,
            'price' => $price,
            'currency' => $currency,
            'pricing_period' => $pricingPeriod,
            'deposit_amount' => DomainInput::optionalDecimal($input, 'deposit_amount'),
            'negotiable' => DomainInput::boolean($input, 'negotiable') ? 1 : 0,
            'available_from' => DomainInput::optionalString($input, 'available_from', 10),
            'expires_at' => DomainInput::optionalString($input, 'expires_at', 19),
        ];

        $listings = new ListingWriteRepository();

        if ($listingId !== null) {
            $listings->update($listingId, $data);
            return $listingId;
        }

        $data['property_id'] = (int) $property['id'];
        $data['created_by_user_id'] = $userId;
        $data['slug'] = Slugger::unique(
            (string) ($input['slug'] ?? ((string) $property['title'] . '-' . $transactionType)),
            fn (string $candidate): bool => $listings->slugExists($candidate)
        );

        return $listings->create($data);
    }
}
