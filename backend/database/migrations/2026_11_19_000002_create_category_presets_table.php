<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A platform-wide catalog of menu sections (Starters, Soups, …) with their
 * names and descriptions in every language. Restaurants pick from it, so
 * categories are translated without owners writing anything.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_presets', function (Blueprint $table) {
            $table->id();
            // {"en": {"name": "…", "description": "…"}, "ru": {…}, "hy": {…}}
            $table->json('translations');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('categories', function (Blueprint $table) {
            // Null for a restaurant's own custom category.
            $table->foreignId('category_preset_id')->nullable()->after('menu_id')
                ->constrained('category_presets')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_preset_id');
        });

        Schema::dropIfExists('category_presets');
    }
};
