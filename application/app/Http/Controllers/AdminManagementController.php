<?php

declare(strict_types=1);

namespace Barama\Http\Controllers;

use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Services\AdminMediaManagementService;
use Barama\Services\BulkModerationService;
use Barama\Services\CatalogManagementService;
use Barama\Services\DomainWorkflowService;
use Barama\Services\FinanceManagementService;
use Barama\Services\PageManagementService;
use Barama\Services\SiteContentManagementService;
use Barama\Services\UserAdministrationService;
use DomainException;
use InvalidArgumentException;

final class AdminManagementController
{
    public function createLocation(Request $request): Response
    {
        return $this->execute(fn (): array => [
            'location_id' => (new CatalogManagementService())->createLocation($request->all()),
        ], 201);
    }

    public function createAmenity(Request $request): Response
    {
        return $this->execute(fn (): array => [
            'amenity_id' => (new CatalogManagementService())->createAmenity($request->all()),
        ], 201);
    }

    public function updateLocationStatus(Request $request, string $id): Response
    {
        return $this->execute(function () use ($request, $id): array {
            if (!ctype_digit($id) || (int) $id < 1) {
                throw new InvalidArgumentException('A valid location ID is required.');
            }

            $status = trim((string) $request->input('status', ''));
            (new CatalogManagementService())->setLocationStatus((int) $id, $status);

            return [
                'location_id' => (int) $id,
                'status' => $status,
                'message' => 'Location status updated.',
            ];
        });
    }

    public function updateAmenityStatus(Request $request, string $id): Response
    {
        return $this->execute(function () use ($request, $id): array {
            if (!ctype_digit($id) || (int) $id < 1) {
                throw new InvalidArgumentException('A valid amenity ID is required.');
            }

            $status = trim((string) $request->input('status', ''));
            (new CatalogManagementService())->setAmenityStatus((int) $id, $status);

            return [
                'amenity_id' => (int) $id,
                'status' => $status,
                'message' => 'Amenity status updated.',
            ];
        });
    }

    public function createPlan(Request $request): Response
    {
        return $this->execute(fn (): array => [
            'plan_id' => (new FinanceManagementService())->createPlan($request->all()),
            'message' => 'Subscription plan created as inactive.',
        ], 201);
    }

    public function updatePlanStatus(Request $request, string $id): Response
    {
        return $this->execute(function () use ($request, $id): array {
            if (!ctype_digit($id) || (int) $id < 1) {
                throw new InvalidArgumentException('A valid plan ID is required.');
            }

            $status = trim((string) $request->input('status', ''));
            (new FinanceManagementService())->setPlanStatus((int) $id, $status);

            return [
                'plan_id' => (int) $id,
                'status' => $status,
                'message' => 'Plan status updated.',
            ];
        });
    }

    public function assignSubscription(Request $request): Response
    {
        return $this->execute(fn (): array => [
            'subscription_id' => (new FinanceManagementService())->assignSubscription($request->all()),
            'message' => 'Company subscription assigned.',
        ], 201);
    }

    public function updateSubscriptionStatus(Request $request, string $id): Response
    {
        return $this->execute(function () use ($request, $id): array {
            if (!ctype_digit($id) || (int) $id < 1) {
                throw new InvalidArgumentException('A valid subscription ID is required.');
            }

            $status = trim((string) $request->input('status', ''));
            (new FinanceManagementService())->setSubscriptionStatus((int) $id, $status);

            return [
                'subscription_id' => (int) $id,
                'status' => $status,
                'message' => 'Subscription status updated.',
            ];
        });
    }

    public function createCoupon(Request $request): Response
    {
        return $this->execute(fn (): array => [
            'coupon_id' => (new FinanceManagementService())->createCoupon($request->all()),
            'message' => 'Coupon created as inactive.',
        ], 201);
    }

    public function updateCouponStatus(Request $request, string $id): Response
    {
        return $this->execute(function () use ($request, $id): array {
            if (!ctype_digit($id) || (int) $id < 1) {
                throw new InvalidArgumentException('A valid coupon ID is required.');
            }

            $status = trim((string) $request->input('status', ''));
            (new FinanceManagementService())->setCouponStatus((int) $id, $status);

            return [
                'coupon_id' => (int) $id,
                'status' => $status,
                'message' => 'Coupon status updated.',
            ];
        });
    }

    public function createTaxRule(Request $request): Response
    {
        return $this->execute(fn (): array => [
            'tax_rule_id' => (new FinanceManagementService())->createTaxRule($request->all()),
            'message' => 'Tax rule created as inactive.',
        ], 201);
    }

