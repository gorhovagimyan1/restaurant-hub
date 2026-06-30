<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('table_qr_codes', function (Blueprint $table) {
            $table->id();
            // Denormalized tenant key for direct ownership checks / queries.
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('restaurant_table_id')->unique()->constrained()->cascadeOnDelete();
            $table->uuid('token')->unique();
            $table->string('qr_image')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_qr_codes');
    }
};
