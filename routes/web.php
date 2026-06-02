<?php

use App\Http\Controllers\ArchitectureController;
use App\Http\Controllers\AuthorController;
use App\Http\Controllers\MicroservicesController;
use App\Http\Controllers\PhpToolsController;
use App\Http\Controllers\PhpVersionController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\JobsController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SitemapController;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

Route::get('/robots.txt', RobotsController::class)->name('robots');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

Route::get('/partytown-proxy', function (Request $request) {
    $url = $request->query('url');
    if (!$url) {
        abort(400, 'Missing url parameter');
    }

    $parsed = parse_url($url);
    $host = $parsed['host'] ?? '';
    $allowed = [
        'www.googletagmanager.com',
        'googletagmanager.com',
        'www.google-analytics.com',
        'google-analytics.com',
        'region1.google-analytics.com',
    ];

    $isAllowed = false;
    foreach ($allowed as $domain) {
        if ($host === $domain || str_ends_with($host, '.' . $domain)) {
            $isAllowed = true;
            break;
        }
    }

    if (!$isAllowed) {
        abort(403, 'Forbidden host');
    }

    try {
        $response = Http::get($url);
        return response($response->body(), $response->status())
            ->header('Content-Type', $response->header('Content-Type') ?: 'application/javascript')
            ->header('Access-Control-Allow-Origin', '*');
    } catch (\Exception $e) {
        abort(502, 'Bad Gateway');
    }
})->name('partytown.proxy');

if ($indexNowKey = config('seo.indexnow_key')) {
    Route::get("/{$indexNowKey}.txt", function () use ($indexNowKey) {
        return response($indexNowKey, 200)->header('Content-Type', 'text/plain; charset=UTF-8');
    })->name('seo.indexnow.key');
}

Route::get('/', function () {
    $locale = config('app.default_site_locale', 'en');
    if (! in_array($locale, SetLocale::SUPPORTED_LOCALES, true)) {
        $locale = 'en';
    }

    return redirect('/'.$locale, 301);
});

Route::get('/login', function () {
    $locale = config('app.default_site_locale', 'en');
    return redirect('/'.$locale.'/login');
})->name('login');
Route::post('/login', [\App\Http\Controllers\Auth\LoginController::class, 'login']);
Route::get('/register', function () {
    $locale = config('app.default_site_locale', 'en');
    return redirect('/'.$locale.'/register');
})->name('register');
Route::post('/register', [\App\Http\Controllers\Auth\RegisterController::class, 'register'])->name('register.post');
Route::post('/logout', [\App\Http\Controllers\Auth\LoginController::class, 'logout'])->name('logout');


