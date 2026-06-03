<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. quizzes
        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->integer('points')->default(50);
            $table->timestamps();
        });

        // 2. quiz_translations
        Schema::create('quiz_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['quiz_id', 'locale']);
        });

        // 3. quiz_questions
        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('multiple_choice'); // multiple_choice, true_false, code_output
            $table->integer('points')->default(10);
            $table->integer('correct_answer_index');
            $table->text('explanation')->nullable();
            $table->timestamps();
        });

        // 4. quiz_question_translations
        Schema::create('quiz_question_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_question_id')->constrained('quiz_questions')->cascadeOnDelete();
            $table->string('locale', 5);
            $table->text('question_text');
            $table->json('options'); // JSON array of options
            $table->timestamps();

            $table->unique(['quiz_question_id', 'locale']);
        });

        // 5. user_quizzes
        Schema::create('user_quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->integer('score')->default(0);
            $table->timestamp('completed_at')->useCurrent();
            $table->timestamps();

            $table->unique(['user_id', 'quiz_id']);
        });

        // 6. badges
        Schema::create('badges', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->integer('points_required')->default(100);
            $table->string('image_path')->nullable();
            $table->timestamps();
        });

        // 7. badge_translations
        Schema::create('badge_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('badge_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['badge_id', 'locale']);
        });

        // 8. user_badges
        Schema::create('user_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('badge_id')->constrained()->cascadeOnDelete();
            $table->timestamp('unlocked_at')->useCurrent();
            $table->timestamps();

            $table->unique(['user_id', 'badge_id']);
        });

        // 9. Add points to users table
        Schema::table('users', function (Blueprint $table) {
            $table->integer('points')->default(0)->after('is_blocked');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('points');
        });

        Schema::dropIfExists('user_badges');
        Schema::dropIfExists('badge_translations');
        Schema::dropIfExists('badges');
        Schema::dropIfExists('user_quizzes');
        Schema::dropIfExists('quiz_question_translations');
        Schema::dropIfExists('quiz_questions');
        Schema::dropIfExists('quiz_translations');
        Schema::dropIfExists('quizzes');
    }
};
