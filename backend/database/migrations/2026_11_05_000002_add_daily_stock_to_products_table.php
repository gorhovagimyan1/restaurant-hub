<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Portions made fresh each day. When set, stock_quantity is reset to
            // this at the start of every business day. null = no daily reset.
            $table->unsignedInteger('daily_stock')->nullable()->after('low_stock_threshold');
            // The business day (restaurant-local) the last reset applied to, so
            // the hourly job resets each product exactly once per day.
            $table->date('stock_reset_on')->nullable()->after('daily_stock');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['daily_stock', 'stock_reset_on']);
        });
    }
};
