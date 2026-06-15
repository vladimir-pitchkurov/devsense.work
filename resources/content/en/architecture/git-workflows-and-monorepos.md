---
title: "Git Hygiene and Monorepos: Atomic Commits, Rebasing, and Microservices CI/CD"
description: "A comprehensive developer's guide to Git hygiene, atomic commits, conventional commits, merging vs. rebasing, interactive rebase, monorepos vs. polyrepos, and optimizing CI/CD workflows."
published: 2026-06-15
faq:
  - question: "What is the primary benefit of atomic commits?"
    answer: "Atomic commits ensure that each commit contains exactly one logical change and its corresponding tests. This makes code reviews easier, keeps the commit history clear, allows clean rollbacks of specific changes, and makes debugging with tools like 'git bisect' highly efficient."
  - question: "When should I use merge and when should I use rebase?"
    answer: "Use rebase on local, private branches to clean up your history, squash WIP commits, and keep a linear history before integrating. Use merge when combining completed feature branches into public shared branches (like main or develop) to preserve the true chronological order and avoid rewriting shared history."
  - question: "How can I avoid running tests for all services in a monorepo for a single-service change?"
    answer: "You can optimize your CI pipeline by using path-based filtering (e.g., the 'paths' filter in GitHub Actions or 'rules:changes' in GitLab CI) so that a workflow only triggers when files within a specific service's subdirectory are modified."
---

# Git Hygiene and Monorepos: Atomic Commits, Rebasing, and Microservices CI/CD

Picture this: it is Friday afternoon, and a critical bug is active in production. You look at the git history to find the regression, but all you see is a wall of commits: "wip", "fix typo", "test", "hope this works", and a massive, tangled web of merge commits. Finding the breaking change becomes a needle-in-a-haystack search. In another repository, a developer changes a single line of text in a microservice readme file, only to trigger a 40-minute CI/CD pipeline that rebuilds and redeploys all twenty microservices in the system. These common developer pains are not failures of the technology stack, but failures of version control strategy and repository architecture.

Clean commit standards and optimized monorepo configurations are essential to build robust, scalable, and developer-friendly development pipelines in microservices.

