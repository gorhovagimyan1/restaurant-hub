<?php

namespace App\Models;

use App\Enums\DiningSessionStatus;
use App\Traits\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A single dining visit at a table. Opened when a guest scans the table's QR
 * code and stays open — everyone at the table joins the same session — until
 * staff settle the bill. Ordering, the running bill and service calls are all
 * authorized against the open session, so once it closes any old links (holding
 * a stale session_token) stop working and the guest must scan again.
 */
class DiningSession extends Model
{
    /** @use HasFactory<\Database\Factories\DiningSessionFactory> */
    use BelongsToRestaurant, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'restaurant_id',
        'restaurant_table_id',
        'session_token',
        'status',
        'open_table_lock',
        'opened_at',
        'last_activity_at',
        'closed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DiningSessionStatus::class,
            'opened_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * Auto-generate the visit token, stamp the open time, and set the
     * one-open-session-per-table lock on creation.
     */
    protected static function booted(): void
    {
        static::creating(function (DiningSession $session): void {
            if (empty($session->session_token)) {
                $session->session_token = (string) Str::uuid();
            }

            if ($session->opened_at === null) {
                $session->opened_at = now();
            }

            if ($session->last_activity_at === null) {
                $session->last_activity_at = $session->opened_at;
            }

            // While open, the lock mirrors the table id so the unique index
            // rejects a second open session for the same table.
            if ($session->status === DiningSessionStatus::Open || $session->status === null) {
                $session->status ??= DiningSessionStatus::Open;
                $session->open_table_lock = $session->restaurant_table_id;
            }
        });
    }

    /**
     * Use the visit token for route model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'session_token';
    }

    /**
     * Close the session: mark it settled and release the one-open-per-table
     * lock so the table's next scan can open a fresh session.
     */
    public function close(): void
    {
        $this->forceFill([
            'status' => DiningSessionStatus::Closed,
            'open_table_lock' => null,
            'closed_at' => $this->closed_at ?? now(),
        ])->save();
    }

    public function isOpen(): bool
    {
        return $this->status === DiningSessionStatus::Open;
    }

    /**
     * Record guest activity so the idle auto-close job leaves this session
     * alone while the table is in use.
     */
    public function touchActivity(): void
    {
        $this->forceFill(['last_activity_at' => now()])->save();
    }

    /**
     * The table this session was opened at.
     *
     * @return BelongsTo<RestaurantTable, $this>
     */
    public function table(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'restaurant_table_id');
    }

    /**
     * Orders placed during this visit.
     *
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Derive restaurant_id from the parent table.
     */
    protected function restaurantParent(): ?Model
    {
        return $this->table()->getResults();
    }
}
