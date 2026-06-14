<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add notification and locale columns to users table
        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 10)->default('en')->after('email');
            $table->boolean('notify_articles_quizzes')->default(true)->after('points');
            $table->boolean('notify_comments')->default(true)->after('notify_articles_quizzes');
            $table->timestamp('last_notified_at')->nullable()->after('notify_comments');
        });

        // 2. Add category_id and notified columns to quizzes table
        Schema::table('quizzes', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('points')->constrained('categories')->nullOnDelete();
            $table->boolean('notified')->default(false)->after('category_id');
        });

        // 3. Add notified column to articles table
        Schema::table('articles', function (Blueprint $table) {
            $table->boolean('notified')->default(false)->after('points_awarded');
        });

        // 4. Create category_user pivot table for user interests
        Schema::create('category_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->unique(['user_id', 'category_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_user');

        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn('notified');
        });

        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn(['category_id', 'notified']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['locale', 'notify_articles_quizzes', 'notify_comments', 'last_notified_at']);
        });
    }
};
