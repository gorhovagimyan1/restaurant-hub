<?php

namespace Database\Factories;

use App\Enums\RestaurantStatus;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Restaurant>
 */
class RestaurantFactory extends Factory
{
    protected $model = Restaurant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1000, 9999),
            'description' => fake()->catchPhrase(),
            'phone' => fake()->e164PhoneNumber(),
            'email' => fake()->companyEmail(),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'country' => fake()->country(),
            'currency' => 'AMD',
            'timezone' => 'Asia/Yerevan',
            'status' => RestaurantStatus::Active->value,
            'is_active' => true,
        ];
    }
}
