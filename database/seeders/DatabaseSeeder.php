<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            // Rates come before anything priced: an amount in a foreign currency
            // cannot be recorded without a rate in force on its own date.
            ExchangeRateSeeder::class,
            CatalogSeeder::class,
            SupplierSeeder::class,
            ExpenseCategorySeeder::class,
        ]);
    }
}
