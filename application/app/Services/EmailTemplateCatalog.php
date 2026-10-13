<?php

declare(strict_types=1);

namespace Barama\Services;

final class EmailTemplateCatalog
{
    /** @return array<string,array<string,mixed>> */
    public static function definitions(): array
    {
        $common = ['app_name', 'app_url'];
        $notification = array_merge($common, ['title', 'message', 'action_url', 'recipient_name']);

        return [
            'inquiry_received' => self::definition(
                'Inquiry received',
                'Marketplace & CRM',
                'New property inquiry sent to the responsible company team.',
                $notification,
                'New marketplace inquiry · {{app_name}}',
                '<p>Hello {{recipient_name}},</p><p><strong>{{title}}</strong></p><p>{{message}}</p><p><a href="{{action_url}}">Open inquiry</a></p>',
                "Hello {{recipient_name}},\n\n{{title}}\n\n{{message}}\n\nOpen inquiry: {{action_url}}"
            ),
            'inquiry_replied' => self::definition(
                'Inquiry replied',
                'Marketplace & CRM',
                'Customer update when a property inquiry is marked replied.',
                $notification,
                '{{title}} · {{app_name}}',
                '<p>Hello {{recipient_name}},</p><p><strong>{{title}}</strong></p><p>{{message}}</p><p><a href="{{action_url}}">Review inquiry</a></p>',
                "Hello {{recipient_name}},\n\n{{title}}\n\n{{message}}\n\nReview inquiry: {{action_url}}"
            ),
            'inquiry_closed' => self::definition(
                'Inquiry closed',
                'Marketplace & CRM',
                'Customer update when a property inquiry is closed.',
                $notification,
                '{{title}} · {{app_name}}',
                '<p>Hello {{recipient_name}},</p><p><strong>{{title}}</strong></p><p>{{message}}</p><p><a href="{{action_url}}">View inquiries</a></p>',
                "Hello {{recipient_name}},\n\n{{title}}\n\n{{message}}\n\nView inquiries: {{action_url}}"
            ),
            'viewing_requested' => self::definition(
                'Viewing requested',
                'Marketplace & CRM',
                'Company notification when a customer requests a viewing.',
                $notification,
                '{{title}} · {{app_name}}',
                '<p>Hello {{recipient_name}},</p><p><strong>{{title}}</strong></p><p>{{message}}</p><p><a href="{{action_url}}">Open viewing request</a></p>',
                "Hello {{recipient_name}},\n\n{{title}}\n\n{{message}}\n\nOpen viewing request: {{action_url}}"
            ),
            'viewing_cancelled' => self::definition(
                'Viewing cancelled',
                'Marketplace & CRM',
                'Company notification when a customer cancels a viewing request.',
                $notification,
                '{{title}} · {{app_name}}',
                '<p>Hello {{recipient_name}},</p><p><strong>{{title}}</strong></p><p>{{message}}</p><p><a href="{{action_url}}">Review viewing</a></p>',
                "Hello {{recipient_name}},\n\n{{title}}\n\n{{message}}\n\nReview viewing: {{action_url}}"
            ),
            'viewing_confirmed' => self::definition(
                'Viewing confirmed',
                'Marketplace & CRM',
                'Customer notification when a viewing is confirmed.',
                $notification,
                '{{title}} · {{app_name}}',
                '<p>Hello {{recipient_name}},</p><p><strong>{{title}}</strong></p><p>{{message}}</p><p><a href="{{action_url}}">View appointment</a></p>',
                "Hello {{recipient_name}},\n\n{{title}}\n\n{{message}}\n\nView appointment: {{action_url}}"
            ),
            'viewing_rescheduled' => self::definition(
                'Viewing rescheduled',
                'Marketplace & CRM',
                'Customer notification when a viewing schedule changes.',
                $notification,
                '{{title}} · {{app_name}}',
                '<p>Hello {{recipient_name}},</p><p><strong>{{title}}</strong></p><p>{{message}}</p><p><a href="{{action_url}}">View appointment</a></p>',
                "Hello {{recipient_name}},\n\n{{title}}\n\n{{message}}\n\nView appointment: {{action_url}}"
            ),
            'viewing_completed' => self::definition(
                'Viewing completed',
                'Marketplace & CRM',
                'Customer notification when a viewing is marked completed.',
                $notification,
                '{{title}} · {{app_name}}',
                '<p>Hello {{recipient_name}},</p><p><strong>{{title}}</strong></p><p>{{message}}</p><p><a href="{{action_url}}">View appointment</a></p>',
                "Hello {{recipient_name}},\n\n{{title}}\n\n{{message}}\n\nView appointment: {{action_url}}"
            ),
            'viewing_declined' => self::definition(
                'Viewing declined',
                'Marketplace & CRM',
                'Customer notification when a company declines a viewing request.',
                $notification,
                '{{title}} · {{app_name}}',
                '<p>Hello {{recipient_name}},</p><p><strong>{{title}}</strong></p><p>{{message}}</p><p><a href="{{action_url}}">View request</a></p>',
                "Hello {{recipient_name}},\n\n{{title}}\n\n{{message}}\n\nView request: {{action_url}}"
            ),
            'saved_search_matches' => self::definition(
                'Saved search matches',
                'Marketplace & CRM',
                'Saved-search digest when new published properties match a customer alert.',
                array_merge($notification, ['search_name', 'match_count', 'matches_summary']),
                '{{title}} · {{app_name}}',
                '<p>Hello {{recipient_name}},</p><p><strong>{{title}}</strong></p><p>{{message}}</p><pre>{{matches_summary}}</pre><p><a href="{{action_url}}">View all current matches</a></p>',
                "Hello {{recipient_name}},\n\n{{title}}\n\n{{message}}\n\n{{matches_summary}}\n\nView all current matches: {{action_url}}"
            ),
            'company_invitation' => self::definition(
                'Company invitation',
                'Company & Membership',
                'Invitation to join a company workspace.',
                array_merge($common, ['company_name', 'member_role', 'action_url', 'expires_days']),
                'Company invitation · {{app_name}}',
                '<p><strong>You have been invited to {{company_name}}</strong></p><p>Role: {{member_role}}</p><p><a href="{{action_url}}">Review company invitation</a></p><p>This invitation expires in {{expires_days}} days.</p>',
                "You have been invited to {{company_name}}.\n\nRole: {{member_role}}\nReview invitation: {{action_url}}\n\nExpires in {{expires_days}} days."
            ),
            'company_approved' => self::definition(
                'Company approved',
                'Company & Membership',
                'Sent to active company owners when administrator approval enables company workspace access.',
                array_merge($common, ['action_url']),
                'Company approved · {{app_name}}',
                '<p><strong>Your company has been approved.</strong></p><p>Company / Vendor Workspace access is now active for your account.</p><p><a href="{{action_url}}">Open company workspace</a></p>',
                "Your company has been approved.\n\nOpen company workspace: {{action_url}}"
            ),
            'contact_form_admin' => self::definition(
                'Contact form received',
                'Communications',
                'Administrative email generated from the public Contact form.',
                array_merge($common, ['contact_name', 'contact_email', 'contact_phone', 'contact_subject', 'contact_message']),
                'Contact form: {{contact_subject}}',
                '<p><strong>New {{app_name}} contact message</strong></p><p><strong>From:</strong> {{contact_name}}<br><strong>Email:</strong> {{contact_email}}<br><strong>Phone:</strong> {{contact_phone}}<br><strong>Subject:</strong> {{contact_subject}}</p><pre>{{contact_message}}</pre>',
                "New {{app_name}} contact message\n\nFrom: {{contact_name}}\nEmail: {{contact_email}}\nPhone: {{contact_phone}}\nSubject: {{contact_subject}}\n\n{{contact_message}}"
            ),
            'auth_email_verification' => self::definition(
                'Email verification',
                'Account & Security',
                'Security-token email used to verify a user email address.',
                array_merge($common, ['recipient_name', 'action_url', 'expires_minutes']),
                'Verify your {{app_name}} email',
                '<p>Hello {{recipient_name}},</p><p><strong>Verify your email address</strong></p><p>Confirm this address to secure your account.</p><p><a href="{{action_url}}">Verify email address</a></p><p>This link expires in {{expires_minutes}} minutes.</p>',
                "Hello {{recipient_name}},\n\nVerify your email address: {{action_url}}\n\nThis link expires in {{expires_minutes}} minutes.",
                true
            ),
            'auth_password_reset' => self::definition(
                'Password reset',
                'Account & Security',
                'Security-token email used to reset an account password.',
                array_merge($common, ['recipient_name', 'action_url', 'expires_minutes']),
                'Reset your {{app_name}} password',
                '<p>Hello {{recipient_name}},</p><p>We received a request to reset your password.</p><p><a href="{{action_url}}">Reset password</a></p><p>This link expires in {{expires_minutes}} minutes.</p><p>If you did not request this, you can ignore this email.</p>',
                "Hello {{recipient_name}},\n\nReset password: {{action_url}}\n\nThis link expires in {{expires_minutes}} minutes.\nIf you did not request this, you can ignore this email.",
                true
            ),
            'account_email_change_confirmation' => self::definition(
                'Email change confirmation',
                'Account & Security',
                'Security-token email sent to a proposed new account email address.',
                array_merge($common, ['recipient_name', 'new_email', 'action_url', 'expires_minutes']),
                'Confirm your {{app_name}} email address',
                '<p>Hello {{recipient_name}},</p><p><strong>Confirm your new email address</strong></p><p>New email: {{new_email}}</p><p><a href="{{action_url}}">Confirm email address</a></p><p>This link expires in {{expires_minutes}} minutes.</p>',
                "Hello {{recipient_name}},\n\nConfirm your new email address {{new_email}}: {{action_url}}\n\nThis link expires in {{expires_minutes}} minutes.",
                true
            ),
            'security_hack_alert' => self::definition(
                'Hacking alert',
                'Account & Security',
                'Throttled administrator alert when Hack Protection blocks a suspicious request.',
                array_merge($common, ['title', 'reason', 'path', 'source_hash', 'action_url']),
                '{{title}}',
                '<p><strong>{{title}}</strong></p><p>Reason: {{reason}}<br>Path: {{path}}<br>Source hash: {{source_hash}}</p><p><a href="{{action_url}}">Review Security Audit</a></p>',
                "{{title}}\n\nReason: {{reason}}\nPath: {{path}}\nSource hash: {{source_hash}}\n\nReview Security Audit: {{action_url}}",
                true
            ),
        ];
    }

