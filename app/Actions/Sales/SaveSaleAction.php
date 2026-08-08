<?php

namespace App\Actions\Sales;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Exceptions\SaleNotPostableException;
use App\Models\Sale;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates a draft sale with its lines.
 *
 * Lines are replaced wholesale rather than diffed: a draft has taken nothing
 * off the shelf yet, so rewriting it is simpler and cannot leave half a sale
 * behind.
 */
final class SaveSaleAction
{
    /**
     * @param  array{sold_on: string, payment_method: string, notes: string|null}  $header
     * @param  list<array{product_id: int, quantity: int, unit_price: string, discount: string}>  $lines
     *
     * @throws SaleNotPostableException
     */
    public function handle(array $header, array $lines, ?Sale $sale = null): Sale
    {
        if ($sale?->isPosted()) {
            throw SaleNotPostableException::isPosted($sale);
        }

        return DB::transaction(function () use ($header, $lines, $sale): Sale {
            $sale ??= new Sale([
                'number' => Sale::nextNumber(),
                'status' => SaleStatus::Draft,
            ]);

            $sale->fill([
                'sold_on' => $header['sold_on'],
                'payment_method' => PaymentMethod::from($header['payment_method']),
                'notes' => $header['notes'],
            ])->save();

            $sale->lines()->delete();

            foreach ($lines as $line) {
                $sale->lines()->create([
                    'product_id' => $line['product_id'],
                    'quantity' => $line['quantity'],
                    'unit_price' => Money::fromDecimal($line['unit_price']),
                    'discount' => Money::fromDecimal($line['discount']),
                ]);
            }

            return $sale->refresh();
        });
    }
}
