<?php
declare(strict_types=1);

/**
 * Barama Session Expired Page v1.0.1
 * Presentation-only refinement.
 * CSRF/session/auth/passcode behavior is unchanged.
 */

if (!function_exists('barama_session_expired_page_matches')) {
    function barama_session_expired_page_matches(string $html): bool
    {
        if (http_response_code() !== 419) {
            return false;
        }

        if (
            strpos($html, 'Your session has expired.') === false ||
            strpos(
                $html,
                'Refresh the page and submit the form again. This protection helps prevent unauthorized requests.'
            ) === false
        ) {
            return false;
        }

        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);

        return is_string($path) && str_starts_with($path, '/dashboard/');
    }
}

if (!function_exists('barama_session_expired_page_render')) {
    function barama_session_expired_page_render(): string
    {
        return <<<'HTML'
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="color-scheme" content="light dark">
<meta name="robots" content="noindex,nofollow">
<title>Session expired | Barama</title>
<script>
(function(){
  try{
    var keys=['barama-theme','barama_theme','theme','bs-theme','color-theme'],theme=null;
    for(var i=0;i<keys.length;i++){
      var v=localStorage.getItem(keys[i]);
      if(v==='dark'||v==='light'){theme=v;break;}
    }
    if(!theme){
      var m=document.cookie.match(/(?:^|;\s*)(?:barama_theme|theme)=(dark|light)(?:;|$)/);
      if(m) theme=m[1];
    }
    if(!theme&&window.matchMedia){
      theme=window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light';
    }
    document.documentElement.setAttribute('data-bs-theme',theme||'light');
  }catch(e){}
}());
</script>
<link rel="stylesheet" href="/assets/css/session-expired.css">
</head>
<body>
<main class="bse-page">
  <header class="bse-topbar">
    <a class="bse-brand" href="/" aria-label="Barama home">
      <span class="bse-mark">B</span><span>Barama</span>
    </a>
    <span class="bse-secure">
      <svg viewBox="0 0 24 24" aria-hidden="true">
        <path d="M12 3 19 6v5c0 4.5-2.8 8.2-7 10-4.2-1.8-7-5.5-7-10V6l7-3Z"/>
        <path d="m9.25 12 1.75 1.75L15.25 9.5"/>
      </svg>
      Protected session
    </span>
  </header>

  <section class="bse-shell" aria-labelledby="bse-title">
    <article class="bse-card">
      <div class="bse-meta-row">
        <span class="bse-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="8"/>
            <path d="M12 7v5l3 2"/>
          </svg>
        </span>
        <span class="bse-status">HTTP 419</span>
      </div>

      <p class="bse-kicker">SESSION SECURITY</p>
      <h1 id="bse-title">Your session has expired.</h1>
      <p class="bse-lead">
        Refresh or reopen the editor before submitting again so Barama can issue a fresh security token.
      </p>

      <div class="bse-note">
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <circle cx="12" cy="12" r="9"/>
          <path d="M12 10v6M12 7.25h.01"/>
        </svg>
        <div>
          <strong>Your change was not applied.</strong>
          <span>
            This protection helps prevent unauthorized requests. Policy data remains unchanged until you resubmit from a fresh authenticated session.
          </span>
        </div>
      </div>

      <div class="bse-actions">
        <button type="button" class="bse-btn bse-primary" data-barama-session-back>
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
          Return to previous page
        </button>

        <a class="bse-btn bse-secondary" href="/login">
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M10 17l5-5-5-5M15 12H3M14 4h5a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-5"/>
          </svg>
          Sign in again
        </a>
      </div>

      <div class="bse-foot">
        <span>Session and CSRF protection remain active.</span>
        <span aria-hidden="true">•</span>
        <span>No changes were saved.</span>
      </div>
    </article>
  </section>
</main>
<script src="/assets/js/session-expired.js" defer></script>
</body>
</html>
HTML;
    }
}

if (!function_exists('barama_session_expired_page_buffer')) {
    function barama_session_expired_page_buffer(string $html): string
    {
        return barama_session_expired_page_matches($html)
            ? barama_session_expired_page_render()
            : $html;
    }
}
