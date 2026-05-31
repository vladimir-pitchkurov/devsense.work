<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add author profile fields to users table for E-E-A-T compliance.
     *
     * These fields power public author profile pages (/en/authors/{slug}) and
     * enrich JSON-LD Person schema on article pages.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // URL-friendly unique identifier (e.g. "vladimir-pitchkurov")
            $table->string('slug')->nullable()->unique()->after('name');
            // Short professional title shown on profile ("Senior PHP Engineer")
            $table->string('job_title')->nullable()->after('slug');
            // Markdown-formatted biography (rendered on author page)
            $table->text('bio')->nullable()->after('job_title');
            // Relative path in public/uploads/ or absolute S3 URL
            $table->string('avatar_path', 512)->nullable()->after('bio');
            // Social / professional links
            $table->string('github_url', 512)->nullable()->after('avatar_path');
            $table->string('linkedin_url', 512)->nullable()->after('github_url');
            $table->string('twitter_url', 512)->nullable()->after('linkedin_url');
            $table->string('website_url', 512)->nullable()->after('twitter_url');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'slug', 'job_title', 'bio', 'avatar_path',
                'github_url', 'linkedin_url', 'twitter_url', 'website_url',
            ]);
        });
    }
};
