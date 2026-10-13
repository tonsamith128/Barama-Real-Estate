<?php

declare(strict_types=1);

namespace Barama\Http\Controllers;

use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Repositories\NotificationRepository;
use Barama\Services\AccountSettingsService;
use Barama\Services\CompanyOnboardingService;
use Barama\Services\CustomerEngagementService;
use Barama\Services\MembershipService;
use Barama\Services\NotificationService;
use Barama\Services\ProfileAvatarService;
use DomainException;
use InvalidArgumentException;

final class AccountManagementController
{
    public function updateProfile(Request $request): Response
    {
        return $this->execute($request, function () use ($request): array {
            $action = trim((string) $request->input('profile_action', 'details'));
            $userId = $this->userId();

            if ($action === 'avatar_upload') {
                $files = $request->files();
                $avatar = $files['avatar'] ?? null;
                if (!is_array($avatar)) {
                    throw new InvalidArgumentException('Choose a profile image to upload.');
                }
                (new ProfileAvatarService())->replace($userId, $avatar);
                return ['message' => 'Profile image updated.'];
            }

            if ($action === 'avatar_remove') {
                (new ProfileAvatarService())->remove($userId);
                return ['message' => 'Profile image removed.'];
            }

            if ($action !== 'details') {
                throw new InvalidArgumentException('Unknown profile action.');
            }

            (new AccountSettingsService())->updateProfile($userId, $request->all());
            return ['message' => 'Profile updated.'];
        }, '/account/profile');
    }

    public function updatePrivacy(Request $request): Response
    {
        return $this->execute(function () use ($request): array {
            (new AccountSettingsService())->updatePrivacy($this->userId(), $request->all());
            return ['message' => 'Privacy settings updated.'];
        }, '/account/privacy');
    }

    public function clearRecentViews(Request $request): Response
    {
        return $this->execute(function (): array {
            (new AccountSettingsService())->clearRecentViews($this->userId());
            return ['message' => 'Recently viewed history cleared.'];
        }, '/account/privacy');
    }

    public function changePassword(Request $request): Response
    {
        return $this->execute(function () use ($request): array {
            (new AccountSettingsService())->changePassword($this->userId(), $request->all());
            return ['message' => 'Password updated securely.'];
        }, '/account/security');
    }

    public function requestEmailChange(Request $request): Response
    {
        return $this->execute(function () use ($request): array {
            (new AccountSettingsService())->requestEmailChange($this->userId(), $request->all());
            return ['message' => 'Confirmation sent to your new email address.'];
        }, '/account/security');
    }

    public function confirmEmailChange(Request $request, string $token): Response
    {
        return $this->execute(function () use ($token): array {
            (new AccountSettingsService())->confirmEmailChange($this->userId(), $token);
            return ['message' => 'Email address updated and verified.'];
        }, '/account/security');
    }

    public function deactivateAccount(Request $request): Response
    {
        return $this->execute(function () use ($request): array {
            (new AccountSettingsService())->deactivateAccount($this->userId(), $request->all());
            return ['message' => 'Account deactivated.'];
        }, '/');
    }

    public function submitCompanyOnboarding(Request $request): Response
    {
        return $this->execute(function () use ($request): array {
            $companyId = (new CompanyOnboardingService())->submit($request->all());
            return ['company_id' => $companyId, 'message' => 'Company submitted for administrator review.'];
        }, '/account/company', 201);
    }

    public function acceptCompanyInvitation(Request $request, string $id): Response
    {
        return $this->execute(function () use ($id): array {
            $companyId = (new MembershipService())->acceptInvitation($this->id($id));
            return ['company_id' => $companyId, 'message' => 'Company invitation accepted.'];
        }, '/developer');
    }

    public function saveListing(Request $request, string $id): Response
    {
        return $this->execute($request, function () use ($id): array {
            $listingId = $this->id($id);
            (new CustomerEngagementService())->saveListing($this->userId(), $listingId);

            return ['listing_id' => $listingId, 'saved' => true, 'message' => 'Property saved.'];
        }, '/account/saved');
    }

    public function removeSavedListing(Request $request, string $id): Response
    {
        return $this->execute($request, function () use ($id): array {
            $listingId = $this->id($id);
            (new CustomerEngagementService())->removeSavedListing($this->userId(), $listingId);

            return ['listing_id' => $listingId, 'saved' => false, 'message' => 'Property removed from saved items.'];
        }, '/account/saved');
    }

    public function addComparison(Request $request, string $id): Response
    {
        return $this->execute($request, function () use ($id): array {
            $listingId = $this->id($id);
            (new CustomerEngagementService())->addComparison($this->userId(), $listingId);

            return ['listing_id' => $listingId, 'compared' => true, 'message' => 'Property added to comparison.'];
        }, '/account/compare');
    }

