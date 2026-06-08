<x-layout title="Admin - Analytics Dashboard | DevSense" description="Super-Admin Traffic & Bot Auditing Dashboard">
<div class="admin-container">
    <div class="admin-header">
        <h1 class="admin-title">Analytics Dashboard</h1>
        <div class="header-actions" style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
            <a href="{{ route('admin.articles.index', ['locale' => app()->getLocale()]) }}" class="admin-btn admin-btn--secondary">
                Articles
            </a>
            @can('manage-users')
                <a href="{{ route('admin.categories.index', ['locale' => app()->getLocale()]) }}" class="admin-btn admin-btn--secondary">
                    Categories
                </a>
            @endcan
            @can('manage-tags')
                <a href="{{ route('admin.tags.index', ['locale' => app()->getLocale()]) }}" class="admin-btn admin-btn--secondary">
                    Tags
                </a>
            @endcan
            @can('manage-users')
                <a href="{{ route('admin.users.index', ['locale' => app()->getLocale()]) }}" class="admin-btn admin-btn--secondary">
                    Users
                </a>
                <a href="{{ route('admin.tickets.index', ['locale' => app()->getLocale()]) }}" class="admin-btn admin-btn--secondary">
                    Tickets
                </a>
            @endcan
            <a href="{{ route('admin.profile.edit', ['locale' => app()->getLocale()]) }}" class="admin-btn admin-btn--secondary">
                Edit Profile
            </a>
            <button onclick="window.print()" class="admin-btn admin-btn--secondary no-print">
                Print Report
            </button>
            <form action="{{ route('logout') }}" method="POST" style="display: inline-block; margin: 0;">
                @csrf
                <button type="submit" class="admin-btn admin-btn--danger no-print" style="display: inline-flex; align-items: center; gap: 0.5rem; border: none; cursor: pointer;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16 17 21 12 16 7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                    Logout
                </button>
            </form>
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

    <!-- Flash message alerts inside admin dashboard -->
    @if (session('success'))
        <div class="admin-alert admin-alert--success" style="margin-top: 1rem; margin-bottom: 1.5rem;">
            {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div class="admin-alert admin-alert--danger" style="margin-top: 1rem; margin-bottom: 1.5rem;">
            {{ session('error') }}
        </div>
    @endif

    <!-- Moderation Queue Section -->
    @if (Auth::user()->isAdmin())
        <div class="admin-card moderation-queue-card" style="margin-bottom: 2rem;">
            <h2 class="section-title">Moderation Queue</h2>
            
            @php
                $pendingAuthorsCount = $pendingAuthors->count();
                $pendingProfilesCount = $pendingProfiles->count();
                $pendingArticlesCount = $pendingArticles->count();
                $reportsCount = $reports->count();
                $totalModerationCount = $pendingAuthorsCount + $pendingProfilesCount + $pendingArticlesCount + $reportsCount;
            @endphp
            
            @if ($totalModerationCount === 0)
                <div class="text-center" style="padding: 2.5rem 0; border-color: rgba(16, 185, 129, 0.3); background-color: rgba(16, 185, 129, 0.02); border-radius: 0.75rem; border: 1px dashed rgba(16, 185, 129, 0.3);">
                    <h3 style="color: #10b981; display: flex; align-items: center; justify-content: center; gap: 0.5rem; margin-bottom: 0.5rem; font-size: 1.2rem; font-weight: 700;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="20" height="20" style="vertical-align: middle;">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                        All Caught Up!
                    </h3>
                    <p style="color: var(--text-muted); margin: 0; font-size: 0.95rem;">No registrations, profile edits, drafts, or complaints require approval at this time.</p>
                </div>
            @else
                <div class="tabs-header" style="display: flex; gap: 0.75rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.75rem; overflow-x: auto; white-space: nowrap; margin-bottom: 1.5rem;">
                    <button onclick="switchModerationTab('authors')" id="tab-btn-authors" class="tab-btn tab-btn--active">
                        Authors Registration ({{ $pendingAuthorsCount }})
                    </button>
                    <button onclick="switchModerationTab('profiles')" id="tab-btn-profiles" class="tab-btn">
                        Profile Updates ({{ $pendingProfilesCount }})
                    </button>
                    <button onclick="switchModerationTab('articles')" id="tab-btn-articles" class="tab-btn">
                        Articles Drafts ({{ $pendingArticlesCount }})
                    </button>
                    <button onclick="switchModerationTab('reports')" id="tab-btn-reports" class="tab-btn">
                        User Reports ({{ $reportsCount }})
                    </button>
                </div>

                <!-- Authors Tab Content -->
                <div id="tab-content-authors" class="tab-content">
                    @if ($pendingAuthors->isEmpty())
                        <p style="color: var(--text-muted); text-align: center; padding: 1.5rem 0;">No pending authors registration requests.</p>
                    @else
                        <div class="table-responsive">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Registered</th>
                                        <th class="text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($pendingAuthors as $author)
                                        <tr>
                                            <td><strong>{{ $author->name }}</strong></td>
                                            <td><code>{{ $author->email }}</code></td>
                                            <td>{{ $author->created_at->diffForHumans() }}</td>
                                            <td class="text-right" style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                                                <form action="{{ route('admin.moderation.authors.approve', ['locale' => app()->getLocale(), 'user' => $author->id]) }}" method="POST" style="display: inline; margin: 0;">
                                                    @csrf
                                                    <button type="submit" class="admin-btn admin-btn--primary" style="padding: 0.4rem 0.8rem; font-size: 0.85rem; background: #10b981; border: none; color: white;">
                                                        Approve
                                                    </button>
                                                </form>
                                                <form action="{{ route('admin.moderation.authors.reject', ['locale' => app()->getLocale(), 'user' => $author->id]) }}" method="POST" style="display: inline; margin: 0;">
                                                    @csrf
                                                    <button type="submit" class="admin-btn admin-btn--secondary" style="padding: 0.4rem 0.8rem; font-size: 0.85rem; border-color: #ef4444; color: #ef4444; background: transparent;" onclick="return confirm('Are you sure you want to reject this registration?')">
                                                        Reject
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                <!-- Profiles Tab Content -->
                <div id="tab-content-profiles" class="tab-content" style="display: none;">
                    @if ($pendingProfiles->isEmpty())
                        <p style="color: var(--text-muted); text-align: center; padding: 1.5rem 0;">No pending profile updates.</p>
                    @else
                        @foreach ($pendingProfiles as $draft)
                            <div class="moderation-item" style="border-bottom: 1px solid var(--border-color); padding: 1.5rem 0;">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; flex-wrap: wrap;">
                                    <div>
                                        <h3 style="margin: 0; font-size: 1.1rem; font-weight: 700;">{{ $draft->name }} (Profile Edit)</h3>
                                        <p style="margin: 0.25rem 0 0 0; font-size: 0.85rem; color: var(--text-muted);">
                                            User: <strong>{{ $draft->user->name }}</strong> | Email: <code>{{ $draft->user->email }}</code>
                                        </p>
                                    </div>
                                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                                        <button onclick="toggleProfileDiff({{ $draft->id }})" class="admin-btn admin-btn--secondary" style="padding: 0.4rem 0.8rem; font-size: 0.85rem;">
                                            View Changes
                                        </button>
                                        <form action="{{ route('admin.moderation.profiles.approve', ['locale' => app()->getLocale(), 'pendingUserProfile' => $draft->id]) }}" method="POST" style="display: inline; margin: 0;">
                                            @csrf
                                            <button type="submit" class="admin-btn admin-btn--primary" style="padding: 0.4rem 0.8rem; font-size: 0.85rem; background: #10b981; border: none; color: white;">
                                                Approve
                                            </button>
                                        </form>
                                        <form action="{{ route('admin.moderation.profiles.reject', ['locale' => app()->getLocale(), 'pendingUserProfile' => $draft->id]) }}" method="POST" style="display: inline; margin: 0;">
                                            @csrf
                                            <button type="submit" class="admin-btn admin-btn--secondary" style="padding: 0.4rem 0.8rem; font-size: 0.85rem; border-color: #ef4444; color: #ef4444; background: transparent;" onclick="return confirm('Are you sure you want to reject these changes?')">
                                                Reject
                                            </button>
                                        </form>
                                    </div>
                                </div>

                                <div id="profile-diff-{{ $draft->id }}" class="profile-diff-drawer" style="display: none; margin-top: 1.5rem; background: rgba(0,0,0,0.15); padding: 1.5rem; border-radius: 0.5rem; border: 1px solid var(--border-color);">
                                    <table class="admin-table" style="width: 100%; border-collapse: collapse;">
                                        <thead>
                                            <tr>
                                                <th style="width: 20%;">Field</th>
                                                <th style="width: 40%;">Original Live</th>
                                                <th style="width: 40%;">Proposed Update</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><strong>Name</strong></td>
                                                <td style="color: var(--text-muted);">{{ $draft->user->name }}</td>
                                                <td style="{{ $draft->name !== $draft->user->name ? 'color: #10b981; font-weight: bold;' : '' }}">{{ $draft->name }}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Slug</strong></td>
                                                <td style="color: var(--text-muted);">{{ $draft->user->slug }}</td>
                                                <td style="{{ $draft->slug !== $draft->user->slug ? 'color: #10b981; font-weight: bold;' : '' }}">{{ $draft->slug }}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Job Title</strong></td>
                                                <td style="color: var(--text-muted);">{{ $draft->user->job_title ?: '(empty)' }}</td>
                                                <td style="{{ $draft->job_title !== $draft->user->job_title ? 'color: #10b981; font-weight: bold;' : '' }}">{{ $draft->job_title ?: '(empty)' }}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Bio</strong></td>
                                                <td style="color: var(--text-muted); white-space: pre-wrap;">{{ $draft->user->bio ?: '(empty)' }}</td>
                                                <td style="{{ $draft->bio !== $draft->user->bio ? 'color: #10b981; font-weight: bold;' : '' }}; white-space: pre-wrap;">{{ $draft->bio ?: '(empty)' }}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Avatar</strong></td>
                                                <td>
                                                    <img src="{{ $draft->user->avatarUrl() }}" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover;">
                                                </td>
                                                <td>
                                                    @if ($draft->avatar_path !== $draft->user->avatar_path)
                                                        <img src="{{ $draft->avatarUrl() }}" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover; border: 2px solid #10b981;">
                                                        <span style="display: block; font-size: 0.75rem; color: #10b981;">(New Avatar)</span>
                                                    @else
                                                        <img src="{{ $draft->user->avatarUrl() }}" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover; opacity: 0.5;">
                                                    @endif
                                                </td>
                                            </tr>
                                            @foreach (['github_url' => 'GitHub', 'linkedin_url' => 'LinkedIn', 'twitter_url' => 'Twitter/X', 'website_url' => 'Website'] as $field => $label)
                                                <tr>
                                                    <td><strong>{{ $label }}</strong></td>
                                                    <td style="color: var(--text-muted);">{{ $draft->user->$field ?: '(empty)' }}</td>
                                                    <td style="{{ $draft->$field !== $draft->user->$field ? 'color: #10b981; font-weight: bold;' : '' }}">{{ $draft->$field ?: '(empty)' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>

                <!-- Articles Tab Content -->
                <div id="tab-content-articles" class="tab-content" style="display: none;">
                    @if ($pendingArticles->isEmpty())
                        <p style="color: var(--text-muted); text-align: center; padding: 1.5rem 0;">No pending article translations draft updates.</p>
                    @else
                        @foreach ($pendingArticles as $draft)
                            <div class="moderation-item" style="border-bottom: 1px solid var(--border-color); padding: 1.5rem 0;">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; flex-wrap: wrap;">
                                    <div>
                                        <h3 style="margin: 0; font-size: 1.1rem; font-weight: 700;">{{ $draft->title }}</h3>
                                        <p style="margin: 0.25rem 0 0 0; font-size: 0.85rem; color: var(--text-muted);">
                                            By <strong>{{ $draft->article->author->name ?? 'Unknown' }}</strong> | Locale: <span class="locale-badge locale-badge--active" style="text-transform: uppercase;">{{ $draft->locale }}</span> | Slug: <code>{{ $draft->article->slug }}</code>
                                        </p>
                                    </div>
                                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                                        <button onclick="toggleArticleDiff({{ $draft->id }})" class="admin-btn admin-btn--secondary" style="padding: 0.4rem 0.8rem; font-size: 0.85rem;">
                                            View Changes
                                        </button>
                                        <form action="{{ route('admin.moderation.articles.approve', ['locale' => app()->getLocale(), 'pendingArticleTranslation' => $draft->id]) }}" method="POST" style="display: inline; margin: 0;">
                                            @csrf
                                            <button type="submit" class="admin-btn admin-btn--primary" style="padding: 0.4rem 0.8rem; font-size: 0.85rem; background: #10b981; border: none; color: white;">
                                                Approve
                                            </button>
                                        </form>
                                        <form action="{{ route('admin.moderation.articles.reject', ['locale' => app()->getLocale(), 'pendingArticleTranslation' => $draft->id]) }}" method="POST" style="display: inline; margin: 0;">
                                            @csrf
                                            <button type="submit" class="admin-btn admin-btn--secondary" style="padding: 0.4rem 0.8rem; font-size: 0.85rem; border-color: #ef4444; color: #ef4444; background: transparent;" onclick="return confirm('Are you sure you want to reject this draft?')">
                                                Reject
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                
                                @php
                                    $liveTranslation = $draft->article->translate($draft->locale);
                                    $liveTitle = $liveTranslation ? $liveTranslation->title : '';
                                    $liveDescription = $liveTranslation ? $liveTranslation->description : '';
                                    $liveContent = $liveTranslation ? $liveTranslation->content : '';
                                    $liveFaq = $liveTranslation && $liveTranslation->faq ? json_encode($liveTranslation->faq, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : '';
                                    $draftFaq = $draft->faq ? json_encode($draft->faq, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : '';
                                @endphp
                                
                                <div id="article-diff-{{ $draft->id }}" class="article-diff-drawer" style="display: none; margin-top: 1.5rem; background: rgba(0,0,0,0.15); padding: 1.5rem; border-radius: 0.5rem; border: 1px solid var(--border-color);">
                                    <h4 style="margin: 0 0 0.5rem 0; font-size: 0.95rem; font-weight: 700; color: var(--text-color);">Title Comparison</h4>
                                    <div id="diff-title-{{ $draft->id }}" class="diff-container" data-original="{{ $liveTitle }}" data-draft="{{ $draft->title }}" style="margin-bottom: 1.5rem;"></div>
                                    
                                    <h4 style="margin: 0 0 0.5rem 0; font-size: 0.95rem; font-weight: 700; color: var(--text-color);">Description Comparison</h4>
                                    <div id="diff-desc-{{ $draft->id }}" class="diff-container" data-original="{{ $liveDescription }}" data-draft="{{ $draft->description }}" style="margin-bottom: 1.5rem;"></div>
                                    
                                    <h4 style="margin: 0 0 0.5rem 0; font-size: 0.95rem; font-weight: 700; color: var(--text-color);">Content (Markdown) Comparison</h4>
                                    <div id="diff-content-{{ $draft->id }}" class="diff-container" data-original="{{ $liveContent }}" data-draft="{{ $draft->content }}" style="margin-bottom: 1.5rem;"></div>

                                    @if($liveFaq || $draftFaq)
                                        <h4 style="margin: 0 0 0.5rem 0; font-size: 0.95rem; font-weight: 700; color: var(--text-color);">FAQ Comparison</h4>
                                        <div id="diff-faq-{{ $draft->id }}" class="diff-container" data-original="{{ $liveFaq }}" data-draft="{{ $draftFaq }}"></div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>

                <!-- Reports Tab Content -->
                <div id="tab-content-reports" class="tab-content" style="display: none;">
                    @if ($reports->isEmpty())
                        <p style="color: var(--text-muted); text-align: center; padding: 1.5rem 0;">No pending content reports/complaints.</p>
                    @else
                        <div class="table-responsive">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Report ID</th>
                                        <th>Target Content</th>
                                        <th>Reason for Complaint</th>
                                        <th>Reporter</th>
                                        <th>Date Reported</th>
                                        <th class="text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($reports as $report)
                                        <tr>
                                            <td><code>#{{ $report->id }}</code></td>
                                            <td>
                                                @if ($report->reportable)
                                                    @if ($report->reportable_type === 'App\Models\Article')
                                                        <strong>Article:</strong> 
                                                        <a href="{{ $report->reportable->url() }}" target="_blank" style="color: var(--primary-color);">
                                                            {{ $report->reportable->slug }}
                                                        </a>
                                                    @elseif ($report->reportable_type === 'App\Models\User')
                                                        <strong>Author Profile:</strong> 
                                                        <a href="{{ route('authors.show', ['locale' => app()->getLocale(), 'slug' => $report->reportable->slug]) }}" target="_blank" style="color: var(--primary-color);">
                                                            {{ $report->reportable->name }}
                                                        </a>
                                                    @else
                                                        {{ class_basename($report->reportable_type) }} (ID: {{ $report->reportable_id }})
                                                    @endif
                                                @else
                                                    <span style="color: #ef4444; font-style: italic;">Deleted Content (ID: {{ $report->reportable_id }})</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span style="font-weight: 500;">{{ $report->reason }}</span>
                                                @if($report->screenshot_path)
                                                    <div style="margin-top: 0.25rem;">
                                                        <a href="{{ $report->screenshotUrl() }}" target="_blank" style="font-size: 0.8rem; color: var(--primary-color); text-decoration: underline;">
                                                            🖼️ View Screenshot
                                                        </a>
                                                    </div>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($report->user)
                                                    {{ $report->user->name }} (<code>{{ $report->user->email }}</code>)
                                                @else
                                                    <span style="color: var(--text-muted); font-style: italic;">Anonymous Guest</span>
                                                @endif
                                            </td>
                                            <td class="text-nowrap">{{ $report->created_at->diffForHumans() }}</td>
                                            <td class="text-right" style="display: flex; gap: 0.5rem; justify-content: flex-end; align-items: center;">
                                                @if ($report->reportable)
                                                    <form action="{{ route('admin.moderation.reports.action', ['locale' => app()->getLocale(), 'report' => $report->id]) }}" method="POST" style="display: inline; margin: 0;">
                                                        @csrf
                                                        <button type="submit" class="admin-btn admin-btn--primary" style="padding: 0.4rem 0.8rem; font-size: 0.85rem; background: #ef4444; border: none; color: white;" onclick="return confirm('Are you sure you want to suspend/block this content?')">
                                                            Suspend Content
                                                        </button>
                                                    </form>
                                                @endif
                                                <form action="{{ route('admin.moderation.reports.dismiss', ['locale' => app()->getLocale(), 'report' => $report->id]) }}" method="POST" style="display: inline; margin: 0;">
                                                    @csrf
                                                    <button type="submit" class="admin-btn admin-btn--secondary" style="padding: 0.4rem 0.8rem; font-size: 0.85rem; border-color: var(--border-color); color: var(--text-color); background: transparent;">
                                                        Dismiss Report
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    @endif

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



<script>
function switchModerationTab(tabName) {
    document.getElementById('tab-content-authors').style.display = 'none';
    document.getElementById('tab-content-profiles').style.display = 'none';
    document.getElementById('tab-content-articles').style.display = 'none';
    document.getElementById('tab-content-reports').style.display = 'none';

    document.getElementById('tab-btn-authors').classList.remove('tab-btn--active');
    document.getElementById('tab-btn-profiles').classList.remove('tab-btn--active');
    document.getElementById('tab-btn-articles').classList.remove('tab-btn--active');
    document.getElementById('tab-btn-reports').classList.remove('tab-btn--active');

    document.getElementById('tab-content-' + tabName).style.display = 'block';
    document.getElementById('tab-btn-' + tabName).classList.add('tab-btn--active');
}

function toggleArticleDiff(id) {
    const drawer = document.getElementById('article-diff-' + id);
    if (drawer.style.display === 'none') {
        drawer.style.display = 'block';
        renderContainerDiff('diff-title-' + id);
        renderContainerDiff('diff-desc-' + id);
        renderContainerDiff('diff-content-' + id);
        
        const faqContainer = document.getElementById('diff-faq-' + id);
        if (faqContainer) {
            renderContainerDiff('diff-faq-' + id);
        }
    } else {
        drawer.style.display = 'none';
    }
}

function toggleProfileDiff(id) {
    const drawer = document.getElementById('profile-diff-' + id);
    drawer.style.display = drawer.style.display === 'none' ? 'block' : 'none';
}

function renderContainerDiff(containerId) {
    const container = document.getElementById(containerId);
    if (!container || container.getAttribute('data-rendered')) return;
    
    const original = container.getAttribute('data-original') || '';
    const draft = container.getAttribute('data-draft') || '';
    
    container.innerHTML = generateDiff(original, draft);
    container.setAttribute('data-rendered', 'true');
}

function escapeHtml(text) {
    return text
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function generateDiff(original, draft) {
    if (!original && draft) {
        return draft.split('\n').map(line => `<div class="diff-line diff-line--added"><span class="diff-prefix">+</span>${escapeHtml(line)}</div>`).join('');
    }
    if (original && !draft) {
        return original.split('\n').map(line => `<div class="diff-line diff-line--deleted"><span class="diff-prefix">-</span>${escapeHtml(line)}</div>`).join('');
    }
    
    const origLines = original.split('\n');
    const draftLines = draft.split('\n');
    
    let html = '';
    let i = 0, j = 0;
    while (i < origLines.length || j < draftLines.length) {
        if (i < origLines.length && j < draftLines.length) {
            if (origLines[i].trim() === draftLines[j].trim()) {
                html += `<div class="diff-line diff-line--unchanged"><span class="diff-prefix">&nbsp;</span>${escapeHtml(origLines[i])}</div>`;
                i++;
                j++;
            } else {
                let foundInOrig = -1;
                for (let k = i; k < Math.min(i + 15, origLines.length); k++) {
                    if (origLines[k].trim() === draftLines[j].trim()) {
                        foundInOrig = k;
                        break;
                    }
                }
                
                if (foundInOrig !== -1) {
                    for (let k = i; k < foundInOrig; k++) {
                        html += `<div class="diff-line diff-line--deleted"><span class="diff-prefix">-</span>${escapeHtml(origLines[k])}</div>`;
                    }
                    i = foundInOrig;
                } else {
                    let foundInDraft = -1;
                    for (let k = j; k < Math.min(j + 15, draftLines.length); k++) {
                        if (origLines[i].trim() === draftLines[k].trim()) {
                            foundInDraft = k;
                            break;
                        }
                    }
                    
                    if (foundInDraft !== -1) {
                        for (let k = j; k < foundInDraft; k++) {
                            html += `<div class="diff-line diff-line--added"><span class="diff-prefix">+</span>${escapeHtml(draftLines[k])}</div>`;
                        }
                        j = foundInDraft;
                    } else {
                        html += `<div class="diff-line diff-line--deleted"><span class="diff-prefix">-</span>${escapeHtml(origLines[i])}</div>`;
                        html += `<div class="diff-line diff-line--added"><span class="diff-prefix">+</span>${escapeHtml(draftLines[j])}</div>`;
                        i++;
                        j++;
                    }
                }
            }
        } else if (i < origLines.length) {
            html += `<div class="diff-line diff-line--deleted"><span class="diff-prefix">-</span>${escapeHtml(origLines[i])}</div>`;
            i++;
        } else if (j < draftLines.length) {
            html += `<div class="diff-line diff-line--added"><span class="diff-prefix">+</span>${escapeHtml(draftLines[j])}</div>`;
            j++;
        }
    }
    return html;
}
</script>
</x-layout>
