<x-layout title="Admin - {{ __('ui.admin.edit_category') }} | DevSense" description="Edit an existing article category">
<div class="admin-container">
    <div class="admin-header">
        <h1 class="admin-title">{{ __('ui.admin.edit_category') }}</h1>
        <a href="{{ route('admin.categories.index', ['locale' => app()->getLocale()]) }}" class="admin-btn admin-btn--secondary">
            {{ __('ui.admin.back_to_list') }}
        </a>
    </div>

    <form action="{{ route('admin.categories.update', ['locale' => app()->getLocale(), 'category' => $category->id]) }}" method="POST" class="admin-form">
        @csrf
        @method('PUT')

        @if ($errors->any())
            <div class="admin-alert admin-alert--danger">
                <strong>{{ __('ui.admin.fix_errors') }}</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="form-grid">
            <!-- Left Side: Basic settings -->
            <div class="form-sidebar">
                <div class="admin-card">
                    <h2 class="card-title">{{ __('ui.admin.settings') }}</h2>
                    
                    <div class="form-group">
                        <label for="slug" class="form-label">{{ __('ui.admin.slug') }}</label>
                        <input type="text" name="slug" id="slug" value="{{ old('slug', $category->slug) }}" required placeholder="e.g. php-version" class="form-input">
                        <p class="form-help">{{ __('ui.admin.slug_help') }}</p>
                    </div>

                    <button type="submit" class="admin-btn admin-btn--primary admin-btn--full">
                        {{ __('ui.admin.update_category_btn') }}
                    </button>
                </div>
            </div>

            <!-- Right Side: Translations -->
            <div class="form-content">
                <div class="admin-card">
                    <!-- Locale Tabs -->
                    <div class="tabs-header">
                        @foreach(\App\Http\Middleware\SetLocale::LOCALE_LABELS as $loc => $label)
                            <button type="button" class="tab-btn {{ $loop->first ? 'active' : '' }}" onclick="switchTab(event, 'tab-{{ $loc }}')">
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>

                    <!-- Locale Tab Contents -->
                    @foreach(\App\Http\Middleware\SetLocale::SUPPORTED_LOCALES as $loc)
                        @php
                            $translation = $category->translate($loc);
                        @endphp
                        <div id="tab-{{ $loc }}" class="tab-pane {{ $loop->first ? 'active' : '' }}">
                            <h3 class="tab-pane-title">{{ __('ui.admin.locale_translation', ['locale' => strtoupper($loc)]) }}</h3>

                            <div class="form-group">
                                <label for="name_{{ $loc }}" class="form-label">{{ __('ui.admin.category_name', ['locale' => strtoupper($loc)]) }}</label>
                                <input type="text" name="translations[{{ $loc }}][name]" id="name_{{ $loc }}" value="{{ old("translations.{$loc}.name", $translation?->name) }}" required placeholder="{{ __('ui.admin.category_name_placeholder', ['locale' => $loc]) }}" class="form-input">
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </form>
</div>

<script>
function switchTab(evt, tabId) {
    var i, tabContent, tabLinks;
    tabContent = document.getElementsByClassName("tab-pane");
    for (i = 0; i < tabContent.length; i++) {
        tabContent[i].classList.remove("active");
    }
    tabLinks = document.getElementsByClassName("tab-btn");
    for (i = 0; i < tabLinks.length; i++) {
        tabLinks[i].classList.remove("active");
    }
    document.getElementById(tabId).classList.add("active");
    evt.currentTarget.classList.add("active");
}
</script>
</x-layout>
