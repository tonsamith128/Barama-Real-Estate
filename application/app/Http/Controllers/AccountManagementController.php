<?php

declare(strict_types=1);

namespace Barama\Http\Controllers;

use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Repositories\NotificationRepository;
use Barama\Services\CustomerEngagementService;
use Barama\Services\NotificationService;
use DomainException;
use InvalidArgumentException;

final class AccountManagementController
{
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
