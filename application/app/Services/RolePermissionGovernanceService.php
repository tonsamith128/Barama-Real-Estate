<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Repositories\RolePermissionGovernanceRepository;
use DomainException;
use InvalidArgumentException;

final class RolePermissionGovernanceService
{
    private const ROLE_ORDER = [
        'user',
        'developer',
        'company',
        'administrator',
        'super-administrator',
    ];

    private const ROLE_META = [
        'user' => [
            'name' => 'User',
            'scope' => 'Customer account',
            'description' => 'Standard customer access for profile, saved activity, inquiries and account settings.',
            'icon' => 'user',
        ],
        'developer' => [
            'name' => 'Developer',
            'scope' => 'Developer workspace',
            'description' => 'Property-development operations including companies, projects, properties, listings and CRM workflows.',
            'icon' => 'building',
        ],
        'company' => [
            'name' => 'Company',
            'scope' => 'Company operations',
            'description' => 'Company or agency operations for properties, listings, media, inquiries and leads without project-development authority.',
            'icon' => 'building',
        ],
        'administrator' => [
            'name' => 'Administrator',
            'scope' => 'Admin Control Center',
            'description' => 'Platform administration across marketplace, content, communications, finance, users and security.',
            'icon' => 'shield',
        ],
        'super-administrator' => [
            'name' => 'Super Administrator',
            'scope' => 'Full platform authority',
            'description' => 'Immutable full access, including Roles & Permissions governance and all current platform permissions.',
            'icon' => 'key',
        ],
    ];

    private const REQUIRED = [
        'user' => ['account.access'],
        'developer' => ['developer.access'],
        'company' => ['account.access', 'developer.access'],
        'administrator' => ['dashboard.access'],
        'super-administrator' => [],
    ];

    private const GROUPS = [
        'Workspace Access' => ['account.access', 'developer.access', 'dashboard.access'],
        'Marketplace & CRM' => [
            'company.manage', 'project.manage', 'property.manage', 'listing.manage',
            'media.manage', 'inquiry.manage', 'lead.manage',
        ],
        'Platform Data' => ['location.manage', 'amenity.manage'],
        'Content & Communications' => ['page.manage', 'communication.manage'],
        'Finance' => ['billing.manage'],
        'Governance' => ['roles.manage'],
    ];

    private const DESCRIPTIONS = [
        'account.access' => 'Open the customer/account workspace.',
        'developer.access' => 'Open the Company / Developer workspace.',
        'dashboard.access' => 'Open the Admin Control Center.',
        'company.manage' => 'Manage company records and company membership workflows.',
        'project.manage' => 'Create and manage development projects.',
        'property.manage' => 'Create and manage property inventory.',
        'listing.manage' => 'Create and manage sale/rental listings.',
        'media.manage' => 'Upload and manage real-estate media.',
        'inquiry.manage' => 'Manage property inquiries and viewing workflows.',
        'lead.manage' => 'Manage property lead pipelines.',
        'location.manage' => 'Manage canonical locations.',
        'amenity.manage' => 'Manage canonical amenities.',
        'page.manage' => 'Manage CMS pages, appearance and public-site content.',
        'communication.manage' => 'Manage notifications, email templates and mail operations.',
        'billing.manage' => 'Manage plans, subscriptions, coupons, taxes, payment providers and billing.',
        'roles.manage' => 'Manage role permission assignments. Super Administrator only.',
    ];

    private RolePermissionGovernanceRepository $repository;

    public function __construct(?RolePermissionGovernanceRepository $repository = null)
    {
        $this->repository = $repository ?? new RolePermissionGovernanceRepository();
    }

