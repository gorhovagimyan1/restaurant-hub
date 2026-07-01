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
 * Seeds a fully populated demo restaurant so the customer portal has real
 * content to render (landing page categories + full menu). Idempotent: it
 * skips seeding if the demo restaurant already exists.
 */
class DemoRestaurantSeeder extends Seeder
{
    private const SLUG = 'the-golden-fork';

    public function run(): void
    {
        if (Restaurant::where('slug', self::SLUG)->exists()) {
            $this->command?->info('Demo restaurant already exists, skipping.');

            return;
        }

        $restaurant = Restaurant::create([
            'name' => 'The Golden Fork',
            'slug' => self::SLUG,
            'description' => 'Modern comfort food, fresh ingredients, and a great selection of drinks — served all day in the heart of Yerevan.',
            'logo' => $this->img('1552566626-52f8b828add9', 200, 200),
            'cover_image' => $this->img('1517248135467-4c7edcad34c4', 1600, 900),
            'phone' => '+37410000000',
            'email' => 'hello@thegoldenfork.test',
            'website' => 'https://thegoldenfork.test',
            'address' => '12 Northern Avenue',
            'city' => 'Yerevan',
            'country' => 'Armenia',
            'currency' => 'AMD',
            'timezone' => 'Asia/Yerevan',
            'status' => RestaurantStatus::Active->value,
            'is_active' => true,
        ]);

        $restaurant->settings()->create([
            'default_language' => 'en',
            'currency' => 'AMD',
            'tax_percentage' => 0,
            'service_charge' => 10,
            'allow_guest_orders' => true,
            'require_table_selection' => true,
            'enable_waiter_call' => true,
            'enable_bill_request' => true,
            'auto_accept_orders' => false,
        ]);

        // Attach a demo owner so the tenant has a staff user too.
        $owner = User::firstOrCreate(
            ['email' => 'owner@thegoldenfork.test'],
            [
                'first_name' => 'Ani',
                'last_name' => 'Grigoryan',
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
            'description' => 'Our full à la carte menu.',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        foreach ($this->menuData() as $index => $categoryData) {
            $category = Category::create([
                'menu_id' => $menu->id,
                'name' => $categoryData['name'],
                'description' => $categoryData['description'],
                'image' => $this->img($categoryData['image'], 800, 600),
                'is_active' => true,
                'sort_order' => $index,
            ]);

            foreach ($categoryData['products'] as $productIndex => $productData) {
                $product = Product::create([
                    'category_id' => $category->id,
                    'name' => $productData['name'],
                    'description' => $productData['description'],
                    'ingredients' => $productData['ingredients'] ?? [],
                    'price' => $productData['price'],
                    'preparation_time' => $productData['prep'] ?? null,
                    'is_available' => $productData['available'] ?? true,
                    'is_featured' => $productData['featured'] ?? false,
                    'sort_order' => $productIndex,
                ]);

                ProductImage::create([
                    'product_id' => $product->id,
                    'image' => $this->img($productData['image'], 800, 600),
                    'is_primary' => true,
                ]);
            }
        }

        $this->command?->info('Seeded demo restaurant: '.self::SLUG);
    }

    /**
     * Build a stable Unsplash image URL from a photo id.
     */
    private function img(string $id, int $w, int $h): string
    {
        return "https://images.unsplash.com/photo-{$id}?auto=format&fit=crop&w={$w}&h={$h}&q=70";
    }

    /**
     * The curated demo menu. Prices are in AMD.
     *
     * @return array<int, array<string, mixed>>
     */
    private function menuData(): array
    {
        return [
            [
                'name' => 'Starters',
                'description' => 'Small plates to begin your meal.',
                'image' => '1541592106381-b31e9677c0e5',
                'products' => [
                    ['name' => 'Truffle Fries', 'description' => 'Crispy fries tossed in truffle oil, parmesan and fresh herbs.', 'price' => 2200, 'prep' => 10, 'featured' => true, 'ingredients' => ['Potato', 'Truffle oil', 'Parmesan', 'Parsley', 'Sea salt'], 'image' => '1541592106381-b31e9677c0e5'],
                    ['name' => 'Crispy Calamari', 'description' => 'Golden fried calamari served with lemon aioli.', 'price' => 3800, 'prep' => 15, 'ingredients' => ['Squid', 'Flour', 'Lemon', 'Garlic aioli', 'Paprika'], 'image' => '1559742811-822873691df8'],
                    ['name' => 'Bruschetta Trio', 'description' => 'Toasted sourdough with tomato, mushroom and ricotta toppings.', 'price' => 2600, 'prep' => 10, 'ingredients' => ['Sourdough', 'Tomato', 'Mushroom', 'Ricotta', 'Basil', 'Olive oil'], 'image' => '1509440159596-0249088772ff'],
                    ['name' => 'Cheese Platter', 'description' => 'Selection of local and imported cheeses with honey and nuts.', 'price' => 4900, 'prep' => 8, 'ingredients' => ['Brie', 'Cheddar', 'Blue cheese', 'Honey', 'Walnuts', 'Crackers'], 'image' => '1452195100486-9cc805987862'],
                ],
            ],
            [
                'name' => 'Soups',
                'description' => 'Warm and hearty.',
                'image' => '1547592180-85f173990554',
                'products' => [
                    ['name' => 'Roasted Tomato Soup', 'description' => 'Slow-roasted tomatoes, basil and a touch of cream.', 'price' => 1900, 'prep' => 10, 'ingredients' => ['Tomato', 'Basil', 'Cream', 'Garlic', 'Onion'], 'image' => '1547592180-85f173990554'],
                    ['name' => 'Mushroom Cream Soup', 'description' => 'Forest mushrooms blended smooth with thyme.', 'price' => 2100, 'prep' => 12, 'ingredients' => ['Mushrooms', 'Cream', 'Thyme', 'Onion', 'Garlic'], 'image' => '1476718406336-bb5a9690ee2a'],
                ],
            ],
            [
                'name' => 'Salads',
                'description' => 'Fresh, crisp and colorful.',
                'image' => '1512621776951-a57141f2eefd',
                'products' => [
                    ['name' => 'Caesar Salad', 'description' => 'Romaine, croutons, parmesan and classic Caesar dressing.', 'price' => 3200, 'prep' => 10, 'featured' => true, 'ingredients' => ['Romaine', 'Croutons', 'Parmesan', 'Caesar dressing', 'Anchovy'], 'image' => '1512621776951-a57141f2eefd'],
                    ['name' => 'Greek Salad', 'description' => 'Tomato, cucumber, olives, red onion and feta.', 'price' => 2900, 'prep' => 8, 'ingredients' => ['Tomato', 'Cucumber', 'Olives', 'Red onion', 'Feta', 'Oregano'], 'image' => '1540420773420-3366772f4999'],
                    ['name' => 'Quinoa Bowl', 'description' => 'Quinoa, avocado, chickpeas and lemon-tahini dressing.', 'price' => 3400, 'prep' => 10, 'ingredients' => ['Quinoa', 'Avocado', 'Chickpeas', 'Cherry tomato', 'Tahini', 'Lemon'], 'image' => '1512852939750-1305098529bf'],
                ],
            ],
            [
                'name' => 'Main Courses',
                'description' => 'Chef signatures and hearty plates.',
                'image' => '1546964124-0cce460f38ef',
                'products' => [
                    ['name' => 'Grilled Ribeye Steak', 'description' => '300g prime ribeye with roasted vegetables and pepper sauce.', 'price' => 8900, 'prep' => 25, 'featured' => true, 'ingredients' => ['Ribeye beef', 'Roasted vegetables', 'Pepper sauce', 'Butter', 'Thyme'], 'image' => '1546964124-0cce460f38ef'],
                    ['name' => 'Truffle Pasta', 'description' => 'Tagliatelle in a creamy black truffle sauce.', 'price' => 5200, 'prep' => 18, 'ingredients' => ['Tagliatelle', 'Black truffle', 'Cream', 'Parmesan', 'Garlic'], 'image' => '1621996346565-e3dbc646d9a9'],
                    ['name' => 'Grilled Salmon', 'description' => 'Fillet of salmon with asparagus and lemon butter.', 'price' => 6800, 'prep' => 20, 'ingredients' => ['Salmon', 'Asparagus', 'Lemon', 'Butter', 'Dill'], 'image' => '1467003909585-2f8a72700288'],
                    ['name' => 'Roast Chicken', 'description' => 'Half chicken marinated in herbs with garlic potatoes.', 'price' => 4600, 'prep' => 22, 'ingredients' => ['Chicken', 'Garlic', 'Potatoes', 'Rosemary', 'Olive oil'], 'image' => '1626645738196-c2a7c87a8f58'],
                ],
            ],
            [
                'name' => 'Pizza',
                'description' => 'Wood-fired, thin crust.',
                'image' => '1513104890138-7c749659a591',
                'products' => [
                    ['name' => 'Margherita', 'description' => 'San Marzano tomato, mozzarella and fresh basil.', 'price' => 3600, 'prep' => 15, 'ingredients' => ['Tomato sauce', 'Mozzarella', 'Basil', 'Olive oil'], 'image' => '1513104890138-7c749659a591'],
                    ['name' => 'Pepperoni', 'description' => 'Loaded with pepperoni and melted mozzarella.', 'price' => 4200, 'prep' => 15, 'featured' => true, 'ingredients' => ['Tomato sauce', 'Mozzarella', 'Pepperoni', 'Oregano'], 'image' => '1628840042765-356cda07504e'],
                    ['name' => 'Quattro Formaggi', 'description' => 'Four-cheese blend with a honey drizzle.', 'price' => 4400, 'prep' => 15, 'ingredients' => ['Mozzarella', 'Gorgonzola', 'Parmesan', 'Fontina', 'Honey'], 'image' => '1571407970349-bc81e7e96d47'],
                ],
            ],
            [
                'name' => 'Burgers',
                'description' => 'Served with fries.',
                'image' => '1568901346375-23c9450c58cd',
                'products' => [
                    ['name' => 'Classic Cheeseburger', 'description' => 'Beef patty, cheddar, lettuce, tomato and house sauce.', 'price' => 3900, 'prep' => 15, 'featured' => true, 'ingredients' => ['Beef patty', 'Cheddar', 'Lettuce', 'Tomato', 'House sauce', 'Brioche bun'], 'image' => '1568901346375-23c9450c58cd'],
                    ['name' => 'Bacon BBQ Burger', 'description' => 'Double beef, bacon, onion rings and BBQ sauce.', 'price' => 4700, 'prep' => 18, 'ingredients' => ['Double beef', 'Bacon', 'Onion rings', 'BBQ sauce', 'Cheddar'], 'image' => '1550547660-d9450f859349'],
                    ['name' => 'Crispy Chicken Burger', 'description' => 'Buttermilk fried chicken with slaw and pickles.', 'price' => 4100, 'prep' => 16, 'ingredients' => ['Fried chicken', 'Slaw', 'Pickles', 'Mayo', 'Brioche bun'], 'image' => '1606755962773-d324e0a13086'],
                ],
            ],
            [
                'name' => 'Desserts',
                'description' => 'Something sweet to finish.',
                'image' => '1578985545062-69928b1d9587',
                'products' => [
                    ['name' => 'Chocolate Lava Cake', 'description' => 'Warm molten chocolate cake with vanilla ice cream.', 'price' => 2800, 'prep' => 12, 'featured' => true, 'ingredients' => ['Dark chocolate', 'Butter', 'Egg', 'Flour', 'Vanilla ice cream'], 'image' => '1606313564200-e75d5e30476c'],
                    ['name' => 'New York Cheesecake', 'description' => 'Classic baked cheesecake with berry compote.', 'price' => 2600, 'prep' => 5, 'ingredients' => ['Cream cheese', 'Biscuit base', 'Berry compote', 'Vanilla'], 'image' => '1578985545062-69928b1d9587'],
                    ['name' => 'Tiramisu', 'description' => 'Espresso-soaked ladyfingers with mascarpone.', 'price' => 2700, 'prep' => 5, 'ingredients' => ['Ladyfingers', 'Espresso', 'Mascarpone', 'Cocoa'], 'image' => '1571877227200-a0d98ea607e9'],
                    ['name' => 'Ice Cream Selection', 'description' => 'Three scoops of house-made gelato.', 'price' => 1800, 'prep' => 3, 'ingredients' => ['Vanilla gelato', 'Chocolate gelato', 'Strawberry gelato'], 'image' => '1497034825429-c343d7c6a68f'],
                ],
            ],
            [
                'name' => 'Soft Drinks',
                'description' => 'Chilled and refreshing.',
                'image' => '1553530666-ba11a7da3888',
                'products' => [
                    ['name' => 'Fresh Orange Juice', 'description' => 'Freshly squeezed oranges.', 'price' => 1400, 'ingredients' => ['Orange'], 'image' => '1621506289937-a8e4df240d0b'],
                    ['name' => 'Berry Smoothie', 'description' => 'Mixed berries, banana and yogurt.', 'price' => 1800, 'featured' => true, 'ingredients' => ['Strawberry', 'Blueberry', 'Banana', 'Yogurt'], 'image' => '1553530666-ba11a7da3888'],
                    ['name' => 'Lemonade', 'description' => 'Homemade lemonade with mint.', 'price' => 1200, 'ingredients' => ['Lemon', 'Mint', 'Sugar', 'Water'], 'image' => '1621263764928-df1444c5e859'],
                    ['name' => 'Sparkling Water', 'description' => 'Chilled sparkling mineral water.', 'price' => 800, 'ingredients' => ['Sparkling mineral water'], 'image' => '1523362628745-0c100150b504'],
                ],
            ],
            [
                'name' => 'Hot Drinks',
                'description' => 'Coffee & tea.',
                'image' => '1509042239860-f550ce710b93',
                'products' => [
                    ['name' => 'Espresso', 'description' => 'Rich single shot.', 'price' => 900, 'ingredients' => ['Espresso'], 'image' => '1510591509098-f4fdc6d0ff04'],
                    ['name' => 'Cappuccino', 'description' => 'Espresso with steamed milk foam.', 'price' => 1300, 'featured' => true, 'ingredients' => ['Espresso', 'Steamed milk', 'Milk foam'], 'image' => '1509042239860-f550ce710b93'],
                    ['name' => 'Armenian Coffee', 'description' => 'Traditional coffee brewed in a jazzve.', 'price' => 1100, 'ingredients' => ['Ground coffee', 'Water'], 'image' => '1447933601403-0c6688de566e'],
                    ['name' => 'Herbal Tea', 'description' => 'Selection of mountain herbal teas.', 'price' => 1000, 'ingredients' => ['Mountain herbs'], 'image' => '1544787219-7f47ccb76574'],
                ],
            ],
            [
                'name' => 'Alcohol',
                'description' => 'Wine, beer and cocktails. Must be 18+.',
                'image' => '1514362545857-3bc16c4c7d1b',
                'products' => [
                    ['name' => 'House Red Wine', 'description' => 'Glass of Armenian dry red wine.', 'price' => 2400, 'ingredients' => ['Dry red wine'], 'image' => '1510812431401-41d2bd2722f3'],
                    ['name' => 'House White Wine', 'description' => 'Glass of crisp dry white wine.', 'price' => 2400, 'ingredients' => ['Dry white wine'], 'image' => '1470158499416-75be9aa0c4db'],
                    ['name' => 'Draft Beer', 'description' => 'Local lager on tap, 0.5L.', 'price' => 1600, 'featured' => true, 'ingredients' => ['Barley', 'Hops', 'Water', 'Yeast'], 'image' => '1608270586620-248524c67de9'],
                    ['name' => 'Classic Mojito', 'description' => 'White rum, lime, mint and soda.', 'price' => 3200, 'ingredients' => ['White rum', 'Lime', 'Mint', 'Sugar', 'Soda'], 'image' => '1514362545857-3bc16c4c7d1b'],
                    ['name' => 'Old Fashioned', 'description' => 'Bourbon, bitters, sugar and orange.', 'price' => 3600, 'ingredients' => ['Bourbon', 'Bitters', 'Sugar', 'Orange'], 'image' => '1470337458703-46ad1756a187'],
                ],
            ],
        ];
    }
}
