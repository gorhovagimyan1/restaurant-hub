<?php

namespace Database\Factories;

use App\Models\Menu;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Menu>
 */
class MenuFactory extends Factory
{
    protected $model = Menu::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'name' => 'Main Menu',
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
