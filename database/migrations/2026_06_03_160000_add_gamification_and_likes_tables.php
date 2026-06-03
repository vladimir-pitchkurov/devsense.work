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
        // 1. Add articles_required to badges table and make points_required nullable
        Schema::table('badges', function (Blueprint $table) {
            $table->integer('points_required')->nullable()->change();
            $table->integer('articles_required')->nullable()->after('points_required');
        });

        // 2. Add points_awarded to articles table
        Schema::table('articles', function (Blueprint $table) {
            $table->boolean('points_awarded')->default(false)->after('is_approved');
        });

        // 3. Create polymorphic likes table
        Schema::create('likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('likeable_id');
            $table->string('likeable_type');
            $table->boolean('is_dislike')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'likeable_id', 'likeable_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('likes');

        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn('points_awarded');
        });

        Schema::table('badges', function (Blueprint $table) {
            $table->integer('points_required')->nullable(false)->change();
            $table->dropColumn('articles_required');
        });
    }
};
