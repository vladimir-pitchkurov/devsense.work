<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. courses
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->integer('points')->default(0);
            $table->timestamps();
        });

        // 2. course_translations
        Schema::create('course_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['course_id', 'locale']);
        });

        // 3. course_chapters
        Schema::create('course_chapters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quiz_id')->nullable()->constrained()->nullOnDelete();
            $table->string('slug');
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->unique(['course_id', 'slug']);
        });

        // 4. course_chapter_translations
        Schema::create('course_chapter_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_chapter_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('title');
            $table->text('content_markdown');
            $table->timestamps();

            $table->unique(['course_chapter_id', 'locale']);
        });

        // 5. user_chapter_progress
        Schema::create('user_chapter_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_chapter_id')->constrained()->cascadeOnDelete();
            $table->integer('score')->default(0);
            $table->json('answers');
            $table->timestamp('completed_at')->useCurrent();
            $table->timestamps();

            $table->unique(['user_id', 'course_chapter_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_chapter_progress');
        Schema::dropIfExists('course_chapter_translations');
        Schema::dropIfExists('course_chapters');
        Schema::dropIfExists('course_translations');
        Schema::dropIfExists('courses');
    }
};
