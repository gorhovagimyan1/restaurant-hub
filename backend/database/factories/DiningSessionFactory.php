<?php

namespace Database\Factories;

use App\Enums\DiningSessionStatus;
use App\Models\DiningSession;
use App\Models\RestaurantTable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiningSession>
 */
class DiningSessionFactory extends Factory
{
    protected $model = DiningSession::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // restaurant_id is derived from the parent table by BelongsToRestaurant;
            // session_token / opened_at / open_table_lock are set on creation.
            'restaurant_table_id' => RestaurantTable::factory(),
            'status' => DiningSessionStatus::Open,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DiningSessionStatus::Closed,
            'open_table_lock' => null,
            'closed_at' => now(),
        ]);
    }
}
