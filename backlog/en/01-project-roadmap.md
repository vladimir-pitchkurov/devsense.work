# Project Roadmap

# Project Roadmap

## Phase 1: MVP (Personal Reference Guide) — [x] Completed
- [x] Basic UI Structure (Tailwind CSS / Blade).
- [x] "PHP Guides" Section:
    - [x] PHP 8.0 (Named Arguments, Match Expression, Nullsafe Operator).
    - [x] PHP 8.1 - 8.5 (Gradual population).
- [x] "Developer Tools" Section:
    - [x] Article: Evolution of the Local Environment (From Valet to Laravel Sail).

## Phase 2: Dynamic Content — [x] Completed
- [x] Extra categories (Microservices, Architecture, Tools, Jobs).
- [x] Migrating articles from hardcoded Blade/resource files to the PostgreSQL database.
- [x] Synchronization mechanism via console command `app:migrate-articles-to-database`.
- [x] Admin panel for authors and admins with a Markdown editor.

## Phase 3: Platform for Authors — [x] Completed
- [x] Authentication (Registration `/register` automatically assigning the `author` role, and Login `/login`).
- [x] Role-Based Access Control (Super Admin, Author, Reader).
- [x] Ownership restrictions in the admin panel (author cabinet): non-admins see, edit, and delete only their own articles.
- [x] Flexible visibility options for the author's public profile page (`is_public` setting).

## Phase 4: UI/UX Improvements & Immediate Plans — [/] In Progress
- [ ] Fix broken pagination styling in the article listings.
- [ ] Responsive Logo: show the large logo for desktop screens and a compact icon for mobile view.
- [ ] Make the entire article card clickable (currently only the "read" arrow initiates the transition).
- [ ] Add links to the guest-facing UI to navigate to the register/login pages for the author cabinet.
- [ ] Integrate author page visibility checks: exclude private author profiles from directories, search, and restrict guest views.

## Phase 5: Community & Interactivity — [ ] Planned
- [ ] Forum for tech discussions.
- [ ] Real-time Chat (Laravel Reverb / WebSockets).

