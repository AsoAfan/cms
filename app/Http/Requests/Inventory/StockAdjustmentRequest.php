<?php

namespace App\Http\Requests\Inventory;

use App\Models\Product;
use App\Queries\StockOnHandQuery;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'counted_quantity' => ['required', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'max:255'],
            'unit_cost' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $product = $this->product();
                $delta = $this->integer('counted_quantity') - $this->currentQuantity();

                if ($delta === 0) {
                    $validator->errors()->add(
                        'counted_quantity',
                        'That is already the counted quantity — nothing to adjust.'
                    );

                    return;
                }

                // Stock appearing from nowhere still has to be worth
                // something, or the valuation quietly understates.
                if ($delta > 0 && ! $this->filled('unit_cost')) {
                    $validator->errors()->add(
                        'unit_cost',
                        'Adding '.$delta.' needs a unit cost so the stock can be valued.'
                    );
                }

                unset($product);
            },
        ];
    }

    public function product(): Product
    {
        return Product::query()->findOrFail($this->integer('product_id'));
    }

    /**
     * What the ledger currently says is on hand.
     */
    public function currentQuantity(): int
    {
        return app(StockOnHandQuery::class)->forProduct($this->product());
    }

    public function delta(): int
    {
        return $this->integer('counted_quantity') - $this->currentQuantity();
    }
}
