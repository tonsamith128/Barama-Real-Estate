<?php

declare(strict_types=1);

namespace Barama\Repositories;

use Barama\Core\Database;
use Barama\Support\IdGenerator;
use PDO;

final class AccountSettingsRepository
{
    /** @return array<string,mixed> */
    public function profile(int $userId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT u.id, u.name, u.email, u.status, u.email_verified_at,
                    u.last_login_at, u.created_at,
                    p.phone, p.date_of_birth, p.address, p.avatar_media_id,
                    p.timezone, p.preferred_language, p.remember_recent_views,
                    avatar.uuid AS avatar_uuid
             FROM users u
             LEFT JOIN user_profiles p ON p.user_id = u.id
             LEFT JOIN media_assets avatar
                ON avatar.id = p.avatar_media_id
               AND avatar.mime_type IN (\'image/jpeg\', \'image/png\', \'image/webp\')
             WHERE u.id = :user_id
             LIMIT 1'
        );
        $statement->execute(['user_id' => $userId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : [];
    }

    public function saveProfile(
        int $userId,
        string $name,
        ?string $phone,
        ?string $dateOfBirth,
        ?string $address,
        string $timezone,
        string $language
    ): void {
        $pdo = Database::connection();
        $update = $pdo->prepare(
            'UPDATE users SET name = :name, updated_at = CURRENT_TIMESTAMP WHERE id = :id'
        );
        $update->execute(['name' => $name, 'id' => $userId]);

        $profile = $pdo->prepare(
            'INSERT INTO user_profiles
                (user_id, phone, date_of_birth, address, timezone, preferred_language)
             VALUES
                (:user_id, :phone, :date_of_birth, :address, :timezone, :language)
             ON DUPLICATE KEY UPDATE
                phone = VALUES(phone),
                date_of_birth = VALUES(date_of_birth),
                address = VALUES(address),
                timezone = VALUES(timezone),
                preferred_language = VALUES(preferred_language),
                updated_at = CURRENT_TIMESTAMP'
        );
        $profile->execute([
            'user_id' => $userId,
            'phone' => $phone,
            'date_of_birth' => $dateOfBirth,
            'address' => $address,
            'timezone' => $timezone,
            'language' => $language,
        ]);
    }

    /** @return array<string,mixed>|null */
    public function avatarMediaForUser(int $userId, string $uuid): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT m.*
             FROM user_profiles p
             INNER JOIN media_assets m ON m.id = p.avatar_media_id
             WHERE p.user_id = :user_id
               AND m.uuid = :uuid
               AND m.disk <> "external"
               AND m.mime_type IN ("image/jpeg", "image/png", "image/webp")
             LIMIT 1'
        );
        $statement->execute([
            'user_id' => $userId,
            'uuid' => trim($uuid),
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function setAvatarMediaId(int $userId, ?int $mediaId): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO user_profiles (user_id, avatar_media_id)
             VALUES (:user_id, :avatar_media_id)
             ON DUPLICATE KEY UPDATE
                avatar_media_id = VALUES(avatar_media_id),
                updated_at = CURRENT_TIMESTAMP'
        );
        $statement->execute([
            'user_id' => $userId,
            'avatar_media_id' => $mediaId,
        ]);
    }

    public function preferredLanguage(int $userId): ?string
    {
        $statement = Database::connection()->prepare(
            'SELECT preferred_language
             FROM user_profiles
             WHERE user_id = :user_id
             LIMIT 1'
        );
        $statement->execute(['user_id' => $userId]);
        $language = $statement->fetchColumn();

        return is_string($language) && $language !== '' ? $language : null;
    }

    public function savePreferredLanguage(int $userId, string $language): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO user_profiles (user_id, preferred_language)
             VALUES (:user_id, :preferred_language)
             ON DUPLICATE KEY UPDATE
                preferred_language = VALUES(preferred_language),
                updated_at = CURRENT_TIMESTAMP'
        );
        $statement->execute([
            'user_id' => $userId,
            'preferred_language' => $language,
        ]);
    }

    public function savePrivacy(int $userId, bool $rememberRecentViews): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO user_profiles (user_id, remember_recent_views)
             VALUES (:user_id, :remember_recent_views)
             ON DUPLICATE KEY UPDATE
                remember_recent_views = VALUES(remember_recent_views),
                updated_at = CURRENT_TIMESTAMP'
        );
        $statement->execute([
            'user_id' => $userId,
            'remember_recent_views' => $rememberRecentViews ? 1 : 0,
        ]);
    }

    public function clearRecentViews(int $userId): void
    {
        $statement = Database::connection()->prepare(
            'DELETE FROM recent_listing_views WHERE user_id = :user_id'
        );
        $statement->execute(['user_id' => $userId]);
    }

    public function clearComparisonItems(int $userId): void
    {
        $statement = Database::connection()->prepare(
            'DELETE FROM comparison_items WHERE user_id = :user_id'
        );
        $statement->execute(['user_id' => $userId]);
    }


    public function updateUserEmail(int $userId, string $email): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE users
             SET email = :email,
                 email_verified_at = CURRENT_TIMESTAMP,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $statement->execute(['email' => strtolower($email), 'id' => $userId]);
    }

    public function setUserStatus(int $userId, string $status): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE users SET status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id'
        );
        $statement->execute(['status' => $status, 'id' => $userId]);
    }

    public function createEmailChangeToken(
        int $userId,
        string $newEmail,
        string $tokenHash,
        string $expiresAt
    ): void {
        $pdo = Database::connection();
        $pdo->prepare(
            'UPDATE email_change_tokens
             SET used_at = CURRENT_TIMESTAMP
             WHERE user_id = :user_id AND used_at IS NULL'
        )->execute(['user_id' => $userId]);

        $statement = $pdo->prepare(
            'INSERT INTO email_change_tokens
                (user_id, new_email, token_hash, expires_at)
             VALUES
                (:user_id, :new_email, :token_hash, :expires_at)'
        );
        $statement->execute([
            'user_id' => $userId,
            'new_email' => strtolower($newEmail),
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
        ]);
    }

    /** @return array<string,mixed>|null */
    public function validEmailChangeToken(string $tokenHash, int $userId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT *
             FROM email_change_tokens
             WHERE token_hash = :token_hash
               AND user_id = :user_id
               AND used_at IS NULL
               AND expires_at > CURRENT_TIMESTAMP
             LIMIT 1'
        );
        $statement->execute(['token_hash' => $tokenHash, 'user_id' => $userId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function markEmailChangeUsed(int $id): void
    {
        Database::connection()->prepare(
            'UPDATE email_change_tokens SET used_at = CURRENT_TIMESTAMP WHERE id = :id'
        )->execute(['id' => $id]);
    }

    public function activeOwnerCompanyCount(int $userId): int
    {
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*)
             FROM company_members cm
             INNER JOIN companies c ON c.id = cm.company_id
             WHERE cm.user_id = :user_id
               AND cm.member_role = "owner"
               AND cm.status = "active"
               AND c.deleted_at IS NULL
               AND c.status NOT IN ("archived", "rejected")'
        );
        $statement->execute(['user_id' => $userId]);

        return (int) $statement->fetchColumn();
    }

    /** @return array<int,array<string,mixed>> */
    public function ownedCompanies(int $userId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT c.id, c.name, c.company_type, c.status, c.email, c.phone,
                    c.updated_at, cm.member_role
             FROM companies c
             INNER JOIN company_members cm ON cm.company_id = c.id
             WHERE cm.user_id = :user_id
               AND cm.member_role = "owner"
               AND cm.status = "active"
               AND c.deleted_at IS NULL
             ORDER BY c.updated_at DESC'
        );
        $statement->execute(['user_id' => $userId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function ownedCompaniesForAccess(int $userId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT c.id, c.name, c.company_type, c.status, c.email, c.phone,
                    c.updated_at, cm.member_role, loc.name AS location_name,
                    (
                        SELECT COUNT(*)
                        FROM projects p
                        WHERE p.company_id = c.id
                          AND p.deleted_at IS NULL
                    ) AS project_count,
                    (
                        SELECT COUNT(*)
                        FROM properties pr
                        WHERE pr.company_id = c.id
                          AND pr.deleted_at IS NULL
                    ) AS property_count,
                    (
                        SELECT COUNT(*)
                        FROM listings l
                        INNER JOIN properties lp ON lp.id = l.property_id
                        WHERE lp.company_id = c.id
                          AND l.deleted_at IS NULL
                          AND lp.deleted_at IS NULL
                    ) AS listing_count
             FROM companies c
             INNER JOIN company_members cm ON cm.company_id = c.id
             LEFT JOIN locations loc ON loc.id = c.location_id
             WHERE cm.user_id = :user_id
               AND cm.member_role = "owner"
               AND cm.status = "active"
               AND c.deleted_at IS NULL
             ORDER BY c.updated_at DESC'
        );
        $statement->execute(['user_id' => $userId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int,array<string,mixed>> */
    public function invitationsForEmail(string $email): array
    {
        $statement = Database::connection()->prepare(
            'SELECT i.id, i.company_id, i.member_role, i.status, i.expires_at,
                    i.created_at, c.name AS company_name,
                    u.name AS invited_by_name
             FROM company_invitations i
             INNER JOIN companies c ON c.id = i.company_id
             INNER JOIN users u ON u.id = i.invited_by_user_id
             WHERE i.email = :email
               AND i.status = "pending"
               AND i.expires_at > CURRENT_TIMESTAMP
               AND c.deleted_at IS NULL
             ORDER BY i.created_at DESC'
        );
        $statement->execute(['email' => strtolower(trim($email))]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createInvitation(
        int $companyId,
        int $invitedByUserId,
        string $email,
        string $memberRole,
        string $expiresAt
    ): int {
        $pdo = Database::connection();

        $existing = $pdo->prepare(
            'SELECT id
             FROM company_invitations
             WHERE company_id = :company_id
               AND email = :email
               AND status = "pending"
               AND expires_at > CURRENT_TIMESTAMP
             LIMIT 1'
        );
        $existing->execute([
            'company_id' => $companyId,
            'email' => strtolower($email),
        ]);
        $id = $existing->fetchColumn();

        if ($id !== false) {
            $pdo->prepare(
                'UPDATE company_invitations
                 SET invited_by_user_id = :invited_by_user_id,
                     member_role = :member_role,
                     expires_at = :expires_at,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id'
            )->execute([
                'invited_by_user_id' => $invitedByUserId,
                'member_role' => $memberRole,
                'expires_at' => $expiresAt,
                'id' => (int) $id,
            ]);

            return (int) $id;
        }

        $statement = $pdo->prepare(
            'INSERT INTO company_invitations
                (uuid, company_id, invited_by_user_id, email, member_role, expires_at)
             VALUES
                (:uuid, :company_id, :invited_by_user_id, :email, :member_role, :expires_at)'
        );
        $statement->execute([
            'uuid' => IdGenerator::uuid(),
            'company_id' => $companyId,
            'invited_by_user_id' => $invitedByUserId,
            'email' => strtolower($email),
            'member_role' => $memberRole,
            'expires_at' => $expiresAt,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /** @return array<string,mixed>|null */
    public function pendingInvitation(int $invitationId, string $email): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT i.*, c.name AS company_name, c.status AS company_status
             FROM company_invitations i
             INNER JOIN companies c ON c.id = i.company_id
             WHERE i.id = :id
               AND i.email = :email
               AND i.status = "pending"
               AND i.expires_at > CURRENT_TIMESTAMP
               AND c.deleted_at IS NULL
             LIMIT 1'
        );
        $statement->execute([
            'id' => $invitationId,
            'email' => strtolower($email),
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function acceptInvitation(int $invitationId, int $userId): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE company_invitations
             SET status = "accepted",
                 accepted_by_user_id = :user_id,
                 accepted_at = CURRENT_TIMESTAMP,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND status = "pending"'
        );
        $statement->execute(['user_id' => $userId, 'id' => $invitationId]);
    }

    public function revokeInvitation(int $invitationId, int $companyId): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE company_invitations
             SET status = "revoked", revoked_at = CURRENT_TIMESTAMP,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND company_id = :company_id AND status = "pending"'
        );
        $statement->execute(['id' => $invitationId, 'company_id' => $companyId]);
    }
}
