<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * The subscription products on sale.
 *
 * One plan today — restaurants choose monthly or yearly, not a tier. Adding a
 * row here is all it takes to offer more: the checkout screen renders whatever
 * is active.
 */
class PlansSeeder extends Seeder
{
    public function run(): void
    {
        Plan::updateOrCreate(
            ['slug' => 'standard'],
            [
                'name' => 'Restaurant Hub',
                'description' => 'Everything you need to take orders from your tables.',
                // Yearly is priced at ten months, so a year saves about 17%.
                'monthly_price' => 14900,
                'yearly_price' => 149000,
                'currency' => 'AMD',
                'features' => [
                    'Unlimited menu items & categories',
                    'Unlimited tables with printable QR codes',
                    'Live orders board & kitchen display',
                    'Your own menu design & branding',
                    'Staff accounts with roles & permissions',
                    'Opening hours, holidays & service settings',
                ],
                'is_active' => true,
                'sort_order' => 1,
            ],
        );
    }
}
