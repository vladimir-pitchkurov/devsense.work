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
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

class SeedTranslations extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'app:seed-translations';

    /**
     * The console command description.
     */
    protected $description = 'Seed translated Markdown articles from resources/content-translations to the database, rebuild sitemap, and ping search engines via IndexNow';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting translation seeding...');

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

        $translationsDir = resource_path('content-translations');
        if (!File::isDirectory($translationsDir)) {
            $this->error("Translations directory not found: {$translationsDir}");
            return 1;
        }

        $locales = File::directories($translationsDir);
        if (empty($locales)) {
            $this->warn("No locale directories found under {$translationsDir}");
            return 0;
        }

        // Translation dictionary for categories and tags
        $translationsDict = [
            'php' => [
                'en' => 'PHP', 'ru' => 'PHP', 'ua' => 'PHP', 'bg' => 'PHP',
                'de' => 'PHP', 'fr' => 'PHP', 'es' => 'PHP', 'it' => 'PHP'
            ],
            'tools' => [
                'en' => 'Tools', 'ru' => 'Инструменты', 'ua' => 'Інструменти', 'bg' => 'Инструменти',
                'de' => 'Werkzeuge', 'fr' => 'Outils', 'es' => 'Herramientas', 'it' => 'Strumenti'
            ],
            'microservices' => [
                'en' => 'Microservices', 'ru' => 'Микросервисы', 'ua' => 'Мікросервіси', 'bg' => 'Микроуслуги',
                'de' => 'Mikroservices', 'fr' => 'Microservices', 'es' => 'Microservicios', 'it' => 'Microservizi'
            ],
            'architecture' => [
                'en' => 'Architecture', 'ru' => 'Архитектура', 'ua' => 'Архітектура', 'bg' => 'Архитектура',
                'de' => 'Architektur', 'fr' => 'Architecture', 'es' => 'Arquitectura', 'it' => 'Architettura'
            ],
            'jobs' => [
                'en' => 'Careers & Jobs', 'ru' => 'Вакансии', 'ua' => 'Вакансії', 'bg' => 'Работни места',
                'de' => 'Karriere & Jobs', 'fr' => 'Carrières et Emplois', 'es' => 'Empleos', 'it' => 'Lavoro e Carriera'
            ]
        ];

        $count = 0;

        DB::transaction(function () use ($locales, $author, $translationsDict, &$count) {
            foreach ($locales as $localePath) {
                $locale = basename($localePath);
                
                $categories = File::directories($localePath);
                foreach ($categories as $categoryPath) {
                    $categorySlug = basename($categoryPath);

                    // Find or create Category
                    $category = Category::firstOrCreate(['slug' => $categorySlug]);
                    
                    // Create Category Translation
                    $categoryName = $translationsDict[$categorySlug][$locale] ?? Str::title(str_replace('-', ' ', $categorySlug));
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

        $this->info("Successfully seeded {$count} translated articles to the database!");

        // 2. Re-generate sitemap
        $this->info('Regenerating sitemap...');
        Artisan::call('sitemap:write');
        $this->info('Sitemap successfully regenerated.');

        // 3. Ping search engines via IndexNow
        $this->info('Pinging IndexNow API...');
        Artisan::call('seo:ping-indexnow', ['--force' => true]);
        $this->info('IndexNow API ping completed.');

        return 0;
    }
}
