<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Update users table
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_approved')->default(false);
        });
        
        // Auto-approve existing authors/admins
        DB::table('users')->update(['is_approved' => true]);

        // 2. Update articles table
        Schema::table('articles', function (Blueprint $table) {
            $table->boolean('is_approved')->default(false);
        });

        // Auto-approve existing articles
        DB::table('articles')->update(['is_approved' => true]);

        // 3. Create pending_user_profiles table
        Schema::create('pending_user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('job_title')->nullable();
            $table->text('bio')->nullable();
            $table->string('avatar_path')->nullable();
            $table->string('github_url')->nullable();
            $table->string('linkedin_url')->nullable();
            $table->string('twitter_url')->nullable();
            $table->string('website_url')->nullable();
            $table->timestamps();
        });

        // 4. Create pending_article_translations table
        Schema::create('pending_article_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->string('locale');
            $table->string('title');
            $table->text('description')->nullable();
            $table->longText('content');
            $table->json('faq')->nullable();
            $table->timestamps();
        });

        // 5. Create reports table
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reportable_type');
            $table->unsignedBigInteger('reportable_id');
            $table->text('reason');
            $table->string('status')->default('pending'); // pending, dismissed, resolved
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
        Schema::dropIfExists('pending_article_translations');
        Schema::dropIfExists('pending_user_profiles');

        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn('is_approved');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_approved');
        });
    }
};
