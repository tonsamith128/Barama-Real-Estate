# Localization

Barama Real Estate uses one canonical localization layer. Language support does not create duplicate controllers, views, stylesheets, or JavaScript bundles.

## Supported locales

- `en` — English
- `km` — ខ្មែរ
- `zh` — 中文

`Barama\Support\Locale` is the single source of truth for supported locale codes and labels. The installer, account profile, runtime helpers, and locale switcher consume this list.

## Resolution and persistence

1. A valid session locale is used first.
2. Otherwise the configured `APP_LOCALE` is used.
3. Unsupported configured values fall back to English.
4. A guest language change is stored in the session.
5. A signed-in language change is also stored in the existing `user_profiles.preferred_language` field.
6. On login or an existing authenticated session without a locale, the saved profile language is restored when it is supported.

Locale switching uses a CSRF-protected POST request and accepts only the canonical supported locale list.

## Translation catalogs

Catalogs live in:

- `application/resources/lang/en.php`
- `application/resources/lang/km.php`
- `application/resources/lang/zh.php`

Views call `t('English source key')`. Missing entries fall back to the English catalog and then to the key itself. All catalogs must expose the same key set.

The initial localization milestone covers the shared application shell, navigation, footer, login, and registration surfaces. Page-specific marketplace, account, developer, and administration copy remains subject to final human translation review before v1.0.0.

## Installer contract

Step 4 uses the same supported locale source for the website default language. Installation writes the selected value to protected runtime configuration as `APP_LOCALE`.

## QA

GitHub Actions verifies:

- supported locale codes and labels
- catalog existence and key parity
- Khmer and Chinese translation output
- locale normalization and English fallback
- rejection of unsupported locale mutations
- exact customer ZIP inclusion of runtime locale files
- exact-package installer language options
- document `lang` changes and rendered Khmer/Chinese navigation in Chromium

Final native-browser visual review and page-specific translation quality remain manual release gates.
