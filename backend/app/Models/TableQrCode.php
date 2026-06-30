<?php

namespace App\Models;

use App\Traits\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TableQrCode extends Model
{
    /** @use HasFactory<\Database\Factories\TableQrCodeFactory> */
    use BelongsToRestaurant, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'restaurant_id',
        'restaurant_table_id',
        'token',
        'qr_image',
    ];

    /**
     * Auto-generate a unique token on creation when one isn't provided.
     */
    protected static function booted(): void
    {
        static::creating(function (TableQrCode $qrCode): void {
            if (empty($qrCode->token)) {
                $qrCode->token = (string) Str::uuid();
            }
        });
    }

    /**
     * Use the public token for route model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'token';
    }

    /**
     * The table this QR code belongs to.
     *
     * @return BelongsTo<RestaurantTable, $this>
     */
    public function table(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'restaurant_table_id');
    }

    /**
     * Derive restaurant_id from the parent table.
     *
     * Note: the relation is resolved via the method (not $this->table) because
     * the `table` name collides with Eloquent's internal $table property.
     */
    protected function restaurantParent(): ?Model
    {
        return $this->table()->getResults();
    }
}
