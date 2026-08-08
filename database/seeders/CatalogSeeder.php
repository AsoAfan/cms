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
     *
     * Priced in dinars, the currency everything is stored in. The old figures
     * here were dollars at roughly a 1,320 rate; these are the same goods at
     * prices a shop would actually write on them.
     */
    public function run(): void
    {
        $products = [
            ['Blackout Eyelet Curtain 117x137', '24000', '58000'],
            ['Blackout Eyelet Curtain 117x183', '28000', '68500'],
            ['Blackout Eyelet Curtain 168x183', '34500', '84500'],
            ['Blackout Eyelet Curtain 229x229', '45000', '108000'],
            ['Voile Panel 117 Ivory', '8500', '21000'],
            ['Voile Panel 168 Charcoal', '10500', '25500'],
            ['Curtain Hook Pack (50)', '1500', '5250'],
            ['Metal Curtain Pole 180cm', '12500', '31500'],
        ];

        foreach ($products as [$name, $cost, $price]) {
            Product::query()->firstOrCreate(
                ['name' => $name],
                [
                    'cost_price' => $cost,
                    'selling_price' => $price,
                ]
            );
        }
    }
}
