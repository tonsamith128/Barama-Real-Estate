<?php

declare(strict_types=1);

namespace Barama\Http\Controllers;

use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Core\View;
use Barama\Repositories\NotificationRepository;

/** Private, user-owned notifications within the Developer workspace. */
final class DeveloperNotificationsController
{
    public function index(Request $request): Response
    {
        $user = auth_user();
        if ($user === null) {
            return Response::redirect(url('/login'));
        }
        $repository = new NotificationRepository();
        $items = $repository->notifications((int) $user['id'], 150);
        $state = trim((string) $request->input('state', 'all'));
        if (!in_array($state, ['all', 'unread', 'read'], true)) {
            $state = 'all';
        }
        $query = mb_strtolower(mb_substr(trim((string) $request->input('q', '')), 0, 80));
        $items = array_values(array_filter($items, static function (array $item) use ($state, $query): bool {
            if ($state === 'unread' && !empty($item['read_at'])) return false;
            if ($state === 'read' && empty($item['read_at'])) return false;
            return $query === '' || mb_stripos((string)($item['title'] ?? '') . ' ' . (string)($item['message'] ?? ''), $query) !== false;
        }));
        return Response::html(View::render('workspace/developer-notifications', [
            'pageTitle' => 'Notifications · Company / Vendor',
            'activeArea' => 'developer',
            'workspaceType' => 'developer',
            'workspaceLabel' => 'Company / Vendor Workspace',
            'workspaceNavigation' => (new DeveloperController())->settingsNavigation(),
            'activeSection' => 'notifications',
            'notificationItems' => $items,
            'notificationUnread' => $repository->unreadCount((int)$user['id']),
            'notificationState' => $state,
            'notificationQuery' => $query,
        ]));
    }

    public function read(Request $request, string $id): Response
    {
        $user = auth_user();
        if ($user === null) return Response::redirect(url('/login'));
        if (!ctype_digit($id) || (int) $id < 1) {
            return Response::html('Invalid notification.', 404);
        }
        (new NotificationRepository())->markRead((int)$user['id'], (int)$id);
        return Response::redirect(url('/developer/notifications'));
    }

    public function readAll(Request $request): Response
    {
        $user = auth_user();
        if ($user === null) return Response::redirect(url('/login'));
        (new NotificationRepository())->markAllRead((int)$user['id']);
        return Response::redirect(url('/developer/notifications'));
    }
}
