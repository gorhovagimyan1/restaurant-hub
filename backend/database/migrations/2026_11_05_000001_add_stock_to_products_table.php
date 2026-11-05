<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // null = stock is not tracked (always orderable while is_available).
            // A number = portions left; orders deduct from it. Never exposed
            // to guests — they only ever see "sold out".
            $table->unsignedInteger('stock_quantity')->nullable()->after('is_available');
            // Alert staff when stock falls to this level. null = config default.
            $table->unsignedInteger('low_stock_threshold')->nullable()->after('stock_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['stock_quantity', 'low_stock_threshold']);
        });
    }
};
