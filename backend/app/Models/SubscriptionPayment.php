<?php

namespace App\Models;

use App\Enums\BillingInterval;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One charge against a subscription — a bank transfer awaiting confirmation
 * today, a gateway checkout later. Doubles as the billing history an owner
 * sees, so rows are never deleted, only re-statused.
 */
class SubscriptionPayment extends Model
{
    /** @use HasFactory<\Database\Factories\SubscriptionPaymentFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'subscription_id',
        'plan_id',
        'interval',
        'amount',
        'currency',
        'status',
        'provider',
        'provider_reference',
        'confirmed_by',
        'paid_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'interval' => BillingInterval::class,
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
