<?php

namespace App\Models;

use App\Enums\DiningSessionStatus;
use App\Enums\OrderStatus;
use App\Enums\TableStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RestaurantTable extends Model
{
    /** @use HasFactory<\Database\Factories\RestaurantTableFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'restaurant_id',
        'name',
        'capacity',
        'status',
        'bill_requested_at',
        'waiter_called_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'status' => TableStatus::class,
            'bill_requested_at' => 'datetime',
            'waiter_called_at' => 'datetime',
        ];
    }

    /**
     * All orders ever placed at this table.
     *
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Orders that make up this table's current (unpaid) bill.
     *
     * @return HasMany<Order, $this>
     */
    public function openOrders(): HasMany
    {
        return $this->hasMany(Order::class)->whereNotIn('status', [
            OrderStatus::Completed->value,
            OrderStatus::Cancelled->value,
        ]);
    }

    /**
     * The restaurant this table belongs to.
     *
     * @return BelongsTo<Restaurant, $this>
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * Every dining session (visit) this table has hosted.
     *
     * @return HasMany<DiningSession, $this>
     */
    public function diningSessions(): HasMany
    {
        return $this->hasMany(DiningSession::class);
    }

    /**
     * The table's currently open dining session, if any. There is at most one
     * (enforced by a unique lock column on dining_sessions).
     *
     * @return HasOne<DiningSession, $this>
     */
    public function openSession(): HasOne
    {
        return $this->hasOne(DiningSession::class)
            ->where('status', DiningSessionStatus::Open->value);
    }

    /**
     * The QR code generated for this table.
     *
     * @return HasOne<TableQrCode, $this>
     */
    public function qrCode(): HasOne
    {
        return $this->hasOne(TableQrCode::class);
    }
}
