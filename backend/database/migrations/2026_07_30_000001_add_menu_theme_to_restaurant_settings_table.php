<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_settings', function (Blueprint $table) {
            // The restaurant's own design for its public menu (palette, fonts,
            // radius, layout). Null means "never customised" — the app falls
            // back to the Classic preset. See App\Support\MenuTheme.
            $table->json('menu_theme')->nullable()->after('auto_accept_orders');
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_settings', function (Blueprint $table) {
            $table->dropColumn('menu_theme');
        });
    }
};
