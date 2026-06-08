# DevSense

DevSense is a **Laravel** web project that publishes knowledge in the form of **Markdown-based guides**.

- Live site: `https://devsense.work/en`

The project aims to **share practical engineering knowledge** and **grow a professional network** around PHP and web development. In the future, DevSense will evolve into a **multi-author platform** with many topics and categories.

## Content (Markdown templates)

Guides are stored as Markdown files with YAML front matter:

- Path: `resources/content/{locale}/{category}/{slug}.md`
- Front matter: `title`, `description` (and sometimes `published`)

Supported locales in this repo: `en`, `ru`, `ua`, `bg`.

## Contributing

You can join right now:

- Add new guides or improve existing ones in `resources/content/`.
- Open Pull Requests with new articles, fixes, and translations.

## Local development (Laravel Sail)

This project is designed to run via **Laravel Sail**:

```bash
sail up -d
sail composer install
sail npm install
sail npm run build
```

Then open the app in your browser (the URL depends on your Sail/compose setup).

## Database Seeding & Content Synchronization

This project uses a hybrid content system. Original Markdown articles and quizzes are checked into the repository, but the database acts as the single source of truth for the application.

### Articles and Translations Seeding
To import or update Markdown articles from files to the PostgreSQL database, use the following Artisan commands:

- **Import/Sync Primary Articles (locales `en`, `ru`, `ua`, `bg`)**:
  ```bash
  sail php artisan app:migrate-articles-to-database
  ```
- **Import/Sync Translated Articles (locales `de`, `fr`, `es`, `it` under `content-translations`)**:
  ```bash
  sail php artisan app:seed-translations
  ```

> [!TIP]
> **Optimized seeding (`--update` flag)**:
> By default, these commands will only seed *new* articles (skipping existing ones to avoid modifying `updated_at` timestamps in the database, which triggers unnecessary search engine indexing via IndexNow).
> If you have modified existing files and want to sync those modifications, run the commands with the `--update` flag:
> ```bash
> sail php artisan app:migrate-articles-to-database --update
> sail php artisan app:seed-translations --update
> ```
> This will perform a smart diff comparison and only update database entries if there are actual content or metadata changes.

### Quizzes Seeding
The application features interactive learning quizzes and badges/achievements (Bronze, Silver, Gold, Expert). The 100-question PHP Basics interview quiz is defined in JSON files (`resources/quizzes/php-basics-interview/{locale}.json`) and seeded using:
```bash
sail php artisan db:seed --class=QuizSeeder
```
This command cleans up outdated quiz data/badges and imports the quiz, all translations (across 8 locales), and achievements into the database.

---

## Production Deployment & Site Maintenance

To deploy the application to production or run maintenance tasks, use the `deploy.sh` script or run the required maintenance commands manually.

### Automated Production Deployment
Deployments are automated via:
```bash
./deploy.sh
```
This script:
1. Places the application in maintenance mode (`php artisan down`).
2. Resets the branch to `origin/prod`.
3. Installs Composer dependencies (`composer install --no-dev ...`).
4. Clears and rebuilds caches (`php artisan optimize:clear`, `php artisan optimize`).
5. Applies database migrations (`php artisan migrate --force`).
6. Regenerates the XML sitemap (`php artisan sitemap:write`).
7. Pings search engines via IndexNow (`php artisan seo:ping-indexnow`).
8. Reloads PHP-FPM and restarts queue workers.
9. Brings the application back online (`php artisan up`).

### Manual Maintenance Commands

If you need to perform tasks manually on the server:

- **Regenerate XML Sitemap**:
  ```bash
  php artisan sitemap:write
  ```
  This generates a static `public/sitemap.xml` file containing all localized paths for the home page, category indexes, public articles, public author profiles, and quizzes. Alternate `hreflang` links are automatically generated for all locales.

- **Ping Search Engines (IndexNow)**:
  ```bash
  php artisan seo:ping-indexnow
  ```
  This command pings Bing, Yandex, etc., notifying them of new or modified pages. To prevent redundant pings (especially since `optimize:clear` is run during deploy), the command uses a persistent timestamp file at `storage/app/.indexnow_last_run` rather than database cache storage. Only pages modified *after* this timestamp are submitted.

- **Force IndexNow Ping**:
  If you need to submit all URLs (e.g., during the initial setup or after major routing changes), run:
  ```bash
  php artisan seo:ping-indexnow --force
  ```

---

## AI Agents Information

If you are an AI coding assistant (like Claude, Gemini, Antigravity, etc.) working on this repository, please review [docs/agent-context.md](file:///docs/agent-context.md) for full project architecture details, state representation, roadmap, and standard rules for writing or refactoring articles.

### Rules for Future Modifications

For developers and AI agents:
1. **Always Update Documentation**: If you add new commands, environment variables, features, or modify the structure of content (articles, quizzes), you **must** document it in this `README.md` and update the agent context in `docs/agent-context.md`.
2. **Translate Immediately**: All articles and quizzes must be written with translations to all supported languages immediately.
3. **Commit Messages**: At the end of every completed task or block of work, always propose a recommended Git commit message.
4. **Summary Language**: Communicate progress and summaries to the user in Russian.
5. **Code Comments**: Keep code comments to a minimum, and write them *exclusively in English*.

