# Barama Real Estate — Clean URLs on Nginx and aaPanel

Barama requires **clean application URLs** for new installations, e.g. `/login`, `/register`, `/forgot-password`, `/properties`, and `/dashboard`. There is no need to reinstall or reset the database when correcting a rewrite.

## Apache hosting

The customer package includes a root `.htaccess`, which enables clean routes when Apache has `mod_rewrite` and allows `.htaccess` overrides.

## Nginx hosting (including aaPanel)

Nginx **does not read** `.htaccess`. PHP cannot turn a missing Nginx rewrite rule on by itself.

In **aaPanel → Website → your domain → URL rewrite**, preserve any existing access restrictions and PHP-FPM configuration. Configure the site's *existing* root `location /` block with the following front-controller rule. Do not add a second duplicate `location /`:

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

Save through the hosting panel, validate the resulting Nginx configuration, and reload Nginx only if validation passes. The website must deny public access to the `application` source and runtime storage, `tools`, `docs`, and repository metadata. Do not use this short snippet as a replacement for the entire virtual-host configuration.

On hosting installed in a subdirectory, the front controller and fallback location must be adjusted to the deployment path.

## Verify before installation

Open these URLs directly in a browser:
- `https://YOUR-DOMAIN/login`
- `https://YOUR-DOMAIN/register`
- `https://YOUR-DOMAIN/forgot-password`

**Before installation**, these URLs should reach Barama's installer redirect instead of a hosting 404. **After installation**, they should render the premium authentication pages.

Return to **Installer → Step 2 — Requirements** and refresh. "Application routing" must display **Pass** before continuing. A successful new install stores `APP_ROUTING_MODE=clean`.

## Existing installations

Do not manually edit the protected runtime file or remove a working legacy configuration without verifying the clean URLs. Existing query-routing installations must be migrated separately, with a private backup, after the host has been configured. Existing customers must never be silently broken by an updater.
