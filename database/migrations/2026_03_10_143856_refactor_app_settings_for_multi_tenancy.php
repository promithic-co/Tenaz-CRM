<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Recreate table with user_id support (SQLite-compatible approach via rename)
        Schema::create('app_settings_new', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->text('value')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['key', 'user_id']);
        });

        // Migrate existing global settings with user_id = null
        DB::statement('INSERT INTO app_settings_new (key, value, created_at, updated_at) SELECT key, value, created_at, updated_at FROM app_settings');

        Schema::dropIfExists('app_settings');
        Schema::rename('app_settings_new', 'app_settings');
    }

    public function down(): void
    {
        Schema::create('app_settings_old', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        DB::statement('INSERT INTO app_settings_old (key, value, created_at, updated_at) SELECT key, value, created_at, updated_at FROM app_settings WHERE user_id IS NULL');

        Schema::dropIfExists('app_settings');
        Schema::rename('app_settings_old', 'app_settings');
    }
};
