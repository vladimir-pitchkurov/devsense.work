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
        Schema::table('article_suggestions', function (Blueprint $table) {
            $table->dropForeign(['article_id']);
        });

        Schema::table('article_suggestions', function (Blueprint $table) {
            $table->unsignedBigInteger('article_id')->nullable()->change();
            $table->foreign('article_id')->references('id')->on('articles')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('article_suggestions', function (Blueprint $table) {
            $table->dropForeign(['article_id']);
        });

        Schema::table('article_suggestions', function (Blueprint $table) {
            $table->unsignedBigInteger('article_id')->nullable(false)->change();
            $table->foreign('article_id')->references('id')->on('articles')->cascadeOnDelete();
        });
    }
};
