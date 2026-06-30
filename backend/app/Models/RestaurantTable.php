<?php

namespace App\Models;

use App\Enums\TableStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'status' => TableStatus::class,
        ];
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
     * The QR code generated for this table.
     *
     * @return HasOne<TableQrCode, $this>
     */
    public function qrCode(): HasOne
    {
        return $this->hasOne(TableQrCode::class);
    }
}
