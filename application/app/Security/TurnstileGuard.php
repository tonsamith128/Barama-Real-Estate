<?php
declare(strict_types=1);
namespace Barama\Security;
use RuntimeException;

/** Customer Turnstile: encrypted credentials, default disabled, server verification. */
final class TurnstileGuard
{
    public const ACTIONS = ['login', 'register', 'forgot_password', 'contact', 'inquiry'];
    private const API = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    private const TEST_KEYS = ['1x00000000000000000000AA','2x00000000000000000000AB',
        '3x00000000000000000000FF','1x0000000000000000000000000000000AA',
        '2x0000000000000000000000000000000AA','3x0000000000000000000000000000000AA'];

    private static function path(): string { return APP_PATH . '/storage/settings/barama-turnstile.json'; }
    private static function key(): string
    {
        $raw = (string) env('APP_KEY', '');
        if (str_starts_with($raw, 'base64:')) $raw = substr($raw, 7);
        $decoded = base64_decode($raw, true);
        if (!is_string($decoded) || strlen($decoded) !== 32) {
            throw new RuntimeException('Application encryption key must be configured before saving Turnstile.');
        }
        return hash_hkdf('sha256', $decoded, 32, 'barama.turnstile.v110');
    }
    private static function hostname(): string
    {
        $url = (string) config('app.url', '');
        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || $host === '' || filter_var($host, FILTER_VALIDATE_IP) ||
            !preg_match('/^[a-z0-9.-]{3,253}$/D', strtolower($host))) {
            throw new RuntimeException('Configured public APP_URL hostname is invalid.');
        }
        return strtolower(rtrim($host, '.'));
    }
    public const APPEARANCES = ['always', 'execute', 'interaction-only'];
    private static function aead(array $record): string
    {
        $signed = ['v'=>1,'site'=>$record['site'],'forms'=>$record['forms'],
            'enabled'=>$record['enabled'],'verified'=>$record['verified']];
        // Legacy records have no appearance and must retain their EXACT AEAD.
        if (array_key_exists('appearance', $record)) {
            $signed['appearance'] = $record['appearance'];
        }
        return json_encode($signed, JSON_THROW_ON_ERROR);
    }
    private static function validAppearance(mixed $appearance): bool
    { return is_string($appearance) && in_array($appearance, self::APPEARANCES, true); }
    public static function load(): array
    {
        $default = ['site'=>'','secret'=>'','forms'=>[], 'enabled'=>false, 'verified'=>0, 'appearance'=>'always'];
        $p = self::path();
        if (!file_exists($p) && !is_link($p)) return $default;
        if (is_link($p) || !is_file($p) || filesize($p) > 8192) throw new RuntimeException('Turnstile settings unavailable.');
        $data = json_decode((string) file_get_contents($p), true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($data) || ($data['version'] ?? 0) !== 1 ||
            !is_string($data['site'] ?? null) || !is_bool($data['enabled'] ?? null) ||
            !is_int($data['verified'] ?? null) || !is_array($data['forms'] ?? null) ||
            !is_array($data['secret'] ?? null)) throw new RuntimeException('Invalid Turnstile settings.');
        self::validateForms($data['forms']);
        if (array_key_exists('appearance', $data) && !self::validAppearance($data['appearance']))
            throw new RuntimeException('Invalid Turnstile appearance.');
        if (!self::validPublicKey($data['site'])) throw new RuntimeException('Invalid site key.');
        $box=$data['secret'];
        $nonce=base64_decode((string) ($box['nonce']??''), true);
        $tag=base64_decode((string) ($box['tag']??''), true);
        $cipher=base64_decode((string) ($box['cipher']??''), true);
        if (!is_string($nonce) || strlen($nonce)!==12 || !is_string($tag) || strlen($tag)!==16 ||
            !is_string($cipher) || $cipher==='') throw new RuntimeException('Invalid encrypted Turnstile secret.');
        $secret=openssl_decrypt($cipher, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $nonce, $tag, self::aead($data));
        if (!is_string($secret) || !self::validSecret($secret)) throw new RuntimeException('Cannot decrypt Turnstile secret.');
        if ($data['enabled'] && ($data['site']==='' || $data['verified']===0 || !$data['forms']))
            throw new RuntimeException('Turnstile enabled configuration incomplete.');
        return ['site'=>$data['site'],'secret'=>$secret,'forms'=>$data['forms'],
            'enabled'=>$data['enabled'],'verified'=>$data['verified'],
            'appearance'=>$data['appearance']??'always'];
    }
    private static function validPublicKey(string $s): bool
    { return strlen($s) >= 8 && strlen($s) <= 128 && !in_array($s,self::TEST_KEYS,true) &&
        preg_match('/^[a-zA-Z0-9_-]+$/D', $s)===1; }
    private static function validSecret(string $s): bool
    { return strlen($s) >= 8 && strlen($s) <= 256 && !in_array($s,self::TEST_KEYS,true) &&
        preg_match('/^[a-zA-Z0-9_-]+$/D', $s)===1; }
    private static function validateForms(array $forms): void
    {
        if (array_values($forms)!==$forms || count($forms)>count(self::ACTIONS) ||
            count($forms)!==count(array_unique($forms))) throw new RuntimeException('Invalid protected form list.');
        foreach ($forms as $form) if (!is_string($form) || !in_array($form,self::ACTIONS,true))
            throw new RuntimeException('Unknown protected form.');
    }
    public static function save(array $settings): void
    {
        foreach (['site','secret','forms','enabled','verified'] as $k) if (!array_key_exists($k,$settings))
            throw new RuntimeException('Missing Turnstile setting.');
        if (!self::validPublicKey((string)$settings['site']) ||
            !self::validSecret((string)$settings['secret'])) throw new RuntimeException('Real Cloudflare keys required.');
        self::validateForms($settings['forms']);
        $appearance = $settings['appearance'] ?? 'always';
        if (!self::validAppearance($appearance)) throw new RuntimeException('Invalid Turnstile appearance.');
        if (!is_bool($settings['enabled']) || !is_int($settings['verified']) ||
            ($settings['enabled'] && ($settings['verified']<time()-3600 || !$settings['forms'])))
            throw new RuntimeException('Verify real Cloudflare keys before enabling form protection.');
        self::hostname();
        $nonce=random_bytes(12); $tag='';
        $cipher=openssl_encrypt($settings['secret'],'aes-256-gcm',self::key(),OPENSSL_RAW_DATA,
            $nonce,$tag,self::aead($settings),16);
        if (!is_string($cipher)) throw new RuntimeException('Turnstile encryption unavailable.');
        $settings['appearance'] = $appearance;
        $data=['version'=>1,'site'=>$settings['site'],'forms'=>$settings['forms'],
            'enabled'=>$settings['enabled'],'verified'=>$settings['verified'],
            'appearance'=>$appearance,
            'secret'=>['nonce'=>base64_encode($nonce),'tag'=>base64_encode($tag),'cipher'=>base64_encode($cipher)]];
        $p=self::path(); $dir=dirname($p);
        if (!is_dir($dir) || is_link($dir) || (file_exists($p) && (!is_file($p) || is_link($p))))
            throw new RuntimeException('Private Turnstile settings directory unavailable.');
        $tmp=$p.'.tmp-'.bin2hex(random_bytes(8));
        if (file_put_contents($tmp,json_encode($data,JSON_THROW_ON_ERROR),LOCK_EX)===false)
            throw new RuntimeException('Cannot save encrypted configuration.');
        chmod($tmp,0600);
        if (!rename($tmp,$p)) { @unlink($tmp); throw new RuntimeException('Cannot activate configuration.'); }
    }
    public static function status(): array
    {
        $r=self::load(); return ['site'=>$r['site'],'forms'=>$r['forms'],'enabled'=>$r['enabled'],
            'verified'=>$r['verified'],'configured'=>$r['secret']!=='',
            'appearance'=>$r['appearance']];
    }
    public static function active(string $form): bool
    {
        $r=self::load(); return $r['enabled'] && in_array($form,$r['forms'],true);
    }
    public static function publicConfig(): array
    {
        $r=self::load(); return $r['enabled']?['site'=>$r['site'],'forms'=>$r['forms'],
            'appearance'=>$r['appearance']]:['site'=>'','forms'=>[],'appearance'=>'always'];
    }
    public static function verify(string $token, string $action, ?string $ip=null): bool
    {
        $r=self::load();
        return self::siteverify($r['secret'],$token,$action,$ip);
    }
    public static function siteverify(string $secret, string $token, string $action, ?string $ip=null): bool
    {
        if (!self::validSecret($secret) || $token==='' || strlen($token)>2048 ||
            !preg_match('/^[a-z_]{1,32}$/D',$action) || !function_exists('curl_init')) return false;
        $body=['secret'=>$secret,'response'=>$token];
        if (is_string($ip) && filter_var($ip,FILTER_VALIDATE_IP)) $body['remoteip']=$ip;
        $h=curl_init(self::API); if ($h===false) return false;
        $ok=curl_setopt_array($h,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($body),
            CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded','Accept: application/json'],
            CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>7,CURLOPT_SSL_VERIFYPEER=>true,
            CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS]);
        if (!$ok) return false;
        $response=curl_exec($h);$http=(int)curl_getinfo($h,CURLINFO_HTTP_CODE);unset($h);
        if ($http!==200 || !is_string($response) || strlen($response)>8192) return false;
        $data=json_decode($response,true);
        return is_array($data) && ($data['success']??false)===true &&
            is_string($data['hostname']??null) && is_string($data['action']??null) &&
            hash_equals(self::hostname(),strtolower(rtrim($data['hostname'],'.'))) &&
            hash_equals($action,$data['action']);
    }
}
