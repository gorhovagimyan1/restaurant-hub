<?php

use App\Enums\BillingInterval;
use App\Enums\PaymentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained();

            $table->enum('interval', BillingInterval::values());

            // Captured at the time of purchase: plan prices change, an issued
            // charge does not.
            $table->decimal('amount', 10, 2);
            $table->char('currency', 3);

            $table->enum('status', PaymentStatus::values())
                ->default(PaymentStatus::Pending->value);

            $table->string('provider');
            $table->string('provider_reference')->nullable();

            // Who marked it paid, when the gateway is manual.
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();

            $table->index(['subscription_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
    }
};
