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
            // Currencies, then rates, before anything priced: an amount in a
            // foreign currency cannot be recorded without a currency to name and
            // a rate in force on its own date.
            CurrencySeeder::class,
            ExchangeRateSeeder::class,
            CatalogSeeder::class,
            SupplierSeeder::class,
            // Before anything sold: every sale names a customer, and the walk-in
            // row this creates is the one the sale screen opens on.
            CustomerSeeder::class,
            ExpenseCategorySeeder::class,
        ]);
    }
}
