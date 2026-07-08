<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // The dining session (visit) this order belongs to. Nullable so
            // pre-existing orders and any staff-created orders are unaffected;
            // the session is freed (not deleted) if it is ever removed.
            $table->foreignId('dining_session_id')
                ->nullable()
                ->after('restaurant_table_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['dining_session_id']);
            $table->dropColumn('dining_session_id');
        });
    }
};
