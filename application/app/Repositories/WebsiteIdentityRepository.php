<?php

declare(strict_types=1);
namespace Barama\Repositories;

use Barama\Core\Database;
use InvalidArgumentException;
use RuntimeException;

final class WebsiteIdentityRepository
{
    private const KEY = 'website.identity.v1';
    public function get(): array
    {
        $stmt = Database::connection()->prepare('SELECT setting_value FROM application_settings WHERE setting_key = :key LIMIT 1');
        $stmt->execute(['key' => self::KEY]);
        $decoded = json_decode((string) ($stmt->fetchColumn() ?: ''), true);
        return array_merge([
            'name' => 'Barama Real Estate',
            'tagline' => 'Discover properties, projects and developers',
            'description' => 'Barama Real Estate marketplace and property management platform.',
            'about' => '', 'keywords' => '', 'contact_email' => '', 'contact_phone' => '',
            'light_logo' => 'images/branding/barama-logo-header.png',
            'dark_logo' => 'images/branding/barama-logo-header.png',
            'favicon_32' => 'images/branding/favicon-32x32.png',
            'favicon_16' => 'images/branding/favicon-16x16.png',
            'apple_icon' => 'images/branding/apple-touch-icon.png',
        ], is_array($decoded) ? $decoded : []);
    }
    public function save(array $input): void
    {
        $current = $this->get();
        foreach (['name'=>120,'tagline'=>180,'description'=>320,'about'=>3000,'keywords'=>500,'contact_email'=>254,'contact_phone'=>60] as $key=>$limit) {
            if (!array_key_exists($key,$input)) continue;
            if (!is_string($input[$key])) throw new InvalidArgumentException('Invalid website identity field.');
            $value = trim(strip_tags($input[$key]));
            if (mb_strlen($value) > $limit) throw new InvalidArgumentException('Website field exceeds its maximum length.');
            if ($key === 'name' && $value === '') throw new InvalidArgumentException('Website name is required.');
            if ($key === 'contact_email' && $value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) throw new InvalidArgumentException('Contact email is invalid.');
            $current[$key] = $value;
        }
        foreach (['light_logo','dark_logo','favicon_32','favicon_16','apple_icon'] as $key) {
            if (!array_key_exists($key,$input)) continue;
            $path = $input[$key];
            if (!is_string($path) || !preg_match('~^images/branding/(?:custom/[a-f0-9]{32}\.(?:png|jpg|webp)|barama-logo-header\.png|favicon-(?:16x16|32x32)\.png|apple-touch-icon\.png)$~D',$path)) {
                throw new InvalidArgumentException('Invalid branding asset path.');
            }
            $current[$key]=$path;
        }
        $payload=json_encode($current, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        $stmt=Database::connection()->prepare('INSERT INTO application_settings (setting_key,setting_value,value_type) VALUES (:key,:val,"json") ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),value_type="json",updated_at=CURRENT_TIMESTAMP');
        $stmt->execute(['key'=>self::KEY,'val'=>$payload]);
    }
}
