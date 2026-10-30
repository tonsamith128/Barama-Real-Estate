# Acceptance gates

- PSR-4, PHP PSR-12, Laravel Pint, no duplicate versions or patch-marker source.
- No giant hand-maintained CSS/JS; two CSS entry points with named sections.
- php scripts/verify-structure.php succeeds.
- Composer install and Laravel feature tests succeed.
- npm build succeeds and emits two CSS entry-point bundles.
- Authorization, company isolation, session, email reset and security regression tests.
- Real shared-hosting clean installer and signed update/recovery tests.
- Preserve customer data and live URLs during verified migration.
- Human accessibility, light/dark, translations and responsive acceptance.
- No merge or live deployment before signed release acceptance.

