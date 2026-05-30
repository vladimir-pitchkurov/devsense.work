<x-layout title="Admin - Create Article | DevSense" description="Create a new article or documentation guide">
<div class="admin-container">
    <div class="admin-header">
        <h1 class="admin-title">Create New Article</h1>
        <a href="{{ route('admin.articles.index', ['locale' => app()->getLocale()]) }}" class="admin-btn admin-btn--secondary">
            Back to List
        </a>
    </div>

    <form action="{{ route('admin.articles.store', ['locale' => app()->getLocale()]) }}" method="POST" class="admin-form">
        @csrf

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
                        <input type="text" name="slug" id="slug" value="{{ old('slug') }}" required placeholder="e.g. php-8-4-property-hooks" class="form-input">
                        <p class="form-help">Must be unique and URL-friendly</p>
                    </div>

                    <div class="form-group">
                        <label for="category_id" class="form-label">Category</label>
                        <select name="category_id" id="category_id" class="form-input">
                            <option value="">-- Select Category --</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>
                                    {{ $category->slug }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Tags</label>
                        <div class="checkbox-group">
                            @foreach($tags as $tag)
                                <label class="checkbox-label">
                                    <input type="checkbox" name="tags[]" value="{{ $tag->id }}" {{ is_array(old('tags')) && in_array($tag->id, old('tags')) ? 'checked' : '' }}>
                                    <span>{{ $tag->slug }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="checkbox-label checkbox-label--large">
                            <input type="checkbox" name="is_published" value="1" {{ old('is_published') ? 'checked' : '' }}>
                            <span>Publish Article</span>
                        </label>
                    </div>

                    <button type="submit" class="admin-btn admin-btn--primary admin-btn--full">
                        Save Article
                    </button>
                </div>
            </div>

            <!-- Right Side: Translations (multilanguage fields) -->
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
                        <div id="tab-{{ $loc }}" class="tab-pane {{ $loop->first ? 'active' : '' }}">
                            <h3 class="tab-pane-title">{{ strtoupper($loc) }} Content</h3>

                            <div class="form-group">
                                <label for="title_{{ $loc }}" class="form-label">Title ({{ strtoupper($loc) }})</label>
                                <input type="text" name="translations[{{ $loc }}][title]" id="title_{{ $loc }}" value="{{ old("translations.{$loc}.title") }}" required placeholder="Article title in {{ $loc }}" class="form-input">
                            </div>

                            <div class="form-group">
                                <label for="description_{{ $loc }}" class="form-label">Meta Description ({{ strtoupper($loc) }})</label>
                                <textarea name="translations[{{ $loc }}][description]" id="description_{{ $loc }}" rows="2" placeholder="SEO meta description..." class="form-input">{{ old("translations.{$loc}.description") }}</textarea>
                            </div>

                            <div class="form-group">
                                <label for="content_{{ $loc }}" class="form-label">Content ({{ strtoupper($loc) }} - Markdown)</label>
                                <textarea name="translations[{{ $loc }}][content]" id="content_{{ $loc }}" rows="15" required placeholder="# Article Heading..." class="form-input form-textarea-code">{{ old("translations.{$loc}.content") }}</textarea>
                            </div>

                            <div class="form-group">
                                <label for="faq_{{ $loc }}" class="form-label">FAQ JSON ({{ strtoupper($loc) }})</label>
                                <textarea name="translations[{{ $loc }}][faq]" id="faq_{{ $loc }}" rows="3" placeholder='[{"question": "Q1?", "answer": "A1"}]' class="form-input form-textarea-code">{{ old("translations.{$loc}.faq") }}</textarea>
                                <p class="form-help">Optional JSON array of Q/A objects for schema.org markup</p>
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

.form-textarea-code {
    font-family: 'Fira Code', monospace;
    font-size: 0.85rem;
    tab-size: 4;
}

.form-help {
    font-size: 0.8rem;
    color: var(--text-muted);
    margin: 0;
}

.checkbox-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    max-height: 150px;
    overflow-y: auto;
    border: 1px solid var(--border-color);
    border-radius: 0.5rem;
    padding: 0.75rem;
    background-color: rgba(var(--bg-color-rgb), 0.3);
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.95rem;
    color: var(--text-color);
    cursor: pointer;
}

.checkbox-label--large {
    font-weight: 600;
    border-top: 1px solid var(--border-color);
    padding-top: 1rem;
    margin-top: 0.5rem;
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

.admin-btn--full {
    width: 100%;
}
</style>
</x-layout>
