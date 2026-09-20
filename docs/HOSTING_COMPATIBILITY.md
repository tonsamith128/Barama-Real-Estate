# Hosting Compatibility

Barama Real Estate supports Apache 2.4+ and Nginx when the hosting environment routes application URLs to the canonical `index.php` front controller.

## Customer goal

For supported hosting, the customer flow remains:

Upload files → extract → open the main domain → installer.

The customer is not expected to edit application PHP files, create a `.env`, run migrations manually, or understand the application document root.

## Apache

The exact customer package includes the canonical root `.htaccess`.

It:

- disables directory indexes
- uses `index.php` as the directory index
- denies direct public access to application, tools, docs, and repository metadata
- preserves real files and directories
- routes other requests to the root front controller
- supports installation in the domain root or a subdirectory when the host permits `.htaccess` overrides and `mod_rewrite`

GitHub release QA runs the exact generated customer ZIP in an Apache 2.4 container with `mod_rewrite` and `AllowOverride All`. It validates both domain-root and subdirectory behavior.

## Nginx

Nginx does not read `.htaccess`, and PHP cannot safely rewrite an arbitrary Nginx vhost configuration from inside a web application.

Supported Nginx hosting therefore requires the host or hosting panel to provide standard front-controller routing equivalent to:

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

Protected application paths must not be served as static files.

This is normally a hosting/vhost capability rather than a customer application setting. The live-server acceptance test must confirm the selected hosting environment already provides or safely applies this routing before Barama is described as one-click on that environment.

GitHub release QA runs the exact customer ZIP behind Nginx and PHP-FPM using this standard front-controller contract at both the domain root and a configured subdirectory.

## Subdirectory routing

The application request layer normalizes the hosting base path before route matching.

Examples:

- `/properties` → `/properties`
- `/barama/properties` with `/barama/index.php` → `/properties`
- `/barama/` with `/barama/index.php` → `/`

This keeps the route table canonical and avoids maintaining duplicate root/subdirectory routes.

## Release boundary

Automated container QA is a pre-release compatibility gate, not a substitute for the final live hosting test.

Before CodeCanyon v1.0.0, the exact release ZIP must still be installed on the intended live Apache/Nginx hosting environment and exercised through the real five-step installer, license service, HTTPS configuration, PHP-FPM/runtime permissions, and rewrite behavior.
