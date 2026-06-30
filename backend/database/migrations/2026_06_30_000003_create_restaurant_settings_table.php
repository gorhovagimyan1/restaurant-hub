<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('default_language', 5)->default('hy');
            $table->char('currency', 3)->default('AMD');
            $table->decimal('tax_percentage', 5, 2)->default(0);
            $table->decimal('service_charge', 5, 2)->default(0);
            $table->boolean('allow_guest_orders')->default(true);
            $table->boolean('require_table_selection')->default(false);
            $table->boolean('enable_waiter_call')->default(true);
            $table->boolean('enable_bill_request')->default(true);
            $table->boolean('auto_accept_orders')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_settings');
    }
};
