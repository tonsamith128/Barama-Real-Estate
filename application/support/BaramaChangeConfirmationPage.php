<?php
declare(strict_types=1);

/**
 * Barama Change Confirmation Page v1.0.0
 * Presentation only. Security logic and HTTP status remain unchanged.
 */
if (!function_exists('barama_change_confirmation_page_matches')) {
    function barama_change_confirmation_page_matches(string $html): bool
    {
        if (
            strpos($html, 'Change confirmation required') === false ||
            strpos($html, 'Return to the previous page and confirm this change with your 6-digit passcode.') === false
        ) {
            return false;
        }

        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $path = parse_url($uri, PHP_URL_PATH);
        return is_string($path) && str_starts_with($path, '/dashboard/');
    }
}

if (!function_exists('barama_change_confirmation_page_render')) {
    function barama_change_confirmation_page_render(): string
    {
        return <<<'HTML'
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="color-scheme" content="light dark">
<meta name="robots" content="noindex,nofollow">
<title>Change confirmation required | Barama</title>
<script>
(function(){
  try{
    var keys=['barama-theme','barama_theme','theme','bs-theme','color-theme'];
    var theme=null;
    for(var i=0;i<keys.length;i++){
      var value=localStorage.getItem(keys[i]);
      if(value==='dark'||value==='light'){theme=value;break;}
    }
    if(!theme){
      var cookie=document.cookie.match(/(?:^|;\s*)(?:barama_theme|theme)=(dark|light)(?:;|$)/);
      if(cookie) theme=cookie[1];
    }
    if(!theme&&window.matchMedia){
      theme=window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light';
    }
    document.documentElement.setAttribute('data-bs-theme',theme||'light');
  }catch(e){}
}());
</script>
<link rel="stylesheet" href="/assets/css/change-confirmation-page.css">
</head>
<body>
<main class="bcc-page">
  <header class="bcc-topbar">
    <a class="bcc-brand" href="/" aria-label="Barama home">
      <span class="bcc-brand-mark" aria-hidden="true">B</span>
      <span class="bcc-brand-name">Barama</span>
    </a>
    <span class="bcc-security-label">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 19 6v5c0 4.5-2.8 8.2-7 10-4.2-1.8-7-5.5-7-10V6l7-3Z"/><path d="m9.25 12 1.75 1.75L15.25 9.5"/></svg>
      Secure confirmation
    </span>
  </header>

  <section class="bcc-shell" aria-labelledby="bcc-title">
    <article class="bcc-card">
      <div class="bcc-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24"><circle cx="8" cy="15" r="3"/><path d="m10.5 12.5 7-7"/><path d="M15 5h4v4"/><path d="M5 4h10a4 4 0 0 1 4 4v8a4 4 0 0 1-4 4H9"/></svg>
      </div>

      <p class="bcc-eyebrow">SECURITY CHECK</p>
      <h1 id="bcc-title">Change confirmation required</h1>
      <p class="bcc-lead">Return to the previous page and confirm this change with your 6-digit passcode.</p>

      <div class="bcc-code" aria-label="Six digit confirmation passcode">
        <span>•</span><span>•</span><span>•</span><span>•</span><span>•</span><span>•</span>
      </div>

      <div class="bcc-notice">
        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 10v6"/><path d="M12 7.25h.01"/></svg>
        <div>
          <strong>Your change has not been applied.</strong>
          <span>Complete the passcode confirmation on the previous screen, then submit the change again.</span>
        </div>
      </div>

      <div class="bcc-actions">
        <button class="bcc-btn bcc-btn-primary" type="button" data-barama-confirmation-back>
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
          Return to previous page
        </button>
        <a class="bcc-btn bcc-btn-secondary" href="/dashboard/policies">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h9l3 3v15H6z"/><path d="M15 3v4h4"/><path d="M9 11h6M9 15h6"/></svg>
          Policy Center
        </a>
      </div>

      <p class="bcc-footnote">This additional verification helps protect sensitive administrative changes.</p>
    </article>
  </section>
</main>
<script src="/assets/js/change-confirmation-page.js" defer></script>
</body>
</html>
HTML;
    }
}

if (!function_exists('barama_change_confirmation_page_buffer')) {
    function barama_change_confirmation_page_buffer(string $html): string
    {
        if (!barama_change_confirmation_page_matches($html)) {
            return $html;
        }
        return barama_change_confirmation_page_render();
    }
}
