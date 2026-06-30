<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestaurantSettings extends Model
{
    /** @use HasFactory<\Database\Factories\RestaurantSettingsFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'restaurant_id',
        'default_language',
        'currency',
        'tax_percentage',
        'service_charge',
        'allow_guest_orders',
        'require_table_selection',
        'enable_waiter_call',
        'enable_bill_request',
        'auto_accept_orders',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tax_percentage' => 'decimal:2',
            'service_charge' => 'decimal:2',
            'allow_guest_orders' => 'boolean',
            'require_table_selection' => 'boolean',
            'enable_waiter_call' => 'boolean',
            'enable_bill_request' => 'boolean',
            'auto_accept_orders' => 'boolean',
        ];
    }

    /**
     * The restaurant these settings belong to.
     *
     * @return BelongsTo<Restaurant, $this>
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
}
