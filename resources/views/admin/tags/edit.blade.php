<x-layout title="Admin - Edit Tag | DevSense" description="Edit an existing article tag">
<div class="admin-container">
    <div class="admin-header">
        <h1 class="admin-title">Edit Tag</h1>
        <a href="{{ route('admin.tags.index', ['locale' => app()->getLocale()]) }}" class="admin-btn admin-btn--secondary">
            Back to List
        </a>
    </div>

    <form action="{{ route('admin.tags.update', ['locale' => app()->getLocale(), 'tag' => $tag->id]) }}" method="POST" class="admin-form">
        @csrf
        @method('PUT')

        @if ($errors->any())
            <div class="admin-alert admin-alert--danger">
                <strong>Please fix the errors below:</strong>
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
                    <h2 class="card-title">Settings</h2>
                    
                    <div class="form-group">
                        <label for="slug" class="form-label">Slug</label>
                        <input type="text" name="slug" id="slug" value="{{ old('slug', $tag->slug) }}" required placeholder="e.g. oop" class="form-input">
                        <p class="form-help">Must be unique and URL-friendly</p>
                    </div>

                    <button type="submit" class="admin-btn admin-btn--primary admin-btn--full">
                        Update Tag
                    </button>
                </div>
            </div>

            <!-- Right Side: Translations -->
            <div class="form-content">
                <div class="admin-card">
                    <!-- Locale Tabs -->
                    <div class="tabs-header">
                        @foreach(['en' => 'English', 'ru' => 'Russian', 'ua' => 'Ukrainian', 'bg' => 'Bulgarian'] as $loc => $label)
                            <button type="button" class="tab-btn {{ $loop->first ? 'active' : '' }}" onclick="switchTab(event, 'tab-{{ $loc }}')">
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>

                    <!-- Locale Tab Contents -->
                    @foreach(['en', 'ru', 'ua', 'bg'] as $loc)
                        @php
                            $translation = $tag->translate($loc);
                        @endphp
                        <div id="tab-{{ $loc }}" class="tab-pane {{ $loop->first ? 'active' : '' }}">
                            <h3 class="tab-pane-title">{{ strtoupper($loc) }} Translation</h3>

                            <div class="form-group">
                                <label for="name_{{ $loc }}" class="form-label">Tag Name ({{ strtoupper($loc) }})</label>
                                <input type="text" name="translations[{{ $loc }}][name]" id="name_{{ $loc }}" value="{{ old("translations.{$loc}.name", $translation?->name) }}" required placeholder="Tag name in {{ $loc }}" class="form-input">
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
