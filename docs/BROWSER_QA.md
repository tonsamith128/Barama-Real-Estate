# Browser, Device & Accessibility QA

Barama Real Estate runs browser QA against the exact generated customer ZIP across Chromium, Firefox, and WebKit.

## Automated coverage

The browser gate uses Chromium in GitHub Actions only. Node.js and Playwright are installed into temporary CI storage and are not committed or shipped to customers.

The deep Chromium gate validates:

- fresh root redirect to /installer/
- installer behavior at mobile, tablet, and desktop widths
- installer Terms modal open/close with Escape and focus restoration
- Light/Dark toggle and persisted preference
- public homepage, properties, projects, companies, login, and register pages
- authenticated account, developer workspace, and admin dashboard
- mobile global navigation aria-expanded state and Escape close
- mobile workspace drawer aria-expanded state, Escape close, and focus restoration
- auth password visibility controls
- canonical visible keyboard focus treatment
- no horizontal overflow at tested viewports
- serious/critical WCAG 2.0/2.1 A/AA violations using axe-core
- screenshots for installer, homepage Light/Dark, account, developer workspace, and dashboard

Firefox and WebKit additionally validate the critical customer contract:

- fresh root redirect to /installer/
- installer and public pages at mobile and desktop widths
- no horizontal overflow
- mobile navigation open/close state and Escape behavior
- Light/Dark theme toggle and persisted preference
- administrator login
- authenticated account, developer workspace, and admin dashboard routing

## Tested viewports

- mobile: 390 x 844
- tablet: 768 x 1024
- desktop: 1440 x 900

## Customer package rule

The customer ZIP contains only the canonical application stylesheet and JavaScript. Browser QA scripts, Playwright, axe-core, npm metadata, screenshots, and test database state are excluded.

## Manual release review

Automated browser QA is a release gate, but final v1.0.0 still requires a human visual review on representative desktop, tablet, and mobile browsers. WebKit is a strong compatibility signal but is not a substitute for final Safari/iOS device acceptance; Chromium coverage likewise does not replace a final Edge/Chrome visual pass.
