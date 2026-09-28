# Barama Real Estate — Built-in About & Contact v1.0.0

Guarded aaPanel deployment bundle for PR #27.

## Source binding

- Repository: bambodia168-sys/Barama-Real-Estate
- PR: #27
- Base main: 9344d327864fa6145f60c7116934174a60893647
- PR head: 4dba6e29b71cc8ce7c97bd70e25b21d836a822eb
- Live target: /www/wwwroot/baramarealestate.tonsamith.com

## What this deploys

- Dedicated Admin About manager at /dashboard/about
- Dedicated Admin Contact manager at /dashboard/contact
- Structured About sections: Hero, Story, Mission & Vision, Values, Stats, Team, CTA
- Structured Contact sections: Hero, Channels, Office, Hours, Form, FAQ, Socials
- Public /about and /contact pages
- Contact inbox workflow: New, Read, Resolved, Spam
- Secure contact form with CSRF, honeypot, throttling, validation and consent
- Reserved About/Contact CMS slug separation and legacy redirects
- Sitemap and navigation integration
- Migration 0022_create_contact_messages.php
- Responsive premium public/admin UI

## Safety

install.sh first verifies SHA256SUMS and performs an exact zero-fuzz dry-run of the PR patch against the live webroot. If the baseline does not match, installation stops before changing live files.

Touched live files are backed up under:

/root/barama-real-estate-backups/builtin-about-contact-v100-<UTC timestamp>

The generated rollback.sh restores file state. The additive contact_messages table and migration are intentionally retained during rollback to avoid destroying submitted contact data or leaving schema history inconsistent.

## Run

WEBROOT="/www/wwwroot/baramarealestate.tonsamith.com" ./install.sh

After successful deployment, hard-refresh the browser and inspect:

- /dashboard/about
- /dashboard/contact
- /about
- /contact
