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
        Schema::table('users', function (Blueprint $table) {
            $table->text('intro')->nullable();
            $table->text('experience')->nullable();
            $table->string('job_status')->nullable(); // e.g. seeking, passively_seeking, not_looking
            $table->boolean('is_anonymous')->default(false);
            $table->json('portfolio')->nullable();
        });

        Schema::table('pending_user_profiles', function (Blueprint $table) {
            $table->text('intro')->nullable();
            $table->text('experience')->nullable();
            $table->string('job_status')->nullable();
            $table->boolean('is_anonymous')->default(false);
            $table->json('portfolio')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pending_user_profiles', function (Blueprint $table) {
            $table->dropColumn(['intro', 'experience', 'job_status', 'is_anonymous', 'portfolio']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['intro', 'experience', 'job_status', 'is_anonymous', 'portfolio']);
        });
    }
};