Route::prefix('{locale}')
    ->whereIn('locale', SetLocale::SUPPORTED_LOCALES)
    ->middleware(SetLocale::class)
    ->group(function () {

        Route::get('/', [HomeController::class, 'index'])->name('home');

        // Auth routes (localized)
        Route::get('/login', [\App\Http\Controllers\Auth\LoginController::class, 'showLoginForm'])->name('login.locale');
        Route::post('/login', [\App\Http\Controllers\Auth\LoginController::class, 'login'])->name('login.post');
        Route::get('/register', [\App\Http\Controllers\Auth\RegisterController::class, 'showRegistrationForm'])->name('register.locale');
        Route::post('/register', [\App\Http\Controllers\Auth\RegisterController::class, 'register']);


        Route::prefix('admin')->middleware(['auth', 'can:access-admin'])->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('admin.dashboard');
            Route::get('/dashboard', [\App\Http\Controllers\Admin\DashboardController::class, 'index']);
            Route::get('/articles', [\App\Http\Controllers\Admin\ArticlesController::class, 'index'])->name('admin.articles.index');
            Route::get('/articles/create', [\App\Http\Controllers\Admin\ArticlesController::class, 'create'])->name('admin.articles.create');
            Route::get('/articles/template', [\App\Http\Controllers\Admin\ArticlesController::class, 'downloadTemplate'])->name('admin.articles.template');
            Route::post('/articles', [\App\Http\Controllers\Admin\ArticlesController::class, 'store'])->name('admin.articles.store');
            Route::get('/articles/{article}/edit', [\App\Http\Controllers\Admin\ArticlesController::class, 'edit'])->name('admin.articles.edit');
            Route::put('/articles/{article}', [\App\Http\Controllers\Admin\ArticlesController::class, 'update'])->name('admin.articles.update');
            Route::delete('/articles/{article}', [\App\Http\Controllers\Admin\ArticlesController::class, 'destroy'])->name('admin.articles.destroy');
            Route::get('/profile', [\App\Http\Controllers\Admin\ProfileController::class, 'edit'])->name('admin.profile.edit');
            Route::put('/profile', [\App\Http\Controllers\Admin\ProfileController::class, 'update'])->name('admin.profile.update');
            Route::post('/media/upload', [\App\Http\Controllers\Admin\MediaUploadController::class, 'upload'])->name('admin.media.upload');

            // Categories CRUD
            Route::get('/categories', [\App\Http\Controllers\Admin\CategoriesController::class, 'index'])->name('admin.categories.index');
            Route::get('/categories/create', [\App\Http\Controllers\Admin\CategoriesController::class, 'create'])->name('admin.categories.create');
            Route::post('/categories', [\App\Http\Controllers\Admin\CategoriesController::class, 'store'])->name('admin.categories.store');
            Route::get('/categories/{category}/edit', [\App\Http\Controllers\Admin\CategoriesController::class, 'edit'])->name('admin.categories.edit');
            Route::put('/categories/{category}', [\App\Http\Controllers\Admin\CategoriesController::class, 'update'])->name('admin.categories.update');
            Route::delete('/categories/{category}', [\App\Http\Controllers\Admin\CategoriesController::class, 'destroy'])->name('admin.categories.destroy');

            // Tags CRUD
            Route::get('/tags', [\App\Http\Controllers\Admin\TagsController::class, 'index'])->name('admin.tags.index');
            Route::get('/tags/create', [\App\Http\Controllers\Admin\TagsController::class, 'create'])->name('admin.tags.create');
            Route::post('/tags', [\App\Http\Controllers\Admin\TagsController::class, 'store'])->name('admin.tags.store');
            Route::get('/tags/{tag}/edit', [\App\Http\Controllers\Admin\TagsController::class, 'edit'])->name('admin.tags.edit');
            Route::put('/tags/{tag}', [\App\Http\Controllers\Admin\TagsController::class, 'update'])->name('admin.tags.update');
            Route::delete('/tags/{tag}', [\App\Http\Controllers\Admin\TagsController::class, 'destroy'])->name('admin.tags.destroy');

            // Moderation actions
            Route::post('/moderation/authors/{user}/approve', [\App\Http\Controllers\Admin\AdminModerationController::class, 'approveAuthor'])->name('admin.moderation.authors.approve');
            Route::post('/moderation/authors/{user}/reject', [\App\Http\Controllers\Admin\AdminModerationController::class, 'rejectAuthor'])->name('admin.moderation.authors.reject');

            Route::post('/moderation/profiles/{pendingUserProfile}/approve', [\App\Http\Controllers\Admin\AdminModerationController::class, 'approveProfile'])->name('admin.moderation.profiles.approve');
            Route::post('/moderation/profiles/{pendingUserProfile}/reject', [\App\Http\Controllers\Admin\AdminModerationController::class, 'rejectProfile'])->name('admin.moderation.profiles.reject');

            Route::post('/moderation/articles/{pendingArticleTranslation}/approve', [\App\Http\Controllers\Admin\AdminModerationController::class, 'approveArticle'])->name('admin.moderation.articles.approve');
            Route::post('/moderation/articles/{pendingArticleTranslation}/reject', [\App\Http\Controllers\Admin\AdminModerationController::class, 'rejectArticle'])->name('admin.moderation.articles.reject');

            Route::post('/moderation/reports/{report}/dismiss', [\App\Http\Controllers\Admin\AdminModerationController::class, 'dismissReport'])->name('admin.moderation.reports.dismiss');
            Route::post('/moderation/reports/{report}/action', [\App\Http\Controllers\Admin\AdminModerationController::class, 'actionReport'])->name('admin.moderation.reports.action');
        });

        Route::prefix('php')->group(function () {
            Route::get('/', [HomeController::class, 'index'])->defaults('category_slug', 'php')->name('php.index');
            Route::get('/{version}', [PhpVersionController::class, 'show'])
                ->name('php.show')
                ->middleware('llm.friendly');
        });

        Route::prefix('tools')->group(function () {
            Route::get('/', [HomeController::class, 'index'])->defaults('category_slug', 'tools')->name('tools.index');
            Route::get('/{slug}', [PhpToolsController::class, 'show'])
                ->name('tools.show')
                ->where('slug', 'sail|sail-databases|sail-queues|sail-env-deploy|sail-troubleshooting')
                ->middleware('llm.friendly');
        });

        Route::prefix('microservices')->group(function () {
            Route::get('/', [HomeController::class, 'index'])->defaults('category_slug', 'microservices')->name('microservices.index');
            Route::get('/{slug}', [MicroservicesController::class, 'show'])
                ->name('microservices.show')
                ->where('slug', MicroservicesController::slugRoutePattern())
                ->middleware('llm.friendly');
        });

        Route::prefix('architecture')->group(function () {
            Route::get('/', [HomeController::class, 'index'])->defaults('category_slug', 'architecture')->name('architecture.index');
            Route::get('/{slug}', [ArchitectureController::class, 'show'])
                ->name('architecture.show')
                ->where('slug', ArchitectureController::slugRoutePattern())
                ->middleware('llm.friendly');
        });

        Route::prefix('jobs')->group(function () {
            Route::get('/', [JobsController::class, 'index'])->name('jobs.index');
            Route::get('/{slug}', [JobsController::class, 'show'])
                ->name('jobs.show')
                ->middleware('llm.friendly');
        });

        Route::prefix('authors')->group(function () {
            Route::get('/', [AuthorController::class, 'index'])->name('authors.index');
            Route::get('/{slug}', [AuthorController::class, 'show'])->name('authors.show');
        });

        Route::prefix('tags')->group(function () {
            Route::get('/', [\App\Http\Controllers\TagController::class, 'index'])->name('tags.index');
            Route::get('/{slug}', [\App\Http\Controllers\TagController::class, 'show'])->name('tags.show');
        });

        Route::get('/terms', function () {
            return view('legal.terms');
        })->name('terms');

        Route::get('/privacy', function () {
            return view('legal.privacy');
        })->name('privacy');

        Route::post('/reports', [\App\Http\Controllers\ReportController::class, 'store'])->name('reports.store');
    });
