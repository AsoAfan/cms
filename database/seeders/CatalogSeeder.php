<?php

namespace Database\Seeders;

use App\Actions\Catalog\SaveProductAction;
use App\Models\Attribute;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Worked examples so the catalogue screens have something real on first run.
     *
     * Curtains are sized goods: an item is a specific width and drop, counted
     * in whole pieces. Size lives in the options rather than in a quantity,
     * which is what keeps stock and costing on whole numbers.
     */
    public function run(): void
    {
        $width = Attribute::query()->firstOrCreate(['name' => 'Width']);
        $drop = Attribute::query()->firstOrCreate(['name' => 'Drop']);
        $colour = Attribute::query()->firstOrCreate(['name' => 'Colour']);

        $widths = collect(['117cm', '168cm', '229cm'])
            ->map(fn (string $value) => $width->values()->firstOrCreate(['value' => $value]));
        $drops = collect(['137cm', '183cm', '229cm'])
            ->map(fn (string $value) => $drop->values()->firstOrCreate(['value' => $value]));
        $colours = collect(['Ivory', 'Charcoal'])
            ->map(fn (string $value) => $colour->values()->firstOrCreate(['value' => $value]));

        $save = new SaveProductAction;

        // Sized in both directions: one item per width x drop.
        $save->handle([
            'name' => 'Blackout Eyelet Curtain',
            'description' => 'Thermal blackout lining, eyelet header.',
            'is_active' => true,
            'variants' => $widths->crossJoin($drops)
                ->map(fn (array $pair): array => [
                    'code' => sprintf(
                        'BEC-%s-%s',
                        str_replace('cm', '', $pair[0]->value),
                        str_replace('cm', '', $pair[1]->value),
                    ),
                    'default_cost_price' => '18.00',
                    'default_selling_price' => '44.00',
                    'attribute_value_ids' => [$pair[0]->id, $pair[1]->id],
                ])
                ->all(),
        ]);

        // Sized and coloured: width x colour.
        $save->handle([
            'name' => 'Voile Panel',
            'description' => 'Sheer voile, slot top.',
            'is_active' => true,
            'variants' => $widths->crossJoin($colours)
                ->map(fn (array $pair): array => [
                    'code' => sprintf(
                        'VOI-%s-%s',
                        str_replace('cm', '', $pair[0]->value),
                        mb_strtoupper(mb_substr($pair[1]->value, 0, 3)),
                    ),
                    'default_cost_price' => '6.50',
                    'default_selling_price' => '16.00',
                    'attribute_value_ids' => [$pair[0]->id, $pair[1]->id],
                ])
                ->all(),
        ]);

        // A plain product with no options at all — one product, one item.
        $save->handle([
            'name' => 'Curtain Hook Pack (50)',
            'description' => 'Standard nylon hooks, pack of fifty.',
            'is_active' => true,
            'variants' => [
                [
                    'code' => 'HOOK-50',
                    'default_cost_price' => '1.20',
                    'default_selling_price' => '3.95',
                    'attribute_value_ids' => [],
                ],
            ],
        ]);
    }
}
