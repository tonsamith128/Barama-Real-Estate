<?php

declare(strict_types=1);

namespace Barama\Http\Controllers;

use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Services\AdminMediaManagementService;
use Barama\Services\AppearanceManagementService;
use Barama\Services\BillingOperationsService;
use Barama\Services\BulkModerationService;
use Barama\Services\CatalogManagementService;
use Barama\Services\CommunicationsManagementService;
use Barama\Services\DomainWorkflowService;
use Barama\Services\EmailTemplateManagementService;
use Barama\Services\FinanceManagementService;
use Barama\Services\PageManagementService;
use Barama\Services\CompanyManagementService;
use Barama\Services\ProjectManagementService;
use Barama\Services\PropertyManagementService;
use Barama\Services\SiteContentManagementService;
use Barama\Services\SiteMediaStorageService;
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

    public function retryBillingWebhook(Request $request, string $id): Response
    {
        return $this->execute(function () use ($id): array {
            if (!ctype_digit($id) || (int) $id < 1) {
                throw new InvalidArgumentException('A valid webhook event ID is required.');
            }

            $result = (new BillingOperationsService())->retryWebhookEvent((int) $id);
            $result['message'] = 'Webhook event retry completed.';

            return $result;
        });
    }

    public function ignoreBillingWebhook(Request $request, string $id): Response
    {
        return $this->execute(function () use ($id): array {
            if (!ctype_digit($id) || (int) $id < 1) {
                throw new InvalidArgumentException('A valid webhook event ID is required.');
            }

            (new BillingOperationsService())->ignoreWebhookEvent((int) $id);

            return [
                'webhook_event_id' => (int) $id,
                'status' => 'ignored',
                'message' => 'Webhook event ignored.',
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

    public function requeueMail(Request $request, string $id): Response
    {
        return $this->execute(function () use ($id): array {
            if (!ctype_digit($id) || (int) $id < 1) {
                throw new InvalidArgumentException('A valid mail queue ID is required.');
            }

            (new CommunicationsManagementService())->requeueEmail((int) $id);

            return [
                'email_outbox_id' => (int) $id,
                'status' => 'pending',
                'message' => 'Mail item requeued.',
            ];
        });
    }

    public function cancelMail(Request $request, string $id): Response
    {
        return $this->execute(function () use ($id): array {
            if (!ctype_digit($id) || (int) $id < 1) {
                throw new InvalidArgumentException('A valid mail queue ID is required.');
            }

            (new CommunicationsManagementService())->cancelEmail((int) $id);

            return [
                'email_outbox_id' => (int) $id,
                'status' => 'cancelled',
                'message' => 'Mail item cancelled.',
            ];
        });
    }

    public function createEmailTemplate(Request $request): Response
    {
        return $this->execute(fn (): array => [
            'email_template_id' => (new EmailTemplateManagementService())
                ->create($request->all()),
            'message' => 'Email template created as inactive.',
        ], 201);
    }

    public function updateEmailTemplate(Request $request, string $id): Response
    {
        return $this->execute(function () use ($request, $id): array {
            if (!ctype_digit($id) || (int) $id < 1) {
                throw new InvalidArgumentException(
                    'A valid email template ID is required.'
                );
            }

            (new EmailTemplateManagementService())->update(
                (int) $id,
                $request->all()
            );

            return [
                'email_template_id' => (int) $id,
                'message' => 'Email template updated.',
            ];
        });
    }

    public function updateEmailTemplateStatus(
        Request $request,
        string $id
    ): Response {
        return $this->execute(function () use ($request, $id): array {
            if (!ctype_digit($id) || (int) $id < 1) {
                throw new InvalidArgumentException(
                    'A valid email template ID is required.'
                );
            }

            $status = trim((string) $request->input('status', ''));
            (new EmailTemplateManagementService())->setStatus(
                (int) $id,
                $status
            );

            return [
                'email_template_id' => (int) $id,
                'status' => $status,
                'message' => 'Email template status updated.',
            ];
        });
    }

    public function updateAppearanceFeature(Request $request, string $feature): Response
    {
        return $this->executeAppearance(function () use ($request, $feature): array {
            (new AppearanceManagementService())->updateFeature(
                $feature,
                $request->all()
            );

            return [
                'feature' => $feature,
                'message' => 'Appearance configuration updated.',
            ];
        });
    }

    public function toggleAppearanceFeature(Request $request, string $feature): Response
    {
        return $this->executeAppearance(function () use ($request, $feature): array {
            $enabled = in_array(
                strtolower(trim((string) $request->input('enabled', '0'))),
                ['1', 'true', 'on', 'yes'],
                true
            );

            (new AppearanceManagementService())->setFeatureEnabled(
                $feature,
                $enabled
            );

            return [
                'feature' => $feature,
                'enabled' => $enabled,
                'message' => $enabled
                    ? 'Appearance feature enabled.'
                    : 'Appearance feature disabled.',
            ];
        });
    }

    public function resetAppearance(Request $request): Response
    {
        return $this->executeAppearance(function (): array {
            (new AppearanceManagementService())->reset();

            return [
                'message' => 'Frontend appearance reset to Barama defaults.',
            ];
        });
    }

    public function updateHomepageFeature(Request $request, string $feature): Response
    {
        return $this->execute(function () use ($request, $feature): array {
            $input = $request->all();

            if (strtolower(trim($feature)) === 'hero') {
                $files = $request->files();
                $storage = new SiteMediaStorageService();

                $image = $files['hero_image_file'] ?? null;
                if (is_array($image) && (int) ($image['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $stored = $storage->store($image, 'homepage_hero_image');
                    $input['uploaded_image_uuid'] = $stored['uuid'];
                }

                $slider = $files['hero_slider_files'] ?? null;
                if (is_array($slider)) {
                    $stored = $storage->storeMany($slider, 'homepage_hero_slider');
                    $input['uploaded_slider_uuids'] = array_map(
                        static fn (array $item): string => (string) $item['uuid'],
                        $stored
                    );
                }

                $video = $files['hero_video_file'] ?? null;
                if (is_array($video) && (int) ($video['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $stored = $storage->store($video, 'homepage_hero_video');
                    $input['uploaded_video_uuid'] = $stored['uuid'];
                }
            }

            (new SiteContentManagementService())->updateHomepageFeature(
                $feature,
                $input
            );

            return [
                'feature' => $feature,
                'message' => 'Homepage feature configuration updated.',
            ];
        });
    }

    public function toggleHomepageFeature(Request $request, string $feature): Response
    {
        return $this->execute(function () use ($request, $feature): array {
            $enabled = in_array(
                strtolower(trim((string) $request->input('enabled', '0'))),
                ['1', 'true', 'on', 'yes'],
                true
            );

            (new SiteContentManagementService())->setHomepageFeatureEnabled(
                $feature,
                $enabled
            );

            return [
                'feature' => $feature,
                'enabled' => $enabled,
                'message' => $enabled
                    ? 'Homepage feature enabled.'
                    : 'Homepage feature disabled.',
            ];
        });
    }

    public function createNavigation(Request $request): Response
    {
        return $this->execute(fn (): array => [
            'navigation_id' => (new SiteContentManagementService())
                ->createNavigation($request->all()),
            'message' => 'Navigation item created.',
        ], 201);
    }

    public function updateNavigation(Request $request, string $id): Response
    {
        return $this->execute(function () use ($request, $id): array {
            (new SiteContentManagementService())->updateNavigation(
                $id,
                $request->all()
            );

            return [
                'navigation_id' => $id,
                'message' => 'Navigation item updated.',
            ];
        });
    }

    public function toggleNavigation(Request $request, string $id): Response
    {
        return $this->execute(function () use ($request, $id): array {
            $enabled = in_array(
                strtolower(trim((string) $request->input('enabled', '0'))),
                ['1', 'true', 'on', 'yes'],
                true
            );

            (new SiteContentManagementService())->setNavigationEnabled(
                $id,
                $enabled
            );

            return [
                'navigation_id' => $id,
                'enabled' => $enabled,
                'message' => $enabled
                    ? 'Navigation item enabled.'
                    : 'Navigation item disabled.',
            ];
        });
    }

    public function deleteNavigation(Request $request, string $id): Response
    {
        return $this->execute(function () use ($id): array {
            (new SiteContentManagementService())->deleteNavigation($id);

            return [
                'navigation_id' => $id,
                'message' => 'Navigation item deleted.',
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

    public function updateCompany(Request $request, string $id): Response
    {
        return $this->execute(function () use ($request, $id): array {
            if (!ctype_digit($id) || (int) $id < 1) {
                throw new InvalidArgumentException('A valid company ID is required.');
            }

            (new CompanyManagementService())->updateAsAdministrator(
                (int) $id,
                $request->all()
            );

            return [
                'company_id' => (int) $id,
                'message' => 'Company updated successfully.',
            ];
        });
    }

    public function deleteCompany(Request $request, string $id): Response
    {
        return $this->execute(function () use ($id): array {
            if (!ctype_digit($id) || (int) $id < 1) {
                throw new InvalidArgumentException('A valid company ID is required.');
            }

            (new CompanyManagementService())->deleteAsAdministrator((int) $id);

            return [
                'company_id' => (int) $id,
                'message' => 'Company deleted successfully.',
            ];
        });
    }

    public function updateProject(Request $request, string $id): Response
    {
        return $this->execute(function () use ($request, $id): array {
            if (!ctype_digit($id) || (int) $id < 1) {
                throw new InvalidArgumentException('A valid project ID is required.');
            }

            (new ProjectManagementService())->updateAsAdministrator(
                (int) $id,
                $request->all()
            );

            return [
                'project_id' => (int) $id,
                'message' => 'Project updated successfully.',
            ];
        });
    }

    public function deleteProject(Request $request, string $id): Response
    {
        return $this->execute(function () use ($id): array {
            if (!ctype_digit($id) || (int) $id < 1) {
                throw new InvalidArgumentException('A valid project ID is required.');
            }

            (new ProjectManagementService())->deleteAsAdministrator((int) $id);

            return [
                'project_id' => (int) $id,
                'message' => 'Project deleted successfully.',
            ];
        });
    }

    public function updateProperty(Request $request, string $id): Response
    {
        return $this->execute(function () use ($request, $id): array {
            if (!ctype_digit($id) || (int) $id < 1) {
                throw new InvalidArgumentException('A valid property ID is required.');
            }

            (new PropertyManagementService())->updateAsAdministrator(
                (int) $id,
                $request->all()
            );

            return [
                'property_id' => (int) $id,
                'message' => 'Property updated successfully.',
            ];
        });
    }

    public function deleteProperty(Request $request, string $id): Response
    {
        return $this->execute(function () use ($id): array {
            if (!ctype_digit($id) || (int) $id < 1) {
                throw new InvalidArgumentException('A valid property ID is required.');
            }

            (new PropertyManagementService())->deleteAsAdministrator((int) $id);

            return [
                'property_id' => (int) $id,
                'message' => 'Property deleted successfully.',
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
     * Appearance writes keep JSON for the enhanced UI but retain a native
     * redirect fallback so configuration remains usable if browser scripting
     * or an intermediary blocks the AJAX response.
     *
     * @param callable(): array<string, mixed> $action
     */
    private function executeAppearance(callable $action): Response
    {
        $ajax = strtolower(trim((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')))
            === 'xmlhttprequest';

        try {
            $data = $action();

            if ($ajax) {
                return Response::json([
                    'ok' => true,
                    'data' => $data,
                ]);
            }

            return Response::redirect('/dashboard/appearance?appearance_saved=1');
        } catch (InvalidArgumentException $exception) {
            if ($ajax) {
                return Response::json([
                    'ok' => false,
                    'message' => $exception->getMessage(),
                ], 422);
            }

            return Response::redirect(
                '/dashboard/appearance?appearance_error='
                . rawurlencode($exception->getMessage())
            );
        } catch (DomainException $exception) {
            if ($ajax) {
                return Response::json([
                    'ok' => false,
                    'message' => $exception->getMessage(),
                ], 403);
            }

            return Response::redirect(
                '/dashboard/appearance?appearance_error='
                . rawurlencode($exception->getMessage())
            );
        } catch (\Throwable $exception) {
            error_log(
                '[Barama Appearance] '
                . $exception::class
                . ': '
                . $exception->getMessage()
            );

            $message = 'Appearance could not be saved. Please retry or review the server error log.';

            if ($ajax) {
                return Response::json([
                    'ok' => false,
                    'message' => $message,
                ], 500);
            }

            return Response::redirect(
                '/dashboard/appearance?appearance_error='
                . rawurlencode($message)
            );
        }
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
