<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create the article_category pivot table
        Schema::create('article_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->unique(['article_id', 'category_id']);
            $table->timestamps();
        });

        // 2. Migrate existing category_id associations to the pivot table
        $articles = DB::table('articles')->whereNotNull('category_id')->get();
        foreach ($articles as $article) {
            DB::table('article_category')->insert([
                'article_id'  => $article->id,
                'category_id' => $article->category_id,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }

        // 3. Drop foreign key and column category_id from articles table
        Schema::table('articles', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn('category_id');
        });

        // 4. Add custom_url unique column to articles
        Schema::table('articles', function (Blueprint $table) {
            $table->string('custom_url')->nullable()->unique()->after('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Remove custom_url column
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn('custom_url');
        });

        // 2. Re-add category_id column
        Schema::table('articles', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
        });

        // 3. Restore the first category from pivot back to articles table
        $pivotRecords = DB::table('article_category')->orderBy('id')->get();
        $processed = [];
        foreach ($pivotRecords as $record) {
            if (!in_array($record->article_id, $processed, true)) {
                DB::table('articles')
                    ->where('id', $record->article_id)
                    ->update(['category_id' => $record->category_id]);
                $processed[] = $record->article_id;
            }
        }

        // 4. Drop pivot table
        Schema::dropIfExists('article_category');
    }
};
