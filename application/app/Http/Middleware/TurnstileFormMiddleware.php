<?php
declare(strict_types=1);
namespace Barama\Http\Middleware;
use Barama\Core\MiddlewareInterface;
use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Security\TurnstileGuard;
use Throwable;

final class TurnstileFormMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly string $form) {
        if (!in_array($form, TurnstileGuard::ACTIONS, true)) throw new \InvalidArgumentException('Unknown form');
    }
    public function handle(Request $request, callable $next): Response
    {
        try {
            if (!TurnstileGuard::active($this->form)) return $next($request);
            $token=$request->input('cf-turnstile-response','');
            if (is_string($token) && TurnstileGuard::verify($token,$this->form,$request->ip()))
                return $next($request);
        } catch (Throwable) { return $this->reject($request,503); }
        return $this->reject($request,422);
    }
    private function reject(Request $request,int $status): Response
    {
        $message=$status===503?'Security verification unavailable; retry later.':'Security verification failed; please refresh and retry.';
        if (in_array($request->path(),['/inquiries','/contact/messages'],true))
            return Response::json(['ok'=>false,'message'=>$message],$status);
        return Response::html('<!doctype html><meta charset="utf-8"><title>Security verification</title><main><h1>Security verification</h1><p>'.htmlspecialchars($message,ENT_QUOTES).'</p></main>',$status);
    }
}
