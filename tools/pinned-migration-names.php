<?php

declare(strict_types=1);

// Canonical migrations from production source sync c28a294, ordered.
// This is independent of the runtime migration alias registry.
return [
    '0001_create_auth_core.php',
    '0002_seed_auth_roles_permissions.php',
    '0003_create_real_estate_domain.php',
    '0004_seed_real_estate_permissions.php',
    '0005_create_domain_workflow_support.php',
    '0006_create_customer_engagement.php',
    '0007_create_notification_delivery.php',
    '0008_create_application_settings.php',
    '0009_create_account_onboarding.php',
    '0010_release_security_hardening.php',
    '0011_create_workspace_preferences.php',
    '0012_create_cms_pages.php',
    '0013_create_billing_foundation.php',
    '0014_create_invoice_transaction_foundation.php',
    '0015_create_coupon_discount_foundation.php',
    '0016_create_tax_rule_foundation.php',
    '0017_create_payment_webhook_foundation.php',
    '0018_create_communication_permission.php',
    '0019_create_email_template_foundation.php',
    '0021_create_newsletter_subscribers.php',
    '0022_create_contact_messages.php',
    '0023_create_user_security_factors.php',
    '0024_create_role_permission_governance.php',
    '0025_create_email_template_localization_history.php',
    '0026_create_email_outbox_event_history.php',
    '0027_create_billing_payment_methods.php',
    '0028_create_global_location_governance.php',
    '0029_create_global_location_hierarchy.php',
    '0030_update_homepage_hero_copy.php',
    '0031_add_account_profile_details.php',
];
