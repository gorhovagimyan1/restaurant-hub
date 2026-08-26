<?php

use App\Enums\BillingInterval;
use App\Enums\SubscriptionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();

            // One subscription per restaurant — it is the billable tenant.
            $table->foreignId('restaurant_id')->unique()->constrained()->cascadeOnDelete();

            // Null while trialing: no plan has been chosen or paid for yet.
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('interval', BillingInterval::values())->nullable();

            $table->enum('status', SubscriptionStatus::values())
                ->default(SubscriptionStatus::Trialing->value);

            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            // Which gateway owns this subscription, and its id over there.
            // Null until something has actually been paid.
            $table->string('provider')->nullable();
            $table->string('provider_reference')->nullable();

            $table->timestamps();

            // The gating middleware reads status + period on every request.
            $table->index(['status', 'current_period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