    /** @return array<string,string> */
    public static function labels(): array
    {
        $labels = [];
        foreach (self::definitions() as $key => $definition) {
            $labels[$key] = (string) $definition['label'];
        }
        return $labels;
    }

    /** @return array<string,mixed>|null */
    public static function get(string $key): ?array
    {
        $key = strtolower(trim($key));
        $definitions = self::definitions();
        return $definitions[$key] ?? null;
    }

    /** @return array<int,string> */
    public static function variables(string $key): array
    {
        $definition = self::get($key);
        return is_array($definition) && is_array($definition['variables'] ?? null)
            ? array_values(array_map('strval', $definition['variables']))
            : [];
    }

    /** @return array{name:string,description:string,subject:string,html:string,text:string} */
    public static function defaults(string $key): array
    {
        $definition = self::get($key);
        $defaults = is_array($definition['defaults'] ?? null) ? $definition['defaults'] : [];
        return [
            'name' => (string) ($defaults['name'] ?? 'Email template'),
            'description' => (string) ($defaults['description'] ?? ''),
            'subject' => (string) ($defaults['subject'] ?? '{{app_name}} notification'),
            'html' => (string) ($defaults['html'] ?? '<p>{{message}}</p>'),
            'text' => (string) ($defaults['text'] ?? '{{message}}'),
        ];
    }

