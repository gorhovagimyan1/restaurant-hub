<?php

namespace Database\Seeders;

use App\Enums\RestaurantStatus;
use App\Enums\Role as RoleEnum;
use App\Models\Category;
use App\Models\Menu;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * A second tenant, used to demonstrate and test multi-tenant isolation:
 * its owner must only ever see/manage this restaurant's data.
 */
class SecondRestaurantSeeder extends Seeder
{
    private const SLUG = 'ocean-breeze';

    public function run(): void
    {
        if (Restaurant::where('slug', self::SLUG)->exists()) {
            $this->command?->info('Second restaurant already exists, skipping.');

            return;
        }

        $restaurant = Restaurant::create([
            'name' => 'Ocean Breeze',
            'slug' => self::SLUG,
            'description' => 'Fresh seafood and coastal favourites.',
            'phone' => '+37411000000',
            'email' => 'hello@oceanbreeze.test',
            'address' => '5 Seaside Road',
            'city' => 'Yerevan',
            'country' => 'Armenia',
            'currency' => 'AMD',
            'timezone' => 'Asia/Yerevan',
            'status' => RestaurantStatus::Active->value,
            'is_active' => true,
        ]);

        $owner = User::firstOrCreate(
            ['email' => 'owner@oceanbreeze.test'],
            [
                'first_name' => 'Davit',
                'last_name' => 'Sargsyan',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );
        $owner->assignRole(RoleEnum::RestaurantOwner->value);
        $restaurant->users()->syncWithoutDetaching([
            $owner->id => ['is_active' => true, 'joined_at' => now()],
        ]);

        $menu = Menu::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Main Menu',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $data = [
            ['name' => 'Seafood', 'products' => [
                ['name' => 'Grilled Octopus', 'price' => 7200],
                ['name' => 'Shrimp Skewers', 'price' => 5400],
            ]],
            ['name' => 'Sushi', 'products' => [
                ['name' => 'Salmon Nigiri', 'price' => 2800],
                ['name' => 'California Roll', 'price' => 3600],
            ]],
        ];

        foreach ($data as $i => $categoryData) {
            $category = Category::create([
                'menu_id' => $menu->id,
                'name' => $categoryData['name'],
                'is_active' => true,
                'sort_order' => $i,
            ]);

            foreach ($categoryData['products'] as $j => $productData) {
                $product = Product::create([
                    'category_id' => $category->id,
                    'name' => $productData['name'],
                    'price' => $productData['price'],
                    'is_available' => true,
                    'sort_order' => $j,
                ]);

                ProductImage::create([
                    'product_id' => $product->id,
                    'image' => 'https://picsum.photos/seed/ocean'.$product->id.'/800/600',
                    'is_primary' => true,
                ]);
            }
        }

        $this->command?->info('Seeded second restaurant: '.self::SLUG);
    }
}
