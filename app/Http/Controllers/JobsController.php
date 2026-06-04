<?php

namespace App\Http\Controllers;

use App\Services\MarkdownContentService;
use App\Services\PublicContentApiService;
use App\Support\SiteUrl;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Serves recruitment job listings backed by localized Markdown content.
 */
class JobsController extends Controller
{
    /**
     * Display a listing of current jobs.
     */
    public function index(PublicContentApiService $apiService): View
    {
        $locale = app()->getLocale();
        $entries = $apiService->scanIndex();
        $cards = [];

        foreach ($entries as $e) {
            if ($e['category'] === 'jobs' && $e['locale'] === $locale) {
                $doc = $apiService->getMarkdownDocument($locale, 'jobs', $e['slug']);
                if ($doc === null) {
                    continue;
                }
                
                $meta = $doc['meta'] ?? [];
                $cards[] = [
                    'slug' => $e['slug'],
                    'title' => $meta['title'] ?? Str::headline($e['slug']),
                    'excerpt' => $meta['description'] ?? '',
                    'company' => $meta['company'] ?? 'DevSense',
                    'location' => $meta['location'] ?? 'Remote',
                    'salary' => $meta['salary'] ?? '',
                    'employment_type' => $meta['employment_type'] ?? 'FULL_TIME',
                ];
            }
        }

        return view('jobs.index', ['cards' => $cards]);
    }

    /**
     * Show a specific job posting.
     */
    public function show(string $slug, MarkdownContentService $markdownService, PublicContentApiService $apiService): View
    {
        $locale = app()->getLocale();
        $data = $markdownService->getParsedContent($locale, 'jobs', $slug);

        if (! $data) {
            abort(404, __('ui.errors.job_missing', ['slug' => $slug]) ?? "Job posting not found.");
        }

        $meta = $data['meta'];
        $pageTitle = $this->scalarMetaString($meta, 'title') ?? Str::headline($slug);
        $pageDescription = $this->scalarMetaString($meta, 'description') ?? '';
        $canonicalUrl = SiteUrl::route('jobs.show', ['locale' => $locale, 'slug' => $slug]);
        $hrefLangMap = config('seo.hreflang', []);
        $modified = Carbon::createFromTimestamp($data['source_modified_at']);
        $published = $this->publishedCarbon($meta, $modified);

        // Build JobPosting Schema
        $structuredData = [
            '@type' => 'JobPosting',
            'title' => $pageTitle,
            'description' => $pageDescription,
            'datePosted' => $published->toIso8601String(),
            'validThrough' => $published->addMonths(3)->toIso8601String(),
            'employmentType' => $meta['employment_type'] ?? 'FULL_TIME',
            'jobLocation' => [
                '@type' => 'Place',
                'address' => [
                    '@type' => 'PostalAddress',
                    'addressLocality' => $meta['location'] ?? 'Remote',
                    'addressCountry' => $meta['country_code'] ?? 'US',
                ],
            ],
            'baseSalary' => [
                '@type' => 'MonetaryAmount',
                'currency' => $meta['salary_currency'] ?? 'USD',
                'value' => [
                    '@type' => 'QuantitativeValue',
                    'value' => $meta['salary_value'] ?? 0,
                    'unitText' => 'MONTH',
                ],
            ],
        ];

        if (isset($meta['faq'])) {
            $structuredData['faq'] = $meta['faq'];
        }

        return view('jobs.show', [
            'content' => $data['html'],
            'meta' => $meta,
            'pageTitle' => $pageTitle,
            'pageDescription' => $pageDescription,
            'canonicalUrl' => $canonicalUrl,
            'breadcrumbCurrent' => $pageTitle,
            'structuredData' => $structuredData,
        ]);
    }
}
