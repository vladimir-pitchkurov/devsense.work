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

## AI Agents Information

If you are an AI coding assistant (like Claude, Gemini, Antigravity, etc.) working on this repository, please review [docs/agent-context.md](file:///docs/agent-context.md) for full project architecture details, state representation, roadmap, and standard rules for writing or refactoring articles.

