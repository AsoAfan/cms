<?php

namespace App\Models;

use App\Support\Money;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The stock-keeping entity. Purchases, sales and the stock ledger all
 * reference this directly.
 *
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string|null $description
 * @property Money|null $default_cost_price
 * @property Money|null $default_selling_price
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name',
    'code',
    'description',
    'default_cost_price',
    'default_selling_price',
    'is_active',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_cost_price' => Money::class,
            'default_selling_price' => Money::class,
            'is_active' => 'boolean',
        ];
    }
}
