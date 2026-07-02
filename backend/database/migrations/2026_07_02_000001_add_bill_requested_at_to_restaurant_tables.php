<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a customer signal they're ready to pay/leave. Set when the guest taps
 * "Request bill" from their table, cleared when staff settle the table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_tables', function (Blueprint $table) {
            $table->timestamp('bill_requested_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_tables', function (Blueprint $table) {
            $table->dropColumn('bill_requested_at');
        });
    }
};
