<?php

namespace App\Services;

class ContentSanitizer
{
    /**
     * Sanitise plain text fields (Title, Meta Description, etc.) by stripping all HTML tags.
     */
    public function sanitizePlainText(?string $text): string
    {
        if ($text === null) {
            return '';
        }
        return strip_tags($text);
    }

    /**
     * Sanitise FAQ JSON input.
     */
    public function sanitizeFaq(?string $faqJson): ?string
    {
        if (empty($faqJson)) {
            return null;
        }

        $decoded = json_decode($faqJson, true);
        if (!is_array($decoded)) {
            return null;
        }

        $sanitized = [];
        foreach ($decoded as $item) {
            if (isset($item['question']) || isset($item['answer'])) {
                $sanitized[] = [
                    'question' => $this->sanitizePlainText($item['question'] ?? ''),
                    'answer' => $this->sanitizePlainText($item['answer'] ?? ''),
                ];
            }
        }

        return json_encode($sanitized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Sanitise Markdown content field to prevent script injection/XSS.
     */
    public function sanitizeMarkdown(?string $markdown): string
    {
        if ($markdown === null) {
            return '';
        }

        // 1. Remove script tags completely
        $markdown = preg_replace('/<script\b[^>]*>([\s\S]*?)<\/script>/i', '', $markdown);

        // 2. Remove typical malicious HTML tags (iframe, object, embed, form, input, button)
        $markdown = preg_replace('/<(iframe|object|embed|form|input|button)\b[^>]*>([\s\S]*?)<\/\1>/i', '', $markdown);
        $markdown = preg_replace('/<(iframe|object|embed|form|input|button)\b[^>]*>/i', '', $markdown);

        // 3. Strip inline javascript handlers (onmouseover, onload, onerror, onclick, etc.)
        $markdown = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]*)/i', '', $markdown);

        // 4. Strip javascript: URIs in anchors and markdown links [text](javascript:...)
        $markdown = preg_replace('/href\s*=\s*("[^"]*javascript:[^"]*"|\'[^\']*javascript:[^\']*\'|[^\s>]*javascript:[^\s>]*)/i', 'href="#"', $markdown);
        $markdown = preg_replace('/\[([^\]]*)\]\(\s*javascript:[^()]*(?:\([^()]*\)[^()]*)*\)/i', '[$1](#)', $markdown);

        return $markdown;
    }
}
