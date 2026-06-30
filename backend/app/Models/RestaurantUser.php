<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Pivot model linking users to restaurants.
 *
 * Roles/permissions are intentionally NOT stored here — they are managed
 * by Spatie Permission. This table only records the membership itself.
 */
class RestaurantUser extends Pivot
{
    protected $table = 'restaurant_user';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'restaurant_id',
        'user_id',
        'is_active',
        'joined_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'joined_at' => 'datetime',
        ];
    }
}
