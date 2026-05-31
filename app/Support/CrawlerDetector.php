<?php

namespace App\Support;

class CrawlerDetector
{
    private const CRAWLERS = [
        // AI Crawlers
        'gptbot' => ['name' => 'GPTBot (OpenAI)', 'is_ai' => true],
        'chatgpt-user' => ['name' => 'ChatGPT User (OpenAI)', 'is_ai' => true],
        'claudebot' => ['name' => 'ClaudeBot (Anthropic)', 'is_ai' => true],
        'anthropic-ai' => ['name' => 'Anthropic AI', 'is_ai' => true],
        'google-extended' => ['name' => 'Google-Extended (Gemini)', 'is_ai' => true],
        'google-bard' => ['name' => 'Google Bard', 'is_ai' => true],
        'cohere-ai' => ['name' => 'Cohere AI', 'is_ai' => true],
        'diffbot' => ['name' => 'Diffbot', 'is_ai' => true],
        'imagesprit' => ['name' => 'ImageSprit', 'is_ai' => true],
        'bytespider' => ['name' => 'ByteSpider (TikTok)', 'is_ai' => true],
        'petalbot' => ['name' => 'PetalBot (Huawei)', 'is_ai' => true],
        
        // Search Engine & Social Crawlers
        'googlebot' => ['name' => 'Googlebot', 'is_ai' => false],
        'bingbot' => ['name' => 'Bingbot', 'is_ai' => false],
        'yandexbot' => ['name' => 'YandexBot', 'is_ai' => false],
        'duckduckbot' => ['name' => 'DuckDuckBot', 'is_ai' => false],
        'baiduspider' => ['name' => 'Baidu Spider', 'is_ai' => false],
        'applebot' => ['name' => 'Applebot', 'is_ai' => false],
        'ia_archiver' => ['name' => 'Wayback Machine', 'is_ai' => false],
        'facebookexternalhit' => ['name' => 'Facebook Crawler', 'is_ai' => false],
        'twitterbot' => ['name' => 'TwitterBot', 'is_ai' => false],
        
        // SEO/Research Audit Bots
        'screaming frog' => ['name' => 'Screaming Frog SEO Spider', 'is_ai' => false],
        'ahrefsbot' => ['name' => 'AhrefsBot', 'is_ai' => false],
        'semrushbot' => ['name' => 'SemrushBot', 'is_ai' => false],
        'rogerbot' => ['name' => 'Moz Rogerbot', 'is_ai' => false],
    ];

    /**
     * Detect crawler details from User Agent.
     *
     * @return array{is_bot: bool, is_ai: bool, name: ?string}
     */
    public static function detect(string $userAgent): array
    {
        if ($userAgent === '') {
            return [
                'is_bot' => false,
                'is_ai' => false,
                'name' => null,
            ];
        }

        $ua = strtolower($userAgent);

        // Check our list of known bots/crawlers
        foreach (self::CRAWLERS as $signature => $info) {
            if (str_contains($ua, $signature)) {
                return [
                    'is_bot' => true,
                    'is_ai' => $info['is_ai'],
                    'name' => $info['name'],
                ];
            }
        }

        // Generic bot checks
        $genericBotSignatures = ['bot', 'crawler', 'spider', 'archiver', 'slurp', 'scraper'];
        foreach ($genericBotSignatures as $signature) {
            if (str_contains($ua, $signature)) {
                return [
                    'is_bot' => true,
                    'is_ai' => false,
                    'name' => 'Generic Crawler (' . ucfirst($signature) . ')',
                ];
            }
        }

        return [
            'is_bot' => false,
            'is_ai' => false,
            'name' => null,
        ];
    }
}
