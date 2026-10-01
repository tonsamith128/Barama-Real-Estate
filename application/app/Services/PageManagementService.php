<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Core\Database;
use Barama\Repositories\PageRepository;
use DomainException;
use InvalidArgumentException;
use Throwable;

final class PageManagementService
{
    /** @param array<string,mixed> $input */
    public function create(array $input): int
    {
        $user = $this->administrator();
        $data = $this->validated($input);
        $repository = new PageRepository();

        if ($repository->slugExists($data['slug'])) {
            throw new InvalidArgumentException('This page URL slug is already in use.');
        }

        $data['status'] = 'draft';
        $data['user_id'] = (int) $user['id'];

        $pdo = Database::connection();

        try {
            $pdo->beginTransaction();
            $id = $repository->create($data);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        (new SecurityAuditService())->record(
            'admin.page_created',
            (int) $user['id'],
            null,
            ['page_id' => $id, 'slug' => $data['slug']]
        );

        return $id;
    }

    /** @param array<string,mixed> $input */
    public function update(int $id, array $input): void
    {
        $user = $this->administrator();
        $repository = new PageRepository();
        $page = $repository->find($id);

        if ($page === null) {
            throw new InvalidArgumentException('CMS page was not found.');
        }

        $data = $this->validated($input);

        if ($repository->slugExists($data['slug'], $id)) {
            throw new InvalidArgumentException('This page URL slug is already in use.');
        }

        $data['user_id'] = (int) $user['id'];

        $repository->update($id, $data);

        (new SecurityAuditService())->record(
            'admin.page_updated',
            (int) $user['id'],
            null,
            ['page_id' => $id, 'slug' => $data['slug']]
        );
    }

    public function setStatus(int $id, string $status): void
    {
        $user = $this->administrator();
        $repository = new PageRepository();
        $page = $repository->find($id);

        if ($page === null) {
            throw new InvalidArgumentException('CMS page was not found.');
        }

        if (in_array(strtolower((string) $page['slug']), ['about', 'about-us', 'contact', 'contact-us'], true)) {
            throw new DomainException(
                'About Us and Contact Us are built-in pages. Manage them from their dedicated dashboard sections.'
            );
        }

        $status = strtolower(trim($status));

        $transitions = [
            'draft' => ['published', 'archived'],
            'published' => ['draft', 'archived'],
            'archived' => ['draft'],
        ];

        $current = (string) $page['status'];

        if (!in_array($status, $transitions[$current] ?? [], true)) {
            throw new DomainException('This CMS page status transition is not allowed.');
        }

        if ($status === 'published' && trim((string) $page['content_text']) === '') {
            throw new DomainException('Add page content before publishing.');
        }

        $repository->setStatus($id, $status, (int) $user['id']);

        (new SecurityAuditService())->record(
            'admin.page_status_changed',
            (int) $user['id'],
            null,
            [
                'page_id' => $id,
                'from_status' => $current,
                'to_status' => $status,
                'slug' => (string) $page['slug'],
            ]
        );
    }

    /** @return array<string,mixed> */
    private function administrator(): array
    {
        $auth = new AuthService();
        $user = $auth->user();

        if (
            $user === null
            || !$auth->hasRole('administrator')
            || !$auth->can('page.manage')
        ) {
            throw new DomainException('Administrator page management permission is required.');
        }

        return $user;
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    private function validated(array $input): array
    {
        $title = trim((string) ($input['title'] ?? ''));
        $slug = strtolower(trim((string) ($input['slug'] ?? '')));
        $excerpt = trim((string) ($input['excerpt'] ?? ''));
        $content = PageContentSanitizer::sanitize(
            (string) ($input['content_text'] ?? '')
        );
        $seoTitle = trim((string) ($input['seo_title'] ?? ''));
        $seoDescription = trim((string) ($input['seo_description'] ?? ''));
        $robots = strtolower(trim((string) ($input['robots_directive'] ?? 'index,follow')));

        if (mb_strlen($title) < 2 || mb_strlen($title) > 191) {
            throw new InvalidArgumentException('Page title must contain between 2 and 191 characters.');
        }

        if ($slug === '') {
            $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($title)) ?? '';
            $slug = trim($slug, '-');
        }

        if (
            $slug === ''
            || mb_strlen($slug) > 160
            || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1
        ) {
            throw new InvalidArgumentException(
                'Page slug may contain lowercase letters, numbers, and single hyphens only.'
            );
        }

        if (CmsRoutePolicy::isReservedSlug($slug)) {
            throw new InvalidArgumentException(
                'This page URL slug is reserved by the Barama platform. Choose a different slug.'
            );
        }

        if (mb_strlen($excerpt) > 500) {
            throw new InvalidArgumentException('Page excerpt cannot exceed 500 characters.');
        }

        if (mb_strlen($content) > 100000) {
            throw new InvalidArgumentException('Page content cannot exceed 100,000 characters.');
        }

        if (mb_strlen($seoTitle) > 191) {
            throw new InvalidArgumentException('SEO title cannot exceed 191 characters.');
        }

        if (mb_strlen($seoDescription) > 300) {
            throw new InvalidArgumentException('SEO description cannot exceed 300 characters.');
        }

        if (!in_array($robots, ['index,follow', 'noindex,follow', 'noindex,nofollow'], true)) {
            throw new InvalidArgumentException('Choose a supported robots directive.');
        }

        return [
            'title' => $title,
            'slug' => $slug,
            'excerpt' => $excerpt !== '' ? $excerpt : null,
            'content_text' => $content,
            'seo_title' => $seoTitle !== '' ? $seoTitle : null,
            'seo_description' => $seoDescription !== '' ? $seoDescription : null,
            'robots_directive' => $robots,
        ];
    }
}