    public function removeComparison(Request $request, string $id): Response
    {
        return $this->execute($request, function () use ($id): array {
            $listingId = $this->id($id);
            (new CustomerEngagementService())->removeComparison($this->userId(), $listingId);

            return ['listing_id' => $listingId, 'compared' => false, 'message' => 'Property removed from comparison.'];
        }, '/account/compare');
    }

    public function createSavedSearch(Request $request): Response
    {
        return $this->execute($request, function () use ($request): array {
            $searchId = (new CustomerEngagementService())->createSavedSearch(
                $this->userId(),
                $request->all()
            );

            return ['search_id' => $searchId, 'message' => 'Saved search created.'];
        }, '/account/alerts');
    }

    public function updateSavedSearch(Request $request, string $id): Response
    {
        return $this->execute($request, function () use ($request, $id): array {
            $searchId = $this->id($id);
            (new CustomerEngagementService())->updateSavedSearchAlerts(
                $this->userId(),
                $searchId,
                $request->all()
            );

            return ['search_id' => $searchId, 'message' => 'Alert preferences updated.'];
        }, '/account/alerts');
    }

    public function deleteSavedSearch(Request $request, string $id): Response
    {
        return $this->execute($request, function () use ($id): array {
            $searchId = $this->id($id);
            (new CustomerEngagementService())->deleteSavedSearch($this->userId(), $searchId);

            return ['search_id' => $searchId, 'message' => 'Saved search deleted.'];
        }, '/account/alerts');
    }

    public function createViewing(Request $request, string $id): Response
    {
        return $this->execute($request, function () use ($request, $id): array {
            $listingId = $this->id($id);
            $viewingId = (new CustomerEngagementService())->createViewingRequest(
                $this->userId(),
                $listingId,
                $request->all()
            );

            return ['viewing_id' => $viewingId, 'message' => 'Viewing request submitted.'];
        }, '/account/viewings', 201);
    }

    public function cancelViewing(Request $request, string $id): Response
    {
        return $this->execute($request, function () use ($id): array {
            $viewingId = $this->id($id);
            (new CustomerEngagementService())->cancelViewingRequest(
                $this->userId(),
                $viewingId
            );

            return ['viewing_id' => $viewingId, 'message' => 'Viewing request cancelled.'];
        }, '/account/viewings');
    }

    public function markNotificationRead(Request $request, string $id): Response
    {
        return $this->execute($request, function () use ($id): array {
            $notificationId = $this->id($id);
            (new NotificationRepository())->markRead($this->userId(), $notificationId);

            return ['notification_id' => $notificationId, 'message' => 'Notification marked read.'];
        }, '/account/notifications');
    }

    public function markAllNotificationsRead(Request $request): Response
    {
        return $this->execute($request, function (): array {
            (new NotificationRepository())->markAllRead($this->userId());

            return ['message' => 'All notifications marked read.'];
        }, '/account/notifications');
    }

    public function updateNotificationPreferences(Request $request): Response
    {
        return $this->execute($request, function () use ($request): array {
            (new NotificationService())->updatePreferences(
                $this->userId(),
                $request->all()
            );

            return ['message' => 'Notification preferences updated.'];
        }, '/account/notifications');
    }

    /**
     * @param callable():array<string,mixed> $action
     */
    private function execute(
        Request $request,
        callable $action,
        string $redirectPath,
        int $successStatus = 200
    ): Response {
        try {
            $payload = $action();

            if ($this->wantsJson($request)) {
                return Response::json(['ok' => true, 'data' => $payload], $successStatus);
            }

            return Response::redirect(url($redirectPath));
        } catch (InvalidArgumentException $exception) {
            return Response::json(['ok' => false, 'message' => $exception->getMessage()], 422);
        } catch (DomainException $exception) {
            return Response::json(['ok' => false, 'message' => $exception->getMessage()], 403);
        }
    }

    private function userId(): int
    {
        $user = auth_user();

        if ($user === null) {
            throw new DomainException('Authentication is required.');
        }

        return (int) $user['id'];
    }

    private function id(string $id): int
    {
        if (!ctype_digit($id) || (int) $id < 1) {
            throw new InvalidArgumentException('A valid record ID is required.');
        }

        return (int) $id;
    }

    private function wantsJson(Request $request): bool
    {
        return strtolower((string) $request->header('X-Requested-With', '')) === 'xmlhttprequest'
            || str_contains(strtolower((string) $request->header('Accept', '')), 'application/json');
    }
}
