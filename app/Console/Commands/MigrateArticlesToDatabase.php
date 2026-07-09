<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\ArticleTranslation;
use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\Tag;
use App\Models\TagTranslation;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

class MigrateArticlesToDatabase extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'app:migrate-articles-to-database {--update : Update existing database translations if they differ from files}';

    /**
     * The console command description.
     */
    protected $description = 'Migrate static Markdown articles from resources/content to the database';
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting article migration...');

        // 1. Get or create a super-admin user as the author
        $author = User::where('role', User::ROLE_SUPER_ADMIN)->first();
        if (!$author) {
            $author = User::create([
                'name' => 'Default Admin',
                'email' => 'admin@devsense.work',
                'password' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(32)),
                'role' => User::ROLE_SUPER_ADMIN,
                'slug' => 'default-admin',
            ]);
            $this->info("Created default admin user: {$author->email}");
        }

        $contentDir = resource_path('content');
        if (!File::isDirectory($contentDir)) {
            $this->error("Content directory not found: {$contentDir}");
            return 1;
        }

        $locales = File::directories($contentDir);
        $count = 0;

        DB::transaction(function () use ($locales, $author, &$count) {
            foreach ($locales as $localePath) {
                $locale = basename($localePath);
                
                $categories = File::directories($localePath);
                foreach ($categories as $categoryPath) {
                    $categorySlug = basename($categoryPath);

                    // Find or create Category
                    $category = Category::firstOrCreate(['slug' => $categorySlug]);

                    $translationsDict = [
                        'php' => [
                            'en' => 'PHP', 'ru' => 'PHP', 'ua' => 'PHP', 'bg' => 'PHP',
                        ],
                        'tools' => [
                            'en' => 'Tools', 'ru' => 'Инструменты', 'ua' => 'Інструменти', 'bg' => 'Инструменти',
                        ],
                        'microservices' => [
                            'en' => 'Microservices', 'ru' => 'Микросервисы', 'ua' => 'Мікросервіси', 'bg' => 'Микроуслуги',
                        ],
                        'architecture' => [
                            'en' => 'Architecture', 'ru' => 'Архитектура', 'ua' => 'Архітектура', 'bg' => 'Архитектура',
                        ],
                        'jobs' => [
                            'en' => 'Careers & Jobs', 'ru' => 'Вакансии', 'ua' => 'Вакансії', 'bg' => 'Работни места',
                        ],
                        'security' => [
                            'en' => 'Security', 'ru' => 'Безопасность', 'ua' => 'Безпека', 'bg' => 'Сигурност',
                        ],
                    ];

                    $categoryName = $translationsDict[$categorySlug][$locale] ?? Str::title(str_replace('-', ' ', $categorySlug));

                    // Create or update Category Translation
                    CategoryTranslation::updateOrCreate([
                        'category_id' => $category->id,
                        'locale' => $locale,
                    ], [
                        'name' => $categoryName,
                    ]);

                    // Generate a default Tag for this category
                    $tag = Tag::firstOrCreate(['slug' => $categorySlug]);
                    TagTranslation::updateOrCreate([
                        'tag_id' => $tag->id,
                        'locale' => $locale,
                    ], [
                        'name' => $categoryName,
                    ]);

                    $files = File::files($categoryPath);
                    foreach ($files as $file) {
                        if ($file->getExtension() !== 'md') {
                            continue;
                        }

                        $slug = $file->getBasename('.md');
                        $rawContent = File::get($file->getRealPath());

                        $title = Str::title(str_replace('-', ' ', $slug));
                        $description = '';
                        $contentMarkdown = $rawContent;
                        $faq = null;

                        // Parse YAML front matter
                        if (preg_match('/^---\r?\n(.*?)\r?\n---\r?\n(.*)/s', $rawContent, $matches)) {
                            try {
                                $yaml = Yaml::parse($matches[1]);
                                if (isset($yaml['title'])) {
                                    $title = $yaml['title'];
                                }
                                if (isset($yaml['description'])) {
                                    $description = $yaml['description'];
                                }
                                if (isset($yaml['faq'])) {
                                    $faq = $yaml['faq'];
                                }
                                $contentMarkdown = $matches[2];
                            } catch (\Exception $e) {
                                $this->warn("Failed parsing YAML in {$file->getRealPath()}: " . $e->getMessage());
                            }
                        }

                        // Find or create Article
                        $article = Article::firstOrCreate([
                            'slug' => $slug,
                        ], [
                            'author_id' => $author->id,
                            'is_published' => true,
                            'is_approved' => true,
                            'published_at' => now(),
                        ]);

                        // Sync category pivot
                        $article->categories()->syncWithoutDetaching([$category->id]);

                        // Sync pivot tag
                        $article->tags()->syncWithoutDetaching([$tag->id]);

                        // Check if translation already exists
                        $existingTranslation = ArticleTranslation::where('article_id', $article->id)
                            ->where('locale', $locale)
                            ->first();

                        if ($existingTranslation) {
                            if (!$this->option('update')) {
                                continue;
                            }

                            // Normalise line endings for comparison
                            $normalizedNewContent = trim(str_replace("\r\n", "\n", $contentMarkdown));
                            $normalizedOldContent = trim(str_replace("\r\n", "\n", $existingTranslation->content));

                            $hasChanged = $existingTranslation->title !== $title
                                || $existingTranslation->description !== $description
                                || $normalizedOldContent !== $normalizedNewContent
                                || json_encode($existingTranslation->faq) !== json_encode($faq);

                            if (!$hasChanged) {
                                continue;
                            }
                        }

                        // Create or update Translation
                        ArticleTranslation::updateOrCreate([
                            'article_id' => $article->id,
                            'locale' => $locale,
                        ], [
                            'title' => $title,
                            'description' => $description,
                            'content' => trim($contentMarkdown),
                            'faq' => $faq,
                        ]);

                        $count++;
                    }
                }
            }
        });

        $this->info("Successfully migrated {$count} new or updated article translations to the database!");
        return 0;
    }
}
