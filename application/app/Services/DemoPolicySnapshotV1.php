<?php

declare(strict_types=1);

namespace Barama\Services;

use RuntimeException;

/**
 * BARAMA_SIGNED_DEMO_POLICY_CENTER_V114
 *
 * Export only public, published display content into the content.json body of
 * a publisher-signed native demo. Never export operator compliance notes,
 * feedback, revision snapshots, authors, private policies or customer data.
 *
 * Install on a fresh customer site only, creating an otherwise absent store.
 * No existing policy file (even empty) is ever overwritten.
 */
final class DemoPolicySnapshotV1
{
    private const TEXT_FIELDS = [
        'id'=>64,'title'=>160,'slug'=>180,'summary'=>420,
        'content_html'=>131072,'category_id'=>64,'category'=>80,
        'effective_date'=>16,'seo_title'=>180,'meta_description'=>320,
        'created_at'=>40,'updated_at'=>40,
    ];

    /** @return array<string,mixed> */
    public static function exportPublic(string $source): array
    {
        if (is_link($source) || !is_file($source) || !is_readable($source) ||
            filesize($source) > 2097152) {
            throw new RuntimeException('DEMO_POLICY_SOURCE_UNAVAILABLE');
        }
        $stored = json_decode((string) file_get_contents($source), true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($stored) || !is_array($stored['policies'] ?? null) ||
            !is_array($stored['categories'] ?? null) || !is_array($stored['faqs'] ?? null)) {
            throw new RuntimeException('DEMO_POLICY_SOURCE_INVALID');
        }
        $policies = [];
        foreach ($stored['policies'] as $entry) {
            if (!is_array($entry) || ($entry['status'] ?? '') !== 'published' ||
                ($entry['visibility'] ?? 'public') !== 'public') continue;
            $out = [];
            foreach (self::TEXT_FIELDS as $key => $limit) {
                $out[$key] = mb_substr(trim((string)($entry[$key] ?? '')), 0, $limit);
            }
            $out['status'] = 'published';
            $out['visibility'] = 'public';
            $out['sort_order'] = max(0, min(9999, (int)($entry['sort_order'] ?? 0)));
            $out['featured'] = (bool)($entry['featured'] ?? false);
            $out['version_number'] = max(1, min(10000, (int)($entry['version_number'] ?? 1)));
            $out['faqs'] = self::faqList((array)($entry['faqs'] ?? []), 30);
            $policies[] = $out;
        }
        $categories = [];
        foreach ($stored['categories'] as $entry) {
            if (!is_array($entry)) continue;
            $categories[] = [
                'id' => mb_substr(trim((string)($entry['id'] ?? '')), 0, 64),
                'name' => mb_substr(trim((string)($entry['name'] ?? '')), 0, 80),
                'slug' => mb_substr(trim((string)($entry['slug'] ?? '')), 0, 100),
                'description' => mb_substr(trim((string)($entry['description'] ?? '')), 0, 360),
                'sort_order' => max(0, min(9999, (int)($entry['sort_order'] ?? 0))),
            ];
        }
        $snapshot = [
            'version'=>1,
            'policies'=>$policies,
            'categories'=>$categories,
            'faqs'=>self::faqList($stored['faqs'], 60),
        ];
        self::validate($snapshot);
        return $snapshot;
    }

    private static function faqList(array $rows, int $limit): array
    {
        $out = [];
        foreach (array_slice($rows, 0, $limit) as $entry) {
            if (!is_array($entry)) throw new RuntimeException('DEMO_POLICY_FAQ_INVALID');
            $out[] = [
                'question'=>mb_substr(trim((string)($entry['question'] ?? '')), 0, 240),
                'answer'=>mb_substr(trim((string)($entry['answer'] ?? '')), 0, 1200),
            ];
        }
        return $out;
    }