## Table of Contents
* [Commit Hygiene and the Power of Atomicity](#git-hygiene)
* [Integration Strategies: Merge vs. Rebase](#merge-vs-rebase)
* [Advanced Git Power-Moves: Interactive Rebase and Reflog](#advanced-git)
* [Microservices Layouts: Monorepos vs. Polyrepos](#monorepo-vs-polyrepo)
* [Monorepo Tooling for PHP & Laravel](#php-monorepos)
* [CI/CD Pipeline Optimization in Monorepos](#monorepo-ci)
* [Limitations and Trade-offs](#limitations)
* [Practical Takeaways](#takeaways)

---

<a id="git-hygiene"></a>
## Commit Hygiene and the Power of Atomicity

Version control is more than just a backup mechanism; it is a communication tool and a history ledger. 

### Atomic Commits
* **Point**: A commit should be the smallest possible unit of code that is complete, functional, and contains both the change and its tests.
* **Why it matters**: If a commit is atomic, it can be reverted easily without breaking other features. It also makes `git bisect` (binary searching history for bugs) extremely fast.
* **Example**: Instead of combining refactoring, a bug fix, and a dependency update into one giant commit, split them into three distinct commits.
* **Consequence**: Code reviews become faster, code history remains clean, and rollbacks are painless.

### Conventional Commits
To standardize commit history, teams use the **Conventional Commits** specification. Messages follow this structure: `<type>(<scope>): <description>`.
* `feat`: A new feature.
* `fix`: A bug fix.
* `chore`: Maintenance tasks, dependencies, build configs.
* `docs`: Documentation changes.
* `refactor`: Code changes that neither fix a bug nor add a feature.
* `test`: Adding or correcting tests.

---

<a id="merge-vs-rebase"></a>
## Integration Strategies: Merge vs. Rebase

How you integrate branch history back into the main line determines the shape and readability of your repository.

```
Merge (Non-linear history):
A --- B --- C (main)
 \         /
  D --- E (feature)

Rebase (Linear history):
A --- B --- C --- D' --- E' (main/feature)
```

### Git Merge
* **Point**: Combines branches by creating a new "merge commit" that has multiple parent commits.
* **Why it matters**: It preserves the exact chronological order of events and represents the historical reality of when branches diverged and met.
* **Consequence**: The history becomes non-linear and cluttered with "Merge branch 'main' into feature" commits, making it harder to read.

### Git Rebase
* **Point**: Moves the base of your feature branch to the latest commit of the target branch, rewriting the commits on top of it.
* **Why it matters**: It creates a clean, linear history where every feature appears to have been built sequentially.
* **Consequence**: It rewrites commit hashes. If you rebase public, shared branches, you will cause conflicts for everyone else on the team. **Rule: Never rebase shared branches.**

---

<a id="advanced-git"></a>
## Advanced Git Power-Moves: Interactive Rebase and Reflog

Advanced Git features help clean up local code before sharing it with the team.

### Interactive Rebase (`git rebase -i`)
* **Point**: Allows you to rewrite, combine, reorder, or delete commits in your local branch history before pushing.
* **Why it matters**: It lets you clean up "wip" and "typo" commits, present a polished history during pull requests, and keep commits atomic.
* **Example**: Running `git rebase -i HEAD~4` opens a list of the last 4 commits:
  ```text
  pick a1b2c3d feat(auth): add google oauth provider
  squash d4e5f6g wip oauth login
  squash h7i8j9k fix typo in redirect url
  reword l0m1n2o feat(auth): add docs for oauth integration
  ```
  This squashes the messy intermediate commits into the main feature commit and rewrites the final message.

### Cherry-Picking (`git cherry-pick`)
* **Point**: Appends a specific commit from one branch onto your current branch.
* **Why it matters**: Useful for hotfixing a bug in a production branch without merging the entire feature branch that contains it.

### Git Reflog (`git reflog`)
* **Point**: A local diary that records every single change made to the heads of your branches, even if those commits were deleted or orphaned by a rebase.
* **Why it matters**: If you make a mistake during a rebase and lose commits, you can use `git reflog` to find the original commit hash and restore it with `git reset --hard <hash>`.

---

<a id="monorepo-vs-polyrepo"></a>
## Microservices Layouts: Monorepos vs. Polyrepos

When developing microservices, we must choose how to organize our repositories.

### Polyrepo (Repository-per-Service)
* **Point**: Each microservice has its own isolated repository.
* **Why it matters**: Clear boundaries, small clone sizes, and isolated CI/CD pipelines.
* **Consequence**: Hard to share code (requires publishing packages), difficult to run cross-service changes, and dependency version drift across services.

### Monorepo (One Repository to Rule Them All)
* **Point**: All microservices, libraries, and gateways live in a single repository.
* **Why it matters**: Simplified cross-service changes, unified dependency versions, and instant code sharing without publishing packages.
* **Consequence**: Large repository sizes, complex CI/CD configurations, and the danger of loose boundaries where services tightly couple to shared folders.

---

<a id="php-monorepos"></a>
## Monorepo Tooling for PHP & Laravel

While JavaScript developers use Turborepo or Lerna, PHP developers can build robust monorepos using native Composer features.

### 1. Composer Path Repositories
Instead of publishing shared packages to Packagist, you can reference local directories using `path` repositories in your microservice's `composer.json`:
```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../packages/shared-dto",
            "options": {
                "symlink": true
            }
        }
    ],
    "require": {
        "devsense/shared-dto": "*"
    }
}
```
Composer creates a symlink to the shared package, allowing you to edit the shared code and see changes in the microservice immediately without running `composer update`.

### 2. Monorepo Builder
Tooling like Symplify's **Monorepo Builder** helps merge composer configurations, automate semantic versioning of sub-packages, and keep dependency versions in sync across all services.

---

<a id="monorepo-ci"></a>
## CI/CD Pipeline Optimization in Monorepos

The biggest bottleneck of a monorepo is build times. If every commit triggers tests for all services, CI/CD becomes slow and expensive.

* **Point**: Trigger CI/CD steps selectively using path filtering.
* **Why it matters**: It limits resource usage and keeps deployment pipelines fast.
* **Example**: In GitHub Actions, configure workflows to run only when files in specific directories change:
  ```yaml
  # .github/workflows/user-service.yml
  on:
    push:
      branches: [ main ]
      paths:
        - 'services/user-service/**'
        - 'packages/shared-dto/**' # Trigger if shared dependencies change
  ```
* **Consequence**: Only the modified service and its dependants are tested and built, reducing build times from 30 minutes to 2 minutes.

---

<a id="code-demo"></a>
## Practical Code Demonstration

Here are real-life setup templates for monorepo configurations.

### 1. Git Interactive Rebase Workflow
To clean up your branch history before pushing:
```bash
# 1. Start interactive rebase for the last 3 commits
git rebase -i HEAD~3

# 2. In the editor, change 'pick' to 'squash' (or 's') for intermediate commits:
# pick 82a17f2 feat: add database indexing
# squash d928f01 fix syntax error in migration
# squash a19f291 add missing index fields

# 3. Save and close. Git will prompt you to edit the combined commit message:
# feat: add database indexing and migrations
```

### 2. Monorepo Selective CI Configuration (GitHub Actions)
```yaml
# .github/workflows/checkout-service.yml
name: Checkout Service CI

on:
  push:
    branches: [ main, development ]
    paths:
      - 'services/checkout-service/**'
      - 'packages/shared-kernel/**'
  pull_request:
    branches: [ main, development ]
    paths:
      - 'services/checkout-service/**'
      - 'packages/shared-kernel/**'

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - name: Checkout Code
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.5'
          extensions: mbstring, xml, bcmath, pdo_pgsql

      - name: Install Dependencies
        run: |
          cd services/checkout-service
          composer install --no-interaction --prefer-dist --optimize-autoloader

      - name: Run Tests
        run: |
          cd services/checkout-service
          ./vendor/bin/phpunit
```

---

<a id="limitations"></a>
## Limitations and Trade-offs

* **Rebase Risks**: Rebasing is destructive because it rewrites history. If you force-push (`git push --force`) a rebased shared branch, you can overwrite commits made by other developers. Always use `git push --force-with-lease` for safety.
* **Monorepo Scale**: As a monorepo grows, git commands like `status` and `fetch` can slow down. Large files and media should be managed via Git LFS (Large File Storage) or kept out of the repository entirely.
* **CI/CD Complexity**: Managing path dependencies manually in CI pipelines can lead to bugs where a change in a shared library goes untested in a service that relies on it. Build tools like Turborepo or Nx automatically manage this dependency graph for you.

---

<a id="takeaways"></a>
## Practical Takeaways

1. **Keep Commits Atomic**: One logical change per commit. Write messages in the imperative mood (e.g. `feat(auth): add token verification`, not `added verification`).
2. **Rebase Locally, Merge Publicly**: Keep feature branches linear with `git rebase`, but merge them into main branches with explicit merge commits (`git merge --no-ff`) to preserve integration points.
3. **Use Path Repositories for PHP Monorepos**: Symlink shared packages in composer to allow instant local edits.
4. **Implement Path Filtering in CI**: Prevent resource waste by targeting pipelines to modified directories.
5. **Use `git push --force-with-lease`**: Never push blindly; ensure you do not overwrite remote work.
