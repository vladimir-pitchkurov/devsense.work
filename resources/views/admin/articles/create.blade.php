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

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
<style>
.EasyMDEContainer {
    background-color: rgba(var(--bg-color-rgb), 0.5) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 0.5rem !important;
    margin-top: 0.5rem;
}
.editor-toolbar {
    background-color: var(--code-header-bg) !important;
    border: none !important;
    border-bottom: 1px solid var(--border-color) !important;
    opacity: 1 !important;
    padding: 0.5rem !important;
}
.editor-toolbar button {
    color: var(--text-color) !important;
    border-radius: 4px !important;
    transition: all 0.2s !important;
}
.editor-toolbar button:hover {
    background: var(--primary-glow) !important;
    color: var(--primary-color) !important;
    border: none !important;
}
.editor-toolbar button.active {
    background: var(--primary-color) !important;
    color: #ffffff !important;
}
.CodeMirror {
    background-color: transparent !important;
    border: none !important;
    color: var(--text-color) !important;
    font-family: 'Fira Code', monospace !important;
    font-size: 0.9rem !important;
    border-radius: 0 0 0.5rem 0.5rem !important;
}
.CodeMirror-cursor {
    border-left: 2px solid var(--primary-color) !important;
}
.editor-preview {
    background-color: var(--page-bg) !important;
    color: var(--text-color) !important;
    padding: 2rem !important;
}
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.js"></script>
<script>
const locales = ['en', 'ru', 'ua', 'bg'];
const editors = {};

function customPreviewRender(plainText) {
    if (!this.parent || typeof this.parent.markdown !== 'function') {
        return plainText;
    }
    var html = this.parent.markdown(plainText);
    
    // 1. Process GFM Alerts
    html = html.replace(/<blockquote>\s*<p>\s*\[!NOTE\]([\s\S]*?)<\/p>\s*<\/blockquote>/gi, function(match, content) {
        return '<div class="markdown-alert markdown-alert-note"><p class="markdown-alert-title">Note</p><p>' + content.trim() + '</p></div>';
    });
    html = html.replace(/<blockquote>\s*<p>\s*\[!WARNING\]([\s\S]*?)<\/p>\s*<\/blockquote>/gi, function(match, content) {
        return '<div class="markdown-alert markdown-alert-warning"><p class="markdown-alert-title">Warning</p><p>' + content.trim() + '</p></div>';
    });
    html = html.replace(/<blockquote>\s*<p>\s*\[!IMPORTANT\]([\s\S]*?)<\/p>\s*<\/blockquote>/gi, function(match, content) {
        return '<div class="markdown-alert markdown-alert-important"><p class="markdown-alert-title">Important</p><p>' + content.trim() + '</p></div>';
    });
    
    // 2. Process Code Block headers if they contain comment paths
    html = html.replace(/<pre><code class="([^"]*)">((?:\/\/|#|(?:\/\*))\s*([a-zA-Z0-9_\-\.\/]+)(?:\s*\*\/)?\n)([\s\S]*?)<\/code><\/pre>/gi, function(match, langClass, commentLine, filePath, codeContent) {
        return '<div class="code-block">' +
               '<div class="code-block__header"><span class="code-block__filename">' + filePath + '</span></div>' +
               '<pre class="code-block__code"><code class="' + langClass + '">' + codeContent + '</code></pre>' +
               '</div>';
    });
    
    return '<div class="markdown-body">' + html + '</div>';
}

document.addEventListener('DOMContentLoaded', function() {
    locales.forEach(loc => {
        const el = document.getElementById('content_' + loc);
        if (el) {
            editors[loc] = new EasyMDE({
                element: el,
                spellChecker: false,
                nativeSpellcheck: false,
                autosave: {
                    enabled: false,
                },
                previewRender: customPreviewRender,
                toolbar: [
                    "bold", "italic", "heading", "|",
                    "quote", "unordered-list", "ordered-list", "|",
                    "link", "image", "table", "|",
                    "preview", "side-by-side", "fullscreen", "|",
                    {
                        name: "alert-note",
                        action: function(editor) {
                            var cm = editor.codemirror;
                            var selection = cm.getSelection();
                            cm.replaceSelection("> [!NOTE]\n> " + (selection || "Ваша заметка / Your note"));
                            cm.focus();
                        },
                        className: "fa fa-info-circle",
                        title: "Insert Note Alert",
                    },
                    {
                        name: "alert-warning",
                        action: function(editor) {
                            var cm = editor.codemirror;
                            var selection = cm.getSelection();
                            cm.replaceSelection("> [!WARNING]\n> " + (selection || "Внимание / Warning"));
                            cm.focus();
                        },
                        className: "fa fa-exclamation-triangle",
                        title: "Insert Warning Alert",
                    },
                    {
                        name: "alert-important",
                        action: function(editor) {
                            var cm = editor.codemirror;
                            var selection = cm.getSelection();
                            cm.replaceSelection("> [!IMPORTANT]\n> " + (selection || "Важно / Important"));
                            cm.focus();
                        },
                        className: "fa fa-exclamation-circle",
                        title: "Insert Important Alert",
                    },
                    {
                        name: "code-path",
                        action: function(editor) {
                            var cm = editor.codemirror;
                            var selection = cm.getSelection();
                            cm.replaceSelection("```php\n// app/Services/MediaUploadService.php\n" + (selection || " // code here\n") + "```");
                            cm.focus();
                        },
                        className: "fa fa-file-code-o",
                        title: "Insert Code Block with File Path",
                    },
                    {
                        name: "quiz",
                        action: function(editor) {
                            var cm = editor.codemirror;
                            cm.replaceSelection("<details>\n<summary>Вопрос / Question?</summary>\nПравильный ответ / Correct answer!\n</details>");
                            cm.focus();
                        },
                        className: "fa fa-question-circle",
                        title: "Insert Quiz/Spoiler",
                    }
                ]
            });
        }
    });

    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function() {
            locales.forEach(loc => {
                if (editors[loc]) {
                    editors[loc].save();
                }
            });
        });
    }
});

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

    const locale = tabId.replace('tab-', '');
    if (editors && editors[locale]) {
        setTimeout(() => {
            editors[locale].codemirror.refresh();
        }, 50);
    }
}
</script>
@endpush

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
