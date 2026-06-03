<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Sets up (or updates) the primary super-admin author profile.
 *
 * Run once on first deploy and whenever you need to update the profile:
 *   php artisan app:setup-author
 *   php artisan app:setup-author --password=secret123
 */
class SetupAuthorProfile extends Command
{
    protected $signature = 'app:setup-author
                            {--password= : Override the admin password (optional)}
                            {--force     : Update fields even if user already exists}';

    protected $description = 'Create or update the primary super-admin author profile (Vladimir Pichkurov)';

    public function handle(): int
    {
        $email    = 'vladimir@devsense.work';
        $password = $this->option('password') ?: 'change-me-on-first-login';

        // ── Profile data ────────────────────────────────────────────────────
        $profile = [
            'name'        => 'Vladimir Pichkurov',
            'role'        => User::ROLE_SUPER_ADMIN,
            'slug'        => 'vladimir-pichkurov',
            'job_title'   => 'Founder & CEO, DevSense | Senior Systems Architect',
            'bio'         => 'Founder & CEO of DevSense. Senior Systems Architect and Backend Engineer with 9+ years of commercial experience designing and scaling complex web systems. Expert in PHP (Laravel, Symfony), MySQL, PostgreSQL, Redis, and microservices architecture. Direct email: vladimir@devsense.work.',
            'github_url'  => 'https://github.com/vladimir-pitchkurov',
            'linkedin_url' => 'https://www.linkedin.com/in/volodimir-pichkurov-626a46150',
            'twitter_url' => null,
            'website_url' => 'https://devsense.work',
            'avatar_path' => 'images/avatar-vladimir-pichkurov.jpg',
        ];

        // ── Find or new ──────────────────────────────────────────────────────
        $user = User::where('email', $email)->first();
        $isNew = !$user;

        if ($isNew) {
            $user = new User();
            $user->email = $email;
            $user->password = Hash::make($password);
        }

        // ── Fill profile data ────────────────────────────────────────────────
        $user->fill($profile);
        $user->save();

        // Only change password when explicitly requested
        if ($this->option('password')) {
            $user->password = Hash::make($password);
            $user->save();
        } elseif ($isNew) {
            $this->line("   Password: <comment>{$password}</comment> — change immediately after first login!");
        }

        $verb = $isNew ? 'created' : 'updated';
        $this->info("✅ Author profile {$verb}: {$user->name} <{$user->email}>");
        $this->table(
            ['Field', 'Value'],
            collect($profile)->map(fn ($v, $k) => [$k, $v ?? '(null)'])->values()->toArray()
        );

        return self::SUCCESS;
    }
}