    public function updateTaxRuleStatus(Request $request, string $id): Response
    {
        return $this->execute(function () use ($request, $id): array {
            if (!ctype_digit($id) || (int) $id < 1) {
                throw new InvalidArgumentException('A valid tax rule ID is required.');
            }

            $status = trim((string) $request->input('status', ''));
            (new FinanceManagementService())->setTaxRuleStatus((int) $id, $status);

            return [
                'tax_rule_id' => (int) $id,
                'status' => $status,
                'message' => 'Tax rule status updated.',
            ];
        });
    }

    public function createPaymentProvider(Request $request): Response
    {
        return $this->execute(fn (): array => [
            'payment_provider_id' => (new FinanceManagementService())->createPaymentProvider($request->all()),
            'message' => 'Payment provider created as inactive.',
        ], 201);
    }

    public function updatePaymentProviderStatus(Request $request, string $id): Response
    {
        return $this->execute(function () use ($request, $id): array {
            if (!ctype_digit($id) || (int) $id < 1) {
                throw new InvalidArgumentException('A valid payment provider ID is required.');
            }

            $status = trim((string) $request->input('status', ''));
            (new FinanceManagementService())->setPaymentProviderStatus((int) $id, $status);

            return [
                'payment_provider_id' => (int) $id,
                'status' => $status,
                'message' => 'Payment provider status updated.',
            ];
        });
    }

    public function createInvoice(Request $request): Response
    {
        return $this->execute(fn (): array => [
            'invoice_id' => (new FinanceManagementService())->createInvoice($request->all()),
            'message' => 'Draft invoice created.',
        ], 201);
    }

    public function updateInvoiceStatus(Request $request, string $id): Response
    {
        return $this->execute(function () use ($request, $id): array {
            if (!ctype_digit($id) || (int) $id < 1) {
                throw new InvalidArgumentException('A valid invoice ID is required.');
            }

            $status = trim((string) $request->input('status', ''));
            (new FinanceManagementService())->setInvoiceStatus((int) $id, $status);

            return [
                'invoice_id' => (int) $id,
                'status' => $status,
                'message' => 'Invoice status updated.',
            ];
        });
    }

    public function createTransaction(Request $request): Response
    {
        return $this->execute(fn (): array => [
            'transaction_id' => (new FinanceManagementService())->createTransaction($request->all()),
            'message' => 'Immutable transaction recorded.',
        ], 201);
    }

    public function updateSiteContent(Request $request): Response
    {
        return $this->execute(function () use ($request): array {
            (new SiteContentManagementService())->update($request->all());

            return [
                'message' => 'Homepage and primary navigation updated.',
            ];
        });
    }

    public function createPage(Request $request): Response
    {
        return $this->execute(fn (): array => [
            'page_id' => (new PageManagementService())->create($request->all()),
            'message' => 'CMS page created as draft.',
        ], 201);
    }

    public function updatePage(Request $request, string $id): Response
    {
        return $this->execute(function () use ($request, $id): array {
            if (!ctype_digit($id) || (int) $id < 1) {
                throw new InvalidArgumentException('A valid page ID is required.');
            }

            (new PageManagementService())->update((int) $id, $request->all());

            return [
                'page_id' => (int) $id,
                'message' => 'CMS page content and SEO settings updated.',
            ];
        });
    }

    public function updatePageStatus(Request $request, string $id): Response
    {
        return $this->execute(function () use ($request, $id): array {
            if (!ctype_digit($id) || (int) $id < 1) {
                throw new InvalidArgumentException('A valid page ID is required.');
            }

            $status = trim((string) $request->input('status', ''));
            (new PageManagementService())->setStatus((int) $id, $status);

            return [
                'page_id' => (int) $id,
                'status' => $status,
                'message' => 'CMS page status updated.',
            ];
        });
    }

    public function updateMediaAltText(Request $request, string $id): Response
    {
        return $this->execute(function () use ($request, $id): array {
            if (!ctype_digit($id) || (int) $id < 1) {
                throw new InvalidArgumentException('A valid media ID is required.');
            }

            $altText = trim((string) $request->input('alt_text', ''));
            (new AdminMediaManagementService())->updateAltText(
                (int) $id,
                $altText !== '' ? $altText : null
            );

            return [
                'media_id' => (int) $id,
                'message' => 'Media alt text updated.',
            ];
        });
    }

    public function deleteOrphanMedia(Request $request, string $id): Response
    {
        return $this->execute(function () use ($id): array {
            if (!ctype_digit($id) || (int) $id < 1) {
                throw new InvalidArgumentException('A valid media asset ID is required.');
            }

            (new AdminMediaManagementService())->deleteOrphanAsset((int) $id);

            return [
                'media_id' => (int) $id,
                'message' => 'Orphaned media asset deleted.',
            ];
        });
    }

