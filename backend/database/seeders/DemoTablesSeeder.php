<?php

namespace Database\Seeders;

use App\Models\Restaurant;
use Illuminate\Database\Seeder;

/**
 * Ensures every restaurant has a handful of tables, each with a QR code, so
 * the QR ordering flow and the live orders board have something to work with.
 * Idempotent: only creates the tables that are missing.
 */
class DemoTablesSeeder extends Seeder
{
    private const TABLES_PER_RESTAURANT = 8;

    public function run(): void
    {
        Restaurant::query()->orderBy('id')->each(function (Restaurant $restaurant): void {
            for ($number = 1; $number <= self::TABLES_PER_RESTAURANT; $number++) {
                $name = "Table {$number}";

                $table = $restaurant->tables()->firstOrCreate(
                    ['name' => $name],
                    ['capacity' => 4],
                );

                // Generate a QR token for the table if it doesn't have one yet.
                $table->qrCode()->firstOrCreate([]);
            }

            $this->command?->info("Seeded tables for: {$restaurant->slug}");
        });
    }
}
