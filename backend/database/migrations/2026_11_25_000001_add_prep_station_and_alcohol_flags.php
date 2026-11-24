<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drinks: where each item is prepared (kitchen / bar / ready to serve) and
 * whether it contains alcohol (18+ badge on the menu).
 *
 * Categories carry the default; a product may override it (null = inherit).
 * Order items snapshot the station when ordered, so moving a category later
 * never shifts tickets already on a board.
 */
return new class extends Migration
{
    /** Catalog defaults, by English preset name: [station, alcoholic]. */
    private const PRESET_DEFAULTS = [
        'soft drinks' => ['ready', false],
        'fresh juices & smoothies' => ['bar', false],
        'hot drinks' => ['bar', false],
        'coffee' => ['bar', false],
        'tea' => ['bar', false],
        'alcohol' => ['bar', true],
        'wine' => ['ready', true],
        'beer' => ['ready', true],
        'cocktails' => ['bar', true],
        'spirits' => ['ready', true],
    ];

    public function up(): void
    {
        Schema::table('category_presets', function (Blueprint $table) {
            $table->string('prep_station', 20)->default('kitchen')->after('translations');
            $table->boolean('is_alcoholic')->default(false)->after('prep_station');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->string('prep_station', 20)->default('kitchen')->after('description');
            $table->boolean('is_alcoholic')->default(false)->after('prep_station');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('prep_station', 20)->nullable()->after('preparation_time');
            $table->boolean('is_alcoholic')->nullable()->after('prep_station');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('prep_station', 20)->default('kitchen')->after('status');
        });

        // Give the catalog's drink categories sensible defaults, and carry
        // them over to the restaurant categories already picked from it.
        DB::table('category_presets')->get(['id', 'translations'])->each(function ($preset) {
            $name = mb_strtolower(trim(json_decode($preset->translations, true)['en']['name'] ?? ''));
            [$station, $alcoholic] = self::PRESET_DEFAULTS[$name] ?? ['kitchen', false];

            $values = ['prep_station' => $station, 'is_alcoholic' => $alcoholic];
            DB::table('category_presets')->where('id', $preset->id)->update($values);
            DB::table('categories')->where('category_preset_id', $preset->id)->update($values);
        });
    }

    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn('prep_station'));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['prep_station', 'is_alcoholic']));
        Schema::table('categories', fn (Blueprint $table) => $table->dropColumn(['prep_station', 'is_alcoholic']));
        Schema::table('category_presets', fn (Blueprint $table) => $table->dropColumn(['prep_station', 'is_alcoholic']));
    }
};
