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
        Schema::create('article_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('content');
            $table->string('status')->default('pending'); // pending, approved, implemented, rejected
            $table->timestamps();
        });

        Schema::create('article_suggestion_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('suggestion_id')->constrained('article_suggestions')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'suggestion_id']);
        });

        Schema::create('article_suggestion_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('suggestion_id')->constrained('article_suggestions')->cascadeOnDelete();
            $table->text('content');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('article_suggestion_comments');
        Schema::dropIfExists('article_suggestion_votes');
        Schema::dropIfExists('article_suggestions');
    }
};