    /** Fail closed even when the ZIP's signature is valid. */
    public static function validate(mixed $value): void
    {
        if (!is_array($value) || array_keys($value) !== ['version','policies','categories','faqs'] ||
            $value['version'] !== 1 || !is_array($value['policies']) ||
            !is_array($value['categories']) || !is_array($value['faqs']) ||
            count($value['policies']) < 1 || count($value['policies']) > 100 ||
            count($value['categories']) > 100 || count($value['faqs']) > 60) {
            throw new RuntimeException('DEMO_POLICY_CONTRACT_INVALID');
        }
        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (strlen($encoded) > 1048576) {
            throw new RuntimeException('DEMO_POLICY_SIZE_LIMIT');
        }
        $categories = [];
        foreach ($value['categories'] as $row) {
            if (!is_array($row) ||
                array_keys($row) !== ['id','name','slug','description','sort_order'] ||
                !is_string($row['id']) || $row['id'] === '' ||
                !is_string($row['name']) || $row['name'] === '' ||
                !is_string($row['slug']) || $row['slug'] === '' ||
                !is_string($row['description']) ||
                !is_int($row['sort_order']) ||
                strlen($row['id']) > 64 || strlen($row['name']) > 320 ||
                isset($categories[$row['id']])) {
                throw new RuntimeException('DEMO_POLICY_CATEGORY_INVALID');
            }
            $categories[$row['id']] = true;
        }
        $ids = [];
        $slugs = [];
        foreach ($value['policies'] as $row) {
            if (!is_array($row) || count($row) !== count(self::TEXT_FIELDS) + 6 ||
                !in_array($row['status'] ?? '', ['published'], true) ||
                ($row['visibility'] ?? '') !== 'public' ||
                !is_int($row['sort_order'] ?? null) ||
                !is_bool($row['featured'] ?? null) ||
                !is_int($row['version_number'] ?? null) ||
                !is_array($row['faqs'] ?? null) ||
                count($row['faqs']) > 30) {
                throw new RuntimeException('DEMO_POLICY_ROW_INVALID');
            }
            foreach (self::TEXT_FIELDS as $field=>$limit) {
                if (!is_string($row[$field] ?? null) ||
                    strlen($row[$field]) > $limit * 4) {
                    throw new RuntimeException('DEMO_POLICY_FIELD_INVALID');
                }
            }
            $id = $row['id']; $slug = $row['slug'];
            if ($id === '' || $slug === '' || trim($row['title']) === '' ||
                isset($ids[$id]) || isset($slugs[$slug]) ||
                ($row['category_id'] !== '' && !isset($categories[$row['category_id']])) ||
                preg_match('~<\s*(?:script|iframe|object|embed|form|style)\b|on[a-z]+\s*=|javascript\s*:~i', $row['content_html'])) {
                throw new RuntimeException('DEMO_POLICY_UNSAFE_OR_DUPLICATE');
            }
            $ids[$id]=true; $slugs[$slug]=true;
            self::validateFaqs($row['faqs']);
            if (count(array_diff(array_keys($row), array_merge(array_keys(self::TEXT_FIELDS),
                ['status','visibility','sort_order','featured','version_number','faqs']))) !== 0) {
                throw new RuntimeException('DEMO_POLICY_EXTRA_FIELDS_DENIED');
            }
        }
        self::validateFaqs($value['faqs']);
    }

    private static function validateFaqs(array $faqs): void
    {
        foreach ($faqs as $entry) {
            if (!is_array($entry) || array_keys($entry) !== ['question','answer'] ||
                !is_string($entry['question']) || !is_string($entry['answer']) ||
                trim($entry['question']) === '' ||
                strlen($entry['question']) > 960 || strlen($entry['answer']) > 4800) {
                throw new RuntimeException('DEMO_POLICY_FAQ_INVALID');
            }
        }
    }

    /**
     * Returns the created path for rollback if the surrounding DB transaction
     * cannot commit. Linking a verified temporary file is no-clobber atomic.
     */
    public static function installFresh(array $snapshot, string $basePath): string
    {
        self::validate($snapshot);
        $base = realpath($basePath);
        if ($base === false || is_link($basePath)) throw new RuntimeException('DEMO_POLICY_SITE_UNSAFE');
        $storage = $base . '/storage';
        if (is_link($storage)) {
            $link = realpath($storage);
            if ($link !== realpath($base . '/application/storage')) {
                throw new RuntimeException('DEMO_POLICY_STORAGE_SYMLINK_DENIED');
            }
        } elseif (!is_dir($storage) && !mkdir($storage, 0750) && !is_dir($storage)) {
            throw new RuntimeException('DEMO_POLICY_STORAGE_MISSING');
        }
        if (!is_dir($storage) || !is_writable($storage)) throw new RuntimeException('DEMO_POLICY_STORAGE_UNWRITABLE');
        $directory = $storage . '/policy-center';
        if (is_link($directory) || (file_exists($directory) && !is_dir($directory))) {
            throw new RuntimeException('DEMO_POLICY_DIRECTORY_UNSAFE');
        }
        if (!is_dir($directory) && !mkdir($directory, 0750) && !is_dir($directory)) {
            throw new RuntimeException('DEMO_POLICY_DIRECTORY_UNWRITABLE');
        }
        $target = $directory . '/data.json';
        if (file_exists($target) || is_link($target)) {
            throw new RuntimeException('DEMO_POLICY_REQUIRES_FRESH_SITE');
        }
        $json = json_encode([
            'version'=>1,
            'policies'=>$snapshot['policies'],
            'categories'=>$snapshot['categories'],
            'faqs'=>$snapshot['faqs'],
            'feedback'=>[],
        ], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
        $temp = $directory . '/.demo-policy-' . bin2hex(random_bytes(12));
        $h = fopen($temp, 'xb');
        if ($h === false) throw new RuntimeException('DEMO_POLICY_TEMP_CREATE_FAILED');
        try {
            if (fwrite($h, $json) !== strlen($json) || !fflush($h)) {
                throw new RuntimeException('DEMO_POLICY_WRITE_FAILED');
            }
        } finally { fclose($h); }
        try {
            if (!chmod($temp, 0640) || !link($temp, $target)) {
                throw new RuntimeException('DEMO_POLICY_ATOMIC_INSTALL_FAILED');
            }
        } finally { @unlink($temp); }
        return $target;
    }
}