    /** @return array<string,mixed> */
    public function viewModel(): array
    {
        $rolesBySlug = [];
        foreach ($this->repository->roles() as $role) {
            $rolesBySlug[(string) ($role['slug'] ?? '')] = $role;
        }

        $permissions = $this->repository->permissions();
        $permissionMap = $this->repository->permissionMap();
        $allPermissionSlugs = array_values(array_map(
            static fn (array $permission): string => (string) $permission['slug'],
            $permissions
        ));

        $auth = new AuthService();
        $canManage = $auth->check()
            && $auth->hasRole('super-administrator')
            && $auth->can('roles.manage');

        $roles = [];
        foreach (self::ROLE_ORDER as $slug) {
            $record = $rolesBySlug[$slug] ?? [
                'name' => self::ROLE_META[$slug]['name'],
                'slug' => $slug,
                'member_count' => 0,
                'active_member_count' => 0,
            ];
            $assigned = $slug === 'super-administrator'
                ? $allPermissionSlugs
                : array_values(array_unique($permissionMap[$slug] ?? []));
            sort($assigned, SORT_STRING);

            $roles[] = array_merge(self::ROLE_META[$slug], [
                'slug' => $slug,
                'member_count' => (int) ($record['member_count'] ?? 0),
                'active_member_count' => (int) ($record['active_member_count'] ?? 0),
                'permissions' => $assigned,
                'permission_count' => count($assigned),
                'locked' => $slug === 'super-administrator',
                'editable' => $canManage && $slug !== 'super-administrator',
                'required' => self::REQUIRED[$slug],
            ]);
        }

        $grouped = [];
        $bySlug = [];
        foreach ($permissions as $permission) {
            $slug = (string) ($permission['slug'] ?? '');
            $bySlug[$slug] = [
                'name' => (string) ($permission['name'] ?? $slug),
                'slug' => $slug,
                'description' => self::DESCRIPTIONS[$slug] ?? 'Platform permission.',
            ];
        }

        $used = [];
        foreach (self::GROUPS as $group => $slugs) {
            foreach ($slugs as $slug) {
                if (isset($bySlug[$slug])) {
                    $grouped[$group][] = $bySlug[$slug];
                    $used[$slug] = true;
                }
            }
        }
        foreach ($bySlug as $slug => $permission) {
            if (!isset($used[$slug])) {
                $grouped['Other Permissions'][] = $permission;
            }
        }

        return [
            'roles' => $roles,
            'permissions' => $permissions,
            'groups' => $grouped,
            'can_manage' => $canManage,
            'role_count' => count($roles),
            'permission_count' => count($permissions),
            'assignment_count' => array_sum(array_map(
                static fn (array $role): int => (int) $role['member_count'],
                $roles
            )),
        ];
    }

    /** @param array<string,mixed> $input */
    public function update(string $roleSlug, array $input): void
    {
        $auth = new AuthService();
        if (
            !$auth->check()
            || !$auth->hasRole('super-administrator')
            || !$auth->can('roles.manage')
        ) {
            throw new DomainException('Super Administrator access is required to change role permissions.');
        }

        $roleSlug = strtolower(trim($roleSlug));
        if (!in_array($roleSlug, self::ROLE_ORDER, true)) {
            throw new InvalidArgumentException('Select a valid system role.');
        }
        if ($roleSlug === 'super-administrator') {
            throw new DomainException('Super Administrator permissions are immutable and always include full platform access.');
        }

        $available = array_fill_keys(array_map(
            static fn (array $permission): string => (string) $permission['slug'],
            $this->repository->permissions()
        ), true);
        $requested = $input['permissions'] ?? [];
        $requested = is_array($requested) ? $requested : [];
        $selected = [];
        foreach ($requested as $slug) {
            $slug = strtolower(trim((string) $slug));
            if ($slug !== '' && isset($available[$slug]) && $slug !== 'roles.manage') {
                $selected[$slug] = true;
            }
        }

        foreach (self::REQUIRED[$roleSlug] as $required) {
            if (isset($available[$required])) {
                $selected[$required] = true;
            }
        }

        $permissionSlugs = array_keys($selected);
        sort($permissionSlugs, SORT_STRING);
        $this->repository->syncPermissions($roleSlug, $permissionSlugs);

        $actor = $auth->user();
        (new SecurityAuditService())->record(
            'admin.role_permissions_updated',
            isset($actor['id']) ? (int) $actor['id'] : null,
            null,
            [
                'role' => $roleSlug,
                'permission_count' => count($permissionSlugs),
            ]
        );
    }
}
