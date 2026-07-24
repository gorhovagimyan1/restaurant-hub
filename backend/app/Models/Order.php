<?php

namespace App\Models;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Order extends Model
{
    /** @use HasFactory<\Database\Factories\OrderFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'restaurant_id',
        'restaurant_table_id',
        'dining_session_id',
        'order_number',
        'customer_name',
        'customer_phone',
        'notes',
        'subtotal',
        'tax',
        'service_charge',
        'total',
        'status',
        'ordered_at',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'tax' => 'decimal:2',
            'service_charge' => 'decimal:2',
            'total' => 'decimal:2',
            'status' => OrderStatus::class,
            'ordered_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Auto-generate a unique order number on creation when one isn't provided.
     */
    protected static function booted(): void
    {
        static::creating(function (Order $order): void {
            if (empty($order->order_number)) {
                $order->order_number = 'ORD-'.strtoupper(Str::random(10));
            }
        });
    }

    /**
     * @return BelongsTo<Restaurant, $this>
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * @return BelongsTo<RestaurantTable, $this>
     */
    public function table(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'restaurant_table_id');
    }

    /**
     * The dining session (visit) this order was placed during.
     *
     * @return BelongsTo<DiningSession, $this>
     */
    public function diningSession(): BelongsTo
    {
        return $this->belongsTo(DiningSession::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Recompute the overall order status from its items' statuses.
     *
     * Kitchen/waiter staff drive individual items; this keeps the order-level
     * status (used by the board grouping and open-bill logic) in sync. A paid
     * or cancelled order is left untouched.
     */
    public function syncStatusFromItems(): void
    {
        if (in_array($this->status, [OrderStatus::Completed, OrderStatus::Cancelled], true)) {
            return;
        }

        $items = $this->items()->get()
            ->reject(fn (OrderItem $item) => $item->status === OrderItemStatus::Cancelled);

        if ($items->isEmpty()) {
            return;
        }

        $new = match (true) {
            $items->every(fn (OrderItem $i) => $i->status === OrderItemStatus::Served) => OrderStatus::Served,
            $items->every(fn (OrderItem $i) => in_array($i->status, [OrderItemStatus::Ready, OrderItemStatus::Served], true)) => OrderStatus::Ready,
            $items->contains(fn (OrderItem $i) => in_array($i->status, [OrderItemStatus::Preparing, OrderItemStatus::Ready, OrderItemStatus::Served], true)) => OrderStatus::Preparing,
            default => OrderStatus::Pending,
        };

        // Only ever forwards — the same rule staff-driven changes follow. Without
        // this, cancelling the one item that was underway on an accepted order
        // would drop it back to "pending" and it would resurface as new work.
        if ($this->status !== $new && $this->status->canTransitionTo($new)) {
            $this->status = $new;
            $this->save();
        }
    }
}
