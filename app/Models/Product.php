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
 * Both prices are required and are held in the base currency. They pre-fill
 * data entry; the price a transaction actually went through at is recorded on
 * that transaction and is what every report reads.
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property Money $cost_price
 * @property Money $selling_price
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name',
    'description',
    'cost_price',
    'selling_price',
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
            'cost_price' => Money::class,
            'selling_price' => Money::class,
        ];
    }
}
