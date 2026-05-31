<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PageVisit;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Render the admin analytics dashboard.
     */
    public function index()
    {
        // 1. Core counters
        $totalVisits = PageVisit::count();
        $botVisits = PageVisit::where('is_bot', true)->count();
        $aiVisits = PageVisit::where('is_ai', true)->count();
        $humanVisits = PageVisit::where('is_bot', false)->count();

        // Ratios
        $humanPercentage = $totalVisits > 0 ? round(($humanVisits / $totalVisits) * 100, 1) : 0;
        $botPercentage = $totalVisits > 0 ? round((($botVisits - $aiVisits) / $totalVisits) * 100, 1) : 0;
        $aiPercentage = $totalVisits > 0 ? round(($aiVisits / $totalVisits) * 100, 1) : 0;

        // 2. Top pages (paths)
        $topPages = PageVisit::select('path', 'locale')
            ->selectRaw('count(*) as count')
            ->groupBy('path', 'locale')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        // 3. Top Crawlers
        $topCrawlers = PageVisit::where('is_bot', true)
            ->select('crawler_name')
            ->selectRaw('count(*) as count')
            ->selectRaw('sum(case when is_ai then 1 else 0 end) as ai_count')
            ->groupBy('crawler_name')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        // 4. Recent logs (crawlers and scrapers)
        $recentCrawls = PageVisit::where('is_bot', true)
            ->latest()
            ->limit(15)
            ->get();

        // 5. Weekly Trend (7-day activity)
        $sevenDaysAgo = now()->subDays(6)->startOfDay();
        $visits = PageVisit::where('created_at', '>=', $sevenDaysAgo)->get();

        $chartData = [];
        for ($i = 6; $i >= 0; $i--) {
            $dateObj = now()->subDays($i);
            $dateStr = $dateObj->format('Y-m-d');
            
            // Filter in-memory to ensure database-agnostic support (SQLite in tests, MySQL/PgSQL in prod)
            $dayVisits = $visits->filter(function ($visit) use ($dateStr) {
                return $visit->created_at->format('Y-m-d') === $dateStr;
            });

            $chartData[] = [
                'label' => $dateObj->format('M d'),
                'human' => $dayVisits->where('is_bot', false)->count(),
                'search_bot' => $dayVisits->where('is_bot', true)->where('is_ai', false)->count(),
                'ai_bot' => $dayVisits->where('is_ai', true)->count(),
            ];
        }

        return view('admin.dashboard', compact(
            'totalVisits',
            'botVisits',
            'aiVisits',
            'humanVisits',
            'humanPercentage',
            'botPercentage',
            'aiPercentage',
            'topPages',
            'topCrawlers',
            'recentCrawls',
            'chartData'
        ));
    }
}
