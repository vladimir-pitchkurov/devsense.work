<x-layout title="Admin - Analytics Dashboard | DevSense" description="Super-Admin Traffic & Bot Auditing Dashboard">
<div class="admin-container">
    <div class="admin-header">
        <h1 class="admin-title">Analytics Dashboard</h1>
        <div class="header-actions" style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
            <a href="{{ route('admin.articles.index', ['locale' => app()->getLocale()]) }}" class="admin-btn admin-btn--secondary">
                Articles
            </a>
            <a href="{{ route('admin.categories.index', ['locale' => app()->getLocale()]) }}" class="admin-btn admin-btn--secondary">
                Categories
            </a>
            <a href="{{ route('admin.tags.index', ['locale' => app()->getLocale()]) }}" class="admin-btn admin-btn--secondary">
                Tags
            </a>
            <a href="{{ route('admin.profile.edit', ['locale' => app()->getLocale()]) }}" class="admin-btn admin-btn--secondary">
                Edit Profile
            </a>
            <button onclick="window.print()" class="admin-btn admin-btn--secondary no-print">
                Print Report
            </button>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <!-- Card 1: Total Traffic -->
        <div class="stat-card">
            <div class="stat-card__icon-wrapper stat-card__icon-wrapper--blue">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="stat-card__icon">
                    <path d="M2 12h22M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
                </svg>
            </div>
            <div class="stat-card__content">
                <div class="stat-card__label">Total Visits</div>
                <div class="stat-card__value">{{ number_format($totalVisits) }}</div>
                <div class="stat-card__sub">All logged public page views</div>
            </div>
        </div>

        <!-- Card 2: Humans -->
        <div class="stat-card">
            <div class="stat-card__icon-wrapper stat-card__icon-wrapper--green">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="stat-card__icon">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
            </div>
            <div class="stat-card__content">
                <div class="stat-card__label">Human Traffic</div>
                <div class="stat-card__value">{{ number_format($humanVisits) }}</div>
                <div class="stat-card__sub">{{ $humanPercentage }}% of total traffic</div>
            </div>
        </div>

        <!-- Card 3: Search Engine Bots -->
        <div class="stat-card">
            <div class="stat-card__icon-wrapper stat-card__icon-wrapper--indigo">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="stat-card__icon">
                    <circle cx="12" cy="12" r="10"></circle>
                    <path d="m8 12 4 4 6-6"></path>
                </svg>
            </div>
            <div class="stat-card__content">
                <div class="stat-card__label">Search Engines</div>
                <div class="stat-card__value">{{ number_format($botVisits - $aiVisits) }}</div>
                <div class="stat-card__sub">{{ $botPercentage }}% indexer crawls</div>
            </div>
        </div>

        <!-- Card 4: AI Scrapers -->
        <div class="stat-card">
            <div class="stat-card__icon-wrapper stat-card__icon-wrapper--purple">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="stat-card__icon">
                    <path d="M12 2V22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                </svg>
            </div>
            <div class="stat-card__content">
                <div class="stat-card__label">AI Scrapers</div>
                <div class="stat-card__value">{{ number_format($aiVisits) }}</div>
                <div class="stat-card__sub">{{ $aiPercentage }}% LLM bot agents</div>
            </div>
        </div>
    </div>

    <!-- Traffic Trend & Pie-ish Stats -->
    <div class="dashboard-row">
        <!-- 7-Day Stacked Activity Graph -->
        <div class="admin-card chart-card">
            <h2 class="section-title">Weekly Traffic Trend</h2>
            @php
                $maxCount = collect($chartData)->map(fn($day) => $day['human'] + $day['search_bot'] + $day['ai_bot'])->max() ?: 10;
            @endphp
            <div class="chart-outer">
                <div class="chart-y-axis">
                    <span>{{ number_format($maxCount) }}</span>
                    <span>{{ number_format($maxCount / 2) }}</span>
                    <span>0</span>
                </div>
                <div class="chart-container">
                    @foreach ($chartData as $day)
                        @php
                            $scale = $maxCount > 0 ? 100 / $maxCount : 0;
                            $humanHeight = $day['human'] * $scale;
                            $searchHeight = $day['search_bot'] * $scale;
                            $aiHeight = $day['ai_bot'] * $scale;
                        @endphp
                        <div class="chart-bar-wrapper">
                            <div class="chart-bar-group">
                                <div class="chart-bar-segment segment--ai" style="height: {{ $aiHeight }}%;" title="AI Bots: {{ $day['ai_bot'] }}"></div>
                                <div class="chart-bar-segment segment--search" style="height: {{ $searchHeight }}%;" title="Search Engines: {{ $day['search_bot'] }}"></div>
                                <div class="chart-bar-segment segment--human" style="height: {{ $humanHeight }}%;" title="Humans: {{ $day['human'] }}"></div>
                            </div>
                            <div class="chart-bar-label">{{ $day['label'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="chart-legend">
                <span class="legend-item"><span class="legend-dot dot--human"></span> Humans</span>
                <span class="legend-item"><span class="legend-dot dot--search"></span> Search Engines</span>
                <span class="legend-item"><span class="legend-dot dot--ai"></span> AI Bots & Scrapers</span>
            </div>
        </div>

        <!-- Audience Split Ratio -->
        <div class="admin-card split-card">
            <h2 class="section-title">Traffic Composition</h2>
            <div class="composition-container">
                <div class="composition-bar">
                    <div class="comp-segment comp-segment--human" style="width: {{ $humanPercentage }}%;" title="Humans: {{ $humanPercentage }}%"></div>
                    <div class="comp-segment comp-segment--search" style="width: {{ $botPercentage }}%;" title="Search: {{ $botPercentage }}%"></div>
                    <div class="comp-segment comp-segment--ai" style="width: {{ $aiPercentage }}%;" title="AI Scrapers: {{ $aiPercentage }}%"></div>
                </div>
                <div class="composition-list">
                    <div class="comp-list-item">
                        <div class="comp-info">
                            <span class="legend-dot dot--human"></span>
                            <span class="comp-name">Human Visitors</span>
                        </div>
                        <span class="comp-stat">{{ number_format($humanVisits) }} ({{ $humanPercentage }}%)</span>
                    </div>
                    <div class="comp-list-item">
                        <div class="comp-info">
                            <span class="legend-dot dot--search"></span>
                            <span class="comp-name">Search Engines</span>
                        </div>
                        <span class="comp-stat">{{ number_format($botVisits - $aiVisits) }} ({{ $botPercentage }}%)</span>
                    </div>
                    <div class="comp-list-item">
                        <div class="comp-info">
                            <span class="legend-dot dot--ai"></span>
                            <span class="comp-name">AI Bots / Scrapers</span>
                        </div>
                        <span class="comp-stat">{{ number_format($aiVisits) }} ({{ $aiPercentage }}%)</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tables Row -->
    <div class="dashboard-row dashboard-row--tables">
        <!-- Top Pages -->
        <div class="admin-card">
            <h2 class="section-title">Top Visited Guides</h2>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Path</th>
                            <th>Locale</th>
                            <th class="text-right">Page Views</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($topPages as $page)
                            <tr>
                                <td><code class="path-code">/{{ ltrim($page->path, '/') }}</code></td>
                                <td>
                                    <span class="locale-badge locale-badge--active">{{ strtoupper($page->locale) }}</span>
                                </td>
                                <td class="text-right font-semibold">{{ number_format($page->count) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center">No traffic logged yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Top Crawlers -->
        <div class="admin-card">
            <h2 class="section-title">Top Search Engines & AI Bots</h2>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Crawler / Scraper Agent</th>
                            <th>Type</th>
                            <th class="text-right">Hits</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($topCrawlers as $crawler)
                            <tr>
                                <td><strong class="article-title-text">{{ $crawler->crawler_name ?: 'Generic Bot' }}</strong></td>
                                <td>
                                    @if ($crawler->ai_count > 0)
                                        <span class="badge badge--ai">AI Bot</span>
                                    @else
                                        <span class="badge badge--search">Search Bot</span>
                                    @endif
                                </td>
                                <td class="text-right font-semibold">{{ number_format($crawler->count) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center">No crawler hits logged yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recent Crawler Activity Log -->
    <div class="admin-card crawler-logs-card">
        <h2 class="section-title">Recent Crawler Activity Logs</h2>
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Crawler / Agent</th>
                        <th>Target Path</th>
                        <th>Locale</th>
                        <th>Anonymized IP Hash</th>
                        <th>User Agent</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentCrawls as $log)
                        <tr>
                            <td class="text-nowrap text-muted">{{ $log->created_at->diffForHumans() }}</td>
                            <td>
                                <span class="badge {{ $log->is_ai ? 'badge--ai' : 'badge--search' }}">
                                    {{ $log->crawler_name ?: 'Crawler' }}
                                </span>
                            </td>
                            <td><code class="path-code">/{{ ltrim($log->path, '/') }}</code></td>
                            <td>
                                <span class="locale-badge locale-badge--active">{{ strtoupper($log->locale) }}</span>
                            </td>
                            <td><code class="ip-hash" title="{{ $log->ip_hash }}">{{ substr($log->ip_hash, 0, 12) }}...</code></td>
                            <td class="ua-cell" title="{{ $log->user_agent }}">{{ Str::limit($log->user_agent, 60) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center">No crawler activities recorded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.admin-container {
    max-width: 1200px;
    margin: 2rem auto;
    padding: 0 1rem;
}

.admin-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
}

.admin-title {
    font-family: 'Outfit', sans-serif;
    font-size: 2.25rem;
    font-weight: 800;
    color: var(--text-color);
}

.admin-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    font-family: inherit;
    font-weight: 600;
    font-size: 0.9rem;
    text-decoration: none;
    cursor: pointer;
    transition: transform 0.2s, background-color 0.2s, opacity 0.2s;
    border: 1px solid transparent;
}

.admin-btn--secondary {
    background-color: transparent;
    border-color: var(--border-color);
    color: var(--text-color);
}

.admin-btn--secondary:hover {
    background-color: rgba(99, 102, 241, 0.05);
    border-color: var(--primary-color);
}

.admin-btn:hover {
    transform: translateY(-1px);
}

/* Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(1, 1fr);
    gap: 1.5rem;
    margin-bottom: 2rem;
}

@media (min-width: 640px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (min-width: 1024px) {
    .stats-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}

.stat-card {
    background-color: var(--card-bg, rgba(255, 255, 255, 0.02));
    border: 1px solid var(--border-color);
    border-radius: 1rem;
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1.25rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.02);
}

.stat-card__icon-wrapper {
    width: 48px;
    height: 48px;
    border-radius: 0.75rem;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.stat-card__icon {
    width: 24px;
    height: 24px;
}

.stat-card__icon-wrapper--blue {
    background-color: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
}

.stat-card__icon-wrapper--green {
    background-color: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.stat-card__icon-wrapper--indigo {
    background-color: rgba(99, 102, 241, 0.1);
    color: #6366f1;
}

.stat-card__icon-wrapper--purple {
    background-color: rgba(168, 85, 247, 0.1);
    color: #a855f7;
}

.stat-card__content {
    min-width: 0;
}

.stat-card__label {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-card__value {
    font-size: 1.75rem;
    font-weight: 800;
    color: var(--text-color);
    line-height: 1.2;
    margin: 0.25rem 0;
    font-family: 'Outfit', sans-serif;
}

.stat-card__sub {
    font-size: 0.8rem;
    color: var(--text-muted);
}

/* Dashboard Grid Rows */
.dashboard-row {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.5rem;
    margin-bottom: 2rem;
}

@media (min-width: 1024px) {
    .dashboard-row {
        grid-template-columns: 2fr 1fr;
    }
    
    .dashboard-row--tables {
        grid-template-columns: 1fr 1fr;
    }
}

.admin-card {
    background-color: var(--card-bg, rgba(255, 255, 255, 0.02));
    border: 1px solid var(--border-color);
    border-radius: 1rem;
    padding: 1.5rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
}

.section-title {
    font-family: 'Outfit', sans-serif;
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-color);
    margin: 0 0 1.5rem;
    border-bottom: 1px solid var(--border-color);
    padding-bottom: 0.75rem;
}

/* Weekly Graph */
.chart-card {
    display: flex;
    flex-direction: column;
}

.chart-outer {
    display: flex;
    gap: 1rem;
    flex-grow: 1;
    margin-bottom: 1.5rem;
    position: relative;
    padding-top: 1rem;
}

.chart-y-axis {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    font-size: 0.75rem;
    color: var(--text-muted);
    text-align: right;
    width: 2.5rem;
    padding-bottom: 1.5rem;
}

.chart-container {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    flex-grow: 1;
    height: 200px;
    border-bottom: 1px solid var(--border-color);
    padding-bottom: 0.5rem;
}

.chart-bar-wrapper {
    display: flex;
    flex-direction: column;
    align-items: center;
    flex-grow: 1;
    max-width: 4rem;
}

.chart-bar-group {
    display: flex;
    flex-direction: column-reverse;
    width: 1.5rem;
    height: 160px;
    background-color: rgba(99, 102, 241, 0.03);
    border-radius: 0.25rem;
    overflow: hidden;
    gap: 1px;
}

.chart-bar-segment {
    width: 100%;
    transition: transform 0.3s ease;
    cursor: pointer;
}

.segment--human {
    background-color: #10b981; /* Green */
}

.segment--search {
    background-color: #6366f1; /* Indigo */
}

.segment--ai {
    background-color: #a855f7; /* Purple */
}

.chart-bar-label {
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-top: 0.5rem;
    white-space: nowrap;
}

.chart-legend {
    display: flex;
    gap: 1.5rem;
    justify-content: center;
    font-size: 0.85rem;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-muted);
}

.legend-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
}

.dot--human { background-color: #10b981; }
.dot--search { background-color: #6366f1; }
.dot--ai { background-color: #a855f7; }

/* Composition / Split ratio card */
.composition-container {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.composition-bar {
    height: 24px;
    border-radius: 12px;
    overflow: hidden;
    display: flex;
    background-color: rgba(99, 102, 241, 0.05);
}

.comp-segment {
    height: 100%;
}

.comp-segment--human { background-color: #10b981; }
.comp-segment--search { background-color: #6366f1; }
.comp-segment--ai { background-color: #a855f7; }

.composition-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.comp-list-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--border-color);
    padding-bottom: 0.5rem;
}

.comp-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.comp-name {
    font-weight: 600;
    color: var(--text-color);
}

.comp-stat {
    font-size: 0.85rem;
    font-family: monospace;
    color: var(--text-muted);
}

/* Tables style */
.table-responsive {
    overflow-x: auto;
}

.admin-table {
    width: 100%;
    border-collapse: collapse;
}

.admin-table th, .admin-table td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--border-color);
    vertical-align: middle;
}

.admin-table th {
    font-weight: 700;
    color: var(--text-muted);
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    background-color: rgba(99, 102, 241, 0.02);
}

.path-code {
    font-family: 'Fira Code', monospace;
    font-size: 0.85rem;
    background-color: rgba(99, 102, 241, 0.05);
    padding: 0.15rem 0.4rem;
    border-radius: 0.25rem;
    color: var(--primary-color);
}

.font-semibold {
    font-weight: 600;
}

.text-right {
    text-align: right;
}

.badge {
    display: inline-block;
    padding: 0.2rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    font-weight: 700;
}

.badge--ai {
    background-color: rgba(168, 85, 247, 0.1);
    color: #a855f7;
    border: 1px solid rgba(168, 85, 247, 0.2);
}

.badge--search {
    background-color: rgba(99, 102, 241, 0.1);
    color: #6366f1;
    border: 1px solid rgba(99, 102, 241, 0.2);
}

.locale-badge {
    font-size: 0.7rem;
    font-weight: 700;
    padding: 0.15rem 0.35rem;
    border-radius: 0.25rem;
}

.locale-badge--active {
    background-color: var(--primary-color);
    color: #fff;
}

/* Crawler Log styles */
.crawler-logs-card {
    margin-top: 1rem;
}

.text-nowrap {
    white-space: nowrap;
}

.ip-hash {
    font-family: monospace;
    color: var(--text-muted);
}

.ua-cell {
    font-size: 0.8rem;
    color: var(--text-muted);
}
</style>
</x-layout>
