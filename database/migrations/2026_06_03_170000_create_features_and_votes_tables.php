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
        Schema::create('features', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->string('slug')->unique();
            $blueprint->string('title_en');
            $blueprint->string('title_ru');
            $blueprint->text('description_en');
            $blueprint->text('description_ru');
            $blueprint->timestamps();
        });

        Schema::create('feature_votes', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $blueprint->foreignId('feature_id')->constrained('features')->cascadeOnDelete();
            $blueprint->timestamps();

            $blueprint->unique(['user_id', 'feature_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feature_votes');
        Schema::dropIfExists('features');
    }
};
