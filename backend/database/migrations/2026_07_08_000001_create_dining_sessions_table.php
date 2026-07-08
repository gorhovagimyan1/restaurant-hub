<?php

use App\Enums\DiningSessionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dining_sessions', function (Blueprint $table) {
            $table->id();
            // Denormalized tenant key for direct ownership checks / queries.
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('restaurant_table_id')->constrained()->cascadeOnDelete();
            // Short-lived token the customer's browser holds for this visit. It
            // is what actually authorizes ordering — the printed QR token stays
            // permanent, this rotates every visit.
            $table->uuid('session_token')->unique();
            $table->string('status')->default(DiningSessionStatus::Open->value);
            // Enforces "at most one OPEN session per table" on MySQL, which has
            // no partial unique indexes: this mirrors restaurant_table_id while
            // the session is open and is NULLed on close. Since MySQL treats
            // NULLs as distinct in a unique index, any number of closed sessions
            // for a table coexist, but a second open one collides.
            $table->unsignedBigInteger('open_table_lock')->nullable()->unique();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['restaurant_table_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dining_sessions');
    }
};
