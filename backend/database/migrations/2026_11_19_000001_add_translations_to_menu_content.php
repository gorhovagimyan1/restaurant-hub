<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-language copies of owner-written text. The existing columns stay the
 * original wording (and the fallback); `translations` holds overrides keyed by
 * locale, e.g. {"ru": {"name": "…", "description": "…"}, "hy": {…}}.
 */
return new class extends Migration
{
    private const TABLES = [
        'restaurants' => 'description',
        'categories' => 'description',
        'products' => 'ingredients',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table => $after) {
            Schema::table($table, function (Blueprint $blueprint) use ($after) {
                $blueprint->json('translations')->nullable()->after($after);
            });
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::TABLES) as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('translations');
            });
        }
    }
};
