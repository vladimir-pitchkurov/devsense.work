<x-layout :title="__('ui.welcome.title')" :description="__('ui.welcome.description')">
    <div class="search-layout">
        <!-- Sidebar filters -->
        <aside class="search-sidebar" id="search-sidebar">
            <div class="search-sidebar__inner">
                <button class="mobile-filter-close" onclick="document.getElementById('search-sidebar').classList.remove('open')">
                    &times;
                </button>
                <form action="{{ route(request()->route()->getName()) }}" method="GET" class="search-form" id="search-form">
                    <!-- Search input -->
                    <div class="search-group">
                        <label for="search-input" class="search-label">{{ __('ui.search.placeholder') }}</label>
                        <div class="search-input-wrapper">
                            <input 
                                type="text" 
                                name="q" 
                                id="search-input" 
                                class="search-input" 
                                placeholder="{{ __('ui.search.placeholder') }}" 
                                value="{{ $search }}"
                                autocomplete="off"
                            >
                            @if($search)
                                <a href="{{ route(request()->route()->getName(), array_merge(request()->route('category_slug') ? ['locale' => app()->getLocale()] : [], request()->except('q', 'page'))) }}" class="search-clear" title="{{ __('ui.search.clear_filters') }}">&times;</a>
                            @endif
                        </div>
                    </div>

                    <!-- Category Selector (only show if not pre-filtered by route parameter) -->
                    @if(!request()->route('category_slug'))
                        <div class="search-group">
                            <span class="search-label">{{ __('ui.search.categories') }}</span>
                            <div class="search-options">
                                <a href="{{ route('home', array_filter(request()->except('category', 'page'))) }}" class="search-option {{ !$selectedCategory ? 'active' : '' }}">
                                    {{ __('ui.search.all_categories') }}
                                </a>
                                @foreach($categories as $cat)
                                    @php
                                        $catName = $cat->translate()?->name ?? $cat->slug;
                                    @endphp
                                    <a href="{{ route('home', array_merge(request()->except('page'), ['category' => $cat->slug])) }}" class="search-option {{ $selectedCategory && $selectedCategory->id === $cat->id ? 'active' : '' }}">
                                        {{ $catName }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <input type="hidden" name="category" value="{{ request()->route('category_slug') }}">
                    @endif

                    <!-- Tag Selector -->
                    @if($tags->isNotEmpty())
                        <div class="search-group">
                            <span class="search-label">{{ __('ui.search.tags') }}</span>
                            <div class="tag-cloud">
                                @foreach($tags as $t)
                                    @php
                                        $tagName = $t->translate()?->name ?? $t->slug;
                                    @endphp
                                    <a href="{{ route(request()->route()->getName(), array_merge(request()->route('category_slug') ? ['locale' => app()->getLocale()] : [], request()->except('page'), ['tag' => $t->slug])) }}" class="tag-pill {{ $selectedTag && $selectedTag->id === $t->id ? 'active' : '' }}">
                                        #{{ $tagName }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- Author Selector -->
                    @if($authors->isNotEmpty())
                        <div class="search-group">
                            <span class="search-label">{{ __('ui.search.authors') }}</span>
                            <div class="search-options">
                                @foreach($authors as $auth)
                                    <a href="{{ route(request()->route()->getName(), array_merge(request()->route('category_slug') ? ['locale' => app()->getLocale()] : [], request()->except('page'), ['author' => $auth->slug])) }}" class="search-option {{ $selectedAuthor && $selectedAuthor->id === $auth->id ? 'active' : '' }}">
                                        {{ $auth->name }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- Sort -->
                    <div class="search-group">
                        <label for="sort-select" class="search-label">{{ __('ui.search.sort_by') }}</label>
                        <select name="sort" id="sort-select" class="search-select" onchange="this.form.submit()">
                            <option value="latest" {{ $sort === 'latest' ? 'selected' : '' }}>{{ __('ui.search.latest') }}</option>
                            <option value="oldest" {{ $sort === 'oldest' ? 'selected' : '' }}>{{ __('ui.search.oldest') }}</option>
                            <option value="alphabetical" {{ $sort === 'alphabetical' ? 'selected' : '' }}>{{ __('ui.search.alphabetical') }}</option>
                        </select>
                    </div>

                    <!-- Reset filters if any active -->
                    @if($search || $selectedCategory || $selectedTag || $selectedAuthor || $sort !== 'latest')
                        <a href="{{ route(request()->route()->getName(), request()->route('category_slug') ? ['locale' => app()->getLocale()] : []) }}" class="search-reset-btn">
                            {{ __('ui.search.clear_filters') }}
                        </a>
                    @endif
                </form>
            </div>
        </aside>

        <!-- Main Articles Grid -->
        <main class="search-results">
            <!-- Filter toggle for mobile -->
            <div class="mobile-filter-bar">
                <button type="button" class="mobile-filter-toggle" onclick="document.getElementById('search-sidebar').classList.add('open')">
                    🔍 {{ __('ui.search.categories') }} & {{ __('ui.search.sort_by') }}
                </button>
            </div>

            @if($articles->isEmpty())
                <div class="search-empty">
                    <p class="search-empty__text">{{ __('ui.search.no_results') }}</p>
                    <a href="{{ route(request()->route()->getName(), request()->route('category_slug') ? ['locale' => app()->getLocale()] : []) }}" class="search-empty__btn">
                        {{ __('ui.search.clear_filters') }}
                    </a>
                </div>
            @else
                <div class="guides">
                    <ul class="guides__list">
                        @foreach ($articles as $article)
                            @php
                                $translation = $article->translate();
                                $categoryName = $article->category?->translate()?->name ?? $article->category?->slug;
                            @endphp
                            <li class="guides__item">
                                <article class="card">
                                    <header class="card__header">
                                        <div class="card__meta-top">
                                            <span class="card__category card__category--{{ $article->category?->slug }}">
                                                {{ $categoryName }}
                                            </span>
                                            <span class="card__date">
                                                {{ $article->published_at ? $article->published_at->format('M d, Y') : '' }}
                                            </span>
                                        </div>
                                        <h2 class="card__title">
                                            <a href="{{ $article->url() }}" class="card__title-link">
                                                {{ $translation?->title ?? $article->slug }}
                                            </a>
                                        </h2>
                                    </header>
                                    <div class="card__body">
                                        <p class="card__excerpt">{{ $translation?->description }}</p>
                                    </div>
                                    <footer class="card__footer">
                                        <!-- Author Row -->
                                        @if($article->author)
                                            <div class="card__author">
                                                <div class="card__author-avatar" title="{{ $article->author->name }}">
                                                    {{ substr($article->author->name, 0, 1) }}
                                                </div>
                                                <span class="card__author-name">{{ $article->author->name }}</span>
                                            </div>
                                        @endif
                                        <a href="{{ $article->url() }}" class="card__link" aria-label="{{ $translation?->title }}">
                                            Read
                                        </a>
                                    </footer>
                                </article>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="search-pagination">
                    {{ $articles->links() }}
                </div>
            @endif
        </main>
    </div>
</x-layout>
