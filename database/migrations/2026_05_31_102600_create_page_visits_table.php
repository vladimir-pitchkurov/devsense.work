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
        Schema::create('page_visits', function (Blueprint $table) {
            $table->id();
            $table->string('ip_hash', 64);
            $table->string('url', 500);
            $table->string('path', 255);
            $table->string('user_agent', 500);
            $table->string('crawler_name', 100)->nullable();
            $table->boolean('is_bot')->default(false);
            $table->boolean('is_ai')->default(false);
            $table->string('locale', 10)->nullable();
            $table->string('referer', 500)->nullable();
            $table->timestamps();
            
            // Add indexes for fast aggregations on dashboard
            $table->index('is_bot');
            $table->index('is_ai');
            $table->index('crawler_name');
            $table->index('path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('page_visits');
    }
};
