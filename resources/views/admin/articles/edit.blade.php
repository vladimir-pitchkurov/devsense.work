<x-layout title="Admin - {{ __('ui.admin.edit_article') }} | DevSense" description="Edit existing article or documentation guide">
<div class="admin-container">
    <div class="admin-header">
        <h1 class="admin-title">{{ __('ui.admin.edit_article') }}</h1>
        <a href="{{ route('admin.articles.index', ['locale' => app()->getLocale()]) }}" class="admin-btn admin-btn--secondary">
            {{ __('ui.admin.back_to_list') }}
        </a>
    </div>

    <form action="{{ route('admin.articles.update', ['locale' => app()->getLocale(), 'article' => $article->id]) }}" method="POST" class="admin-form">
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
                        <input type="text" name="slug" id="slug" value="{{ old('slug', $article->slug) }}" required placeholder="e.g. php-8-4-property-hooks" class="form-input">
                        <p class="form-help">{{ __('ui.admin.slug_help') }}</p>
                    </div>

                    <div class="form-group">
                        <label class="form-label">{{ __('ui.admin.categories_select') }}</label>
                        <div class="checkbox-group">
                            @foreach($categories as $category)
                                <label class="checkbox-label">
                                    <input type="checkbox" name="categories[]" value="{{ $category->id }}" {{ in_array($category->id, old('categories', $article->categories->pluck('id')->toArray())) ? 'checked' : '' }}>
                                    <span>{{ $category->slug }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="custom_url" class="form-label">{{ __('ui.admin.custom_url') }}</label>
                        <input type="text" name="custom_url" id="custom_url" value="{{ old('custom_url', $article->custom_url) }}" placeholder="e.g. /my-custom-path" class="form-input">
                        <p class="form-help">{{ __('ui.admin.custom_url_help') }}</p>
                    </div>

                    <div class="form-group">
                        <label class="form-label">{{ __('ui.admin.tags') }}</label>
                        <div class="checkbox-group">
                            @foreach($tags as $tag)
                                <label class="checkbox-label">
                                    <input type="checkbox" name="tags[]" value="{{ $tag->id }}" {{ in_array($tag->id, old('tags', $article->tags->pluck('id')->toArray())) ? 'checked' : '' }}>
                                    <span>{{ $tag->slug }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="checkbox-label checkbox-label--large">
                            <input type="checkbox" name="is_published" value="1" {{ old('is_published', $article->is_published) ? 'checked' : '' }}>
                            <span>{{ __('ui.admin.publish_article') }}</span>
                        </label>
                    </div>

                    <button type="submit" class="admin-btn admin-btn--primary admin-btn--full">
                        {{ __('ui.admin.update_article') }}
                    </button>
                </div>
            </div>

            <!-- Right Side: Translations (multilanguage fields) -->
            <div class="form-content">
                <div class="admin-card">
                    <div class="tabs-header-container">
                        <div class="tabs-header">
                            @foreach(\App\Http\Middleware\SetLocale::LOCALE_LABELS as $loc => $label)
                                <button type="button" class="tab-btn {{ $loop->first ? 'active' : '' }}" onclick="switchTab(event, 'tab-{{ $loc }}')">
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>
                        <div class="tabs-actions">
                            <a href="{{ route('admin.articles.template', ['locale' => app()->getLocale()]) }}" class="admin-btn admin-btn--secondary" title="Download Markdown template for formatting guidelines">
                                <i class="fa fa-download" style="margin-right: 0.5rem;"></i> {{ __('ui.admin.download_template') }}
                            </a>
                            <button type="button" class="admin-btn admin-btn--secondary" onclick="triggerImport()" title="Import content from a Markdown file to pre-populate fields for active tab">
                                <i class="fa fa-upload" style="margin-right: 0.5rem;"></i> {{ __('ui.admin.import_markdown') }}
                            </button>
                            <input type="file" id="import_md_file" accept=".md" style="display: none;" onchange="handleImport(event)">
                        </div>
                    </div>

                    <!-- Locale Tab Contents -->
                    @foreach(\App\Http\Middleware\SetLocale::SUPPORTED_LOCALES as $loc)
                        @php
                            $translation = $article->getTranslationOrDraft($loc);
                        @endphp
                        <div id="tab-{{ $loc }}" class="tab-pane {{ $loop->first ? 'active' : '' }}">
                            <h3 class="tab-pane-title">{{ __('ui.admin.locale_content', ['locale' => strtoupper($loc)]) }}</h3>

                            <div class="form-group">
                                <label for="title_{{ $loc }}" class="form-label">{{ __('ui.admin.title', ['locale' => strtoupper($loc)]) }}</label>
                                <input type="text" name="translations[{{ $loc }}][title]" id="title_{{ $loc }}" value="{{ old("translations.{$loc}.title", $translation?->title) }}" placeholder="{{ __('ui.admin.title_placeholder', ['locale' => $loc]) }}" class="form-input">
                            </div>

                            <div class="form-group">
                                <label for="description_{{ $loc }}" class="form-label">{{ __('ui.admin.meta_description', ['locale' => strtoupper($loc)]) }}</label>
                                <textarea name="translations[{{ $loc }}][description]" id="description_{{ $loc }}" rows="2" placeholder="{{ __('ui.admin.meta_description_placeholder') }}" class="form-input">{{ old("translations.{$loc}.description", $translation?->description) }}</textarea>
                            </div>

                            <div class="form-group">
                                <label for="content_{{ $loc }}" class="form-label">{{ __('ui.admin.content_label', ['locale' => strtoupper($loc)]) }}</label>
                                <textarea name="translations[{{ $loc }}][content]" id="content_{{ $loc }}" rows="15" placeholder="{{ __('ui.admin.content_placeholder') }}" class="form-input form-textarea-code">{{ old("translations.{$loc}.content", $translation?->content) }}</textarea>
                            </div>

                            <div class="form-group">
                                <label for="faq_{{ $loc }}" class="form-label">{{ __('ui.admin.faq_label', ['locale' => strtoupper($loc)]) }}</label>
                                <textarea name="translations[{{ $loc }}][faq]" id="faq_{{ $loc }}" rows="3" placeholder='[{"question": "Q1?", "answer": "A1"}]' class="form-input form-textarea-code">{{ old("translations.{$loc}.faq", $translation?->faq ? json_encode($translation->faq, JSON_UNESCAPED_UNICODE) : '') }}</textarea>
                                <p class="form-help">{{ __('ui.admin.faq_help') }}</p>
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
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.js"></script>
<script>
const locales = @json(\App\Http\Middleware\SetLocale::SUPPORTED_LOCALES);
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

let activeLocale = 'en';

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
    activeLocale = locale;
    if (editors && editors[locale]) {
        setTimeout(() => {
            editors[locale].codemirror.refresh();
        }, 50);
    }
}

function triggerImport() {
    document.getElementById('import_md_file').click();
}

function handleImport(event) {
    const file = event.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = function(e) {
        const text = e.target.result;
        const parsed = parseFrontMatter(text);
        
        if (parsed.attributes.title) {
            const titleInput = document.getElementById('title_' + activeLocale);
            if (titleInput) titleInput.value = parsed.attributes.title;
        }
        if (parsed.attributes.description) {
            const descInput = document.getElementById('description_' + activeLocale);
            if (descInput) descInput.value = parsed.attributes.description;
        }
        if (parsed.attributes.slug) {
            const slugInput = document.getElementById('slug');
            if (slugInput) slugInput.value = parsed.attributes.slug;
        }
        if (parsed.attributes.faq) {
            const faqInput = document.getElementById('faq_' + activeLocale);
            if (faqInput) {
                faqInput.value = typeof parsed.attributes.faq === 'string'
                    ? parsed.attributes.faq
                    : JSON.stringify(parsed.attributes.faq, null, 2);
            }
        }
        if (parsed.body) {
            const contentInput = document.getElementById('content_' + activeLocale);
            if (contentInput) {
                contentInput.value = parsed.body.trim();
                if (editors[activeLocale]) {
                    editors[activeLocale].value(parsed.body.trim());
                }
            }
        }
    };
    reader.readAsText(file);
    event.target.value = '';
}

function parseFrontMatter(text) {
    const result = {
        attributes: {},
        body: text
    };
    
    const match = text.match(/^---\r?\n([\s\S]*?)\r?\n---\r?\n?/);
    if (!match) {
        return result;
    }
    
    const frontMatter = match[1];
    result.body = text.slice(match[0].length);
    
    const lines = frontMatter.split(/\r?\n/);
    let currentKey = null;
    let currentArray = null;
    let currentObj = null;
    
    for (let line of lines) {
        if (!line.trim()) continue;
        
        const topLevelMatch = line.match(/^([a-zA-Z0-9_-]+)\s*:\s*(.*)$/);
        if (topLevelMatch) {
            const key = topLevelMatch[1].trim();
            let val = topLevelMatch[2].trim();
            
            if ((val.startsWith('"') && val.endsWith('"')) || (val.startsWith("'") && val.endsWith("'"))) {
                val = val.slice(1, -1);
            }
            
            currentKey = key;
            currentArray = null;
            currentObj = null;
            
            if (val === '') {
                result.attributes[key] = null;
            } else {
                result.attributes[key] = val;
            }
            continue;
        }
        
        const arrayItemMatch = line.match(/^\s*-\s*(.*)$/);
        if (arrayItemMatch && currentKey) {
            if (!Array.isArray(result.attributes[currentKey])) {
                result.attributes[currentKey] = [];
            }
            currentArray = result.attributes[currentKey];
            
            const innerVal = arrayItemMatch[1].trim();
            const innerKvMatch = innerVal.match(/^([a-zA-Z0-9_-]+)\s*:\s*(.*)$/);
            if (innerKvMatch) {
                currentObj = {};
                const k = innerKvMatch[1].trim();
                let v = innerKvMatch[2].trim();
                if ((v.startsWith('"') && v.endsWith('"')) || (v.startsWith("'") && v.endsWith("'"))) {
                    v = v.slice(1, -1);
                }
                currentObj[k] = v;
                currentArray.push(currentObj);
            } else {
                let v = innerVal;
                if ((v.startsWith('"') && v.endsWith('"')) || (v.startsWith("'") && v.endsWith("'"))) {
                    v = v.slice(1, -1);
                }
                currentArray.push(v);
                currentObj = null;
            }
            continue;
        }
        
        const kvMatch = line.match(/^\s*([a-zA-Z0-9_-]+)\s*:\s*(.*)$/);
        if (kvMatch && currentObj) {
            const k = kvMatch[1].trim();
            let v = kvMatch[2].trim();
            if ((v.startsWith('"') && v.endsWith('"')) || (v.startsWith("'") && v.endsWith("'"))) {
                v = v.slice(1, -1);
            }
            currentObj[k] = v;
            continue;
        }
    }
    
    return result;
}
</script>
@endpush
</x-layout>