    public function bulkWorkflow(Request $request): Response
    {
        return $this->execute(function () use ($request): array {
            $records = $request->input('records', []);

            if (!is_array($records)) {
                throw new InvalidArgumentException('Selected moderation records are invalid.');
            }

            $decision = trim((string) $request->input('decision', ''));
            $note = trim((string) $request->input('note', ''));

            $result = (new BulkModerationService())->moderate(
                $records,
                $decision,
                $note !== '' ? mb_substr($note, 0, 500) : null
            );

            $result['message'] = sprintf(
                'Bulk moderation processed %d record(s): %d succeeded, %d failed.',
                $result['processed'],
                $result['succeeded'],
                $result['failed']
            );

            return $result;
        });
    }

    public function workflow(Request $request, string $resource, string $id): Response
    {
        return $this->execute(function () use ($request, $resource, $id): array {
            $resource = strtolower(trim($resource));

            if (!in_array($resource, ['company', 'project', 'property', 'listing'], true)) {
                throw new DomainException(
                    'Administrator workflow changes are limited to marketplace moderation. '
                    . 'Inquiry, lead, and viewing operations remain company-owned.'
                );
            }

            if (!ctype_digit($id) || (int) $id < 1) {
                throw new InvalidArgumentException('A valid resource ID is required.');
            }

            $status = trim((string) $request->input('status', ''));
            $note = trim((string) $request->input('note', ''));

            if ($status === 'rejected' && $note === '') {
                throw new InvalidArgumentException('A rejection reason is required before returning this record to the submitter.');
            }

            (new DomainWorkflowService())->transition(
                $resource,
                (int) $id,
                $status,
                $note !== '' ? $note : null
            );

            return [
                'resource' => $resource,
                'id' => (int) $id,
                'status' => $status,
            ];
        });
    }

    public function updateUserStatus(Request $request, string $id): Response
    {
        return $this->execute(function () use ($request, $id): array {
            if (!ctype_digit($id) || (int) $id < 1) {
                throw new InvalidArgumentException('A valid user ID is required.');
            }

            $status = trim((string) $request->input('status', ''));
            (new UserAdministrationService())->setStatus((int) $id, $status);

            return [
                'user_id' => (int) $id,
                'status' => $status,
                'message' => 'User account status updated.',
            ];
        });
    }

    public function setAdministrator(Request $request, string $id): Response
    {
        return $this->execute(function () use ($request, $id): array {
            if (!ctype_digit($id) || (int) $id < 1) {
                throw new InvalidArgumentException('A valid user ID is required.');
            }

            $enabled = in_array(
                (string) $request->input('enabled', ''),
                ['1', 'true', 'on'],
                true
            );

            (new UserAdministrationService())->setAdministrator((int) $id, $enabled);

            return [
                'user_id' => (int) $id,
                'administrator' => $enabled,
                'message' => $enabled
                    ? 'Administrator role granted.'
                    : 'Administrator role removed.',
            ];
        });
    }

    public function reconcileDeveloperAccess(Request $request, string $id): Response
    {
        return $this->execute(function () use ($id): array {
            if (!ctype_digit($id) || (int) $id < 1) {
                throw new InvalidArgumentException('A valid user ID is required.');
            }

            $result = (new UserAdministrationService())->reconcileDeveloperAccess((int) $id);
            $result['user_id'] = (int) $id;
            $result['message'] = $result['changed']
                ? 'Developer workspace access was reconciled with active company membership.'
                : 'Developer workspace access already matches active company membership.';

            return $result;
        });
    }

    public function revokeUserSessions(Request $request, string $id): Response
    {
        return $this->execute(function () use ($id): array {
            if (!ctype_digit($id) || (int) $id < 1) {
                throw new InvalidArgumentException('A valid user ID is required.');
            }

            (new UserAdministrationService())->revokeSessions((int) $id);

            return [
                'user_id' => (int) $id,
                'message' => 'Active sessions were invalidated for this account.',
            ];
        });
    }

    /**
     * @param callable(): array<string, mixed> $action
     */
    private function execute(callable $action, int $successStatus = 200): Response
    {
        try {
            return Response::json([
                'ok' => true,
                'data' => $action(),
            ], $successStatus);
        } catch (InvalidArgumentException $exception) {
            return Response::json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 422);
        } catch (DomainException $exception) {
            return Response::json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 403);
        }
    }
}
