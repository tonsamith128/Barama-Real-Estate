<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Repositories\EmailTemplateRepository;

final class EmailTemplateRendererService
{
    /**
     * @param array<string,string> $variables
     * @param array{subject:string,html:string,text:string} $fallback
     * @return array{subject:string,html:string,text:string,template_id:?int}
     */
    public function render(
        string $templateKey,
        array $variables,
        array $fallback
    ): array {
        $template = (new EmailTemplateRepository())->activeByKey(
            strtolower(trim($templateKey))
        );

        if ($template === null) {
            return [
                'subject' => $fallback['subject'],
                'html' => $fallback['html'],
                'text' => $fallback['text'],
                'template_id' => null,
            ];
        }

        return [
            'subject' => $this->replace(
                (string) $template['subject_template'],
                $variables,
                false
            ),
            'html' => $this->replace(
                (string) $template['html_template'],
                $variables,
                true
            ),
            'text' => $this->replace(
                (string) $template['text_template'],
                $variables,
                false
            ),
            'template_id' => (int) $template['id'],
        ];
    }

    /** @param array<string,string> $variables */
    private function replace(
        string $template,
        array $variables,
        bool $escapeHtml
    ): string {
        $replacements = [];

        foreach ($variables as $key => $value) {
            $replacements['{{' . $key . '}}'] = $escapeHtml
                ? e($value)
                : $value;
            $replacements['{{ ' . $key . ' }}'] = $escapeHtml
                ? e($value)
                : $value;
        }

        return strtr($template, $replacements);
    }
}
