<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Worked examples so the catalogue has something real on first run.
     *
     * Each size is its own product, counted in whole pieces — which is what
     * keeps stock and costing on whole numbers all the way through.
     */
    public function run(): void
    {
        $products = [
            ['Blackout Eyelet Curtain 117x137', 'BEC-117-137', '18.00', '44.00'],
            ['Blackout Eyelet Curtain 117x183', 'BEC-117-183', '21.00', '52.00'],
            ['Blackout Eyelet Curtain 168x183', 'BEC-168-183', '26.00', '64.00'],
            ['Blackout Eyelet Curtain 229x229', 'BEC-229-229', '34.00', '82.00'],
            ['Voile Panel 117 Ivory', 'VOI-117-IVO', '6.50', '16.00'],
            ['Voile Panel 168 Charcoal', 'VOI-168-CHA', '8.00', '19.50'],
            ['Curtain Hook Pack (50)', 'HOOK-50', '1.20', '3.95'],
            ['Metal Curtain Pole 180cm', 'POLE-180', '9.40', '24.00'],
        ];

        foreach ($products as [$name, $code, $cost, $price]) {
            Product::query()->firstOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'default_cost_price' => $cost,
                    'default_selling_price' => $price,
                    'is_active' => true,
                ]
            );
        }
    }
}
