<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dining_sessions', function (Blueprint $table) {
            // Bumped on every guest interaction (scan, order, bill, service
            // call). The idle auto-close job closes sessions whose last activity
            // is older than the configured timeout so abandoned tables don't
            // leave a session (and its QR link) open forever.
            $table->timestamp('last_activity_at')->nullable()->after('opened_at');
            $table->index(['status', 'last_activity_at']);
        });
    }

    public function down(): void
    {
        Schema::table('dining_sessions', function (Blueprint $table) {
            $table->dropIndex(['status', 'last_activity_at']);
            $table->dropColumn('last_activity_at');
        });
    }
};
