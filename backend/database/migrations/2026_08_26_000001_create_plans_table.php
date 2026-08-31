<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();

            // Both intervals live on the plan: a restaurant picks how often to
            // pay, not a different product.
            $table->decimal('monthly_price', 10, 2);
            $table->decimal('yearly_price', 10, 2);
            $table->char('currency', 3)->default('AMD');

            // Marketing bullets shown on the checkout screen.
            $table->json('features')->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
