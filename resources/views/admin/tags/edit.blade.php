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

.admin-alert--danger {
    background-color: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #ef4444;
    padding: 1rem;
    border-radius: 0.5rem;
    margin-bottom: 2rem;
}

.admin-alert--danger ul {
    margin: 0.5rem 0 0 0;
    padding-left: 1.25rem;
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 2rem;
}

@media (min-width: 992px) {
    .form-grid {
        grid-template-columns: 320px 1fr;
    }
}

.admin-card {
    background-color: var(--card-bg, rgba(255, 255, 255, 0.02));
    border: 1px solid var(--border-color);
    border-radius: 1rem;
    padding: 1.5rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
}

.card-title {
    font-family: 'Outfit', sans-serif;
    font-size: 1.25rem;
    font-weight: 700;
    margin-bottom: 1.5rem;
    color: var(--text-color);
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
}

.form-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-color);
    letter-spacing: 0.5px;
    text-transform: uppercase;
}

.form-input {
    background-color: rgba(var(--bg-color-rgb), 0.5);
    border: 1px solid var(--border-color);
    border-radius: 0.5rem;
    padding: 0.75rem;
    color: var(--text-color);
    font-family: inherit;
    font-size: 0.95rem;
    width: 100%;
    box-sizing: border-box;
}

.form-input:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(var(--primary-color-rgb), 0.15);
}

.form-help {
    font-size: 0.8rem;
    color: var(--text-muted);
    margin: 0;
}

.tabs-header {
    display: flex;
    border-bottom: 1px solid var(--border-color);
    margin-bottom: 1.5rem;
    gap: 0.5rem;
}

.tab-btn {
    background: none;
    border: none;
    padding: 0.75rem 1rem;
    color: var(--text-muted);
    font-family: inherit;
    font-weight: 600;
    font-size: 0.9rem;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    transition: color 0.2s, border-color 0.2s;
}

.tab-btn:hover {
    color: var(--text-color);
}

.tab-btn.active {
    color: var(--primary-color);
    border-bottom-color: var(--primary-color);
}

.tab-pane {
    display: none;
}

.tab-pane.active {
    display: block;
}

.tab-pane-title {
    font-family: 'Outfit', sans-serif;
    font-size: 1.15rem;
    font-weight: 700;
    margin-bottom: 1.5rem;
    color: var(--primary-color);
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

.admin-btn--primary {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%);
    color: #fff;
}

.admin-btn--secondary {
    background-color: transparent;
    border-color: var(--border-color);
}

.admin-btn--secondary:hover {
    background-color: rgba(var(--border-color-rgb), 0.1);
}

.admin-btn--full {
    width: 100%;
}

.admin-btn:hover {
    transform: translateY(-1px);
}
</style>
</x-layout>