    /** @return array<string,string> */
    public static function sampleVariables(string $key): array
    {
        $samples = [
            'app_name' => 'Barama Real Estate',
            'app_url' => 'https://example.com',
            'title' => 'Example notification',
            'message' => 'This is example content used only inside the Email Template Builder preview.',
            'action_url' => 'https://example.com/account',
            'recipient_name' => 'Samith',
            'search_name' => 'Battambang homes',
            'match_count' => '3',
            'matches_summary' => "River View Residence — USD 185,000\nGarden Villa — USD 240,000\nCity Apartment — USD 98,000",
            'company_name' => 'Barama Development',
            'member_role' => 'Manager',
            'expires_days' => '7',
            'contact_name' => 'Example Customer',
            'contact_email' => 'customer@example.com',
            'contact_phone' => '+855 12 345 678',
            'contact_subject' => 'Property information request',
            'contact_message' => 'I would like more information about this property.',
            'expires_minutes' => '60',
            'new_email' => 'new-email@example.com',
            'reason' => 'path probe',
            'path' => '/.env',
            'source_hash' => '2f7d98f0d73f819a',
        ];

        $result = [];
        foreach (self::variables($key) as $variable) {
            $result[$variable] = $samples[$variable] ?? ('Example ' . str_replace('_', ' ', $variable));
        }
        return $result;
    }

    /** @param array<int,string> $variables */
    private static function definition(
        string $label,
        string $group,
        string $description,
        array $variables,
        string $subject,
        string $html,
        string $text,
        bool $securitySensitive = false
    ): array {
        return [
            'label' => $label,
            'group' => $group,
            'description' => $description,
            'variables' => array_values(array_unique($variables)),
            'security_sensitive' => $securitySensitive,
            'defaults' => [
                'name' => $label . ' email',
                'description' => 'Custom email override for ' . strtolower($label) . '.',
                'subject' => $subject,
                'html' => $html,
                'text' => $text,
            ],
        ];
    }
}
