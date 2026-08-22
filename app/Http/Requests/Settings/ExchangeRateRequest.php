<?php

namespace App\Http\Requests\Settings;

use App\Models\Currency;
use App\Services\CurrencyService;
use App\Support\ExchangeRates;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExchangeRateRequest extends FormRequest
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
            // Only the foreign currencies: the base currency is its own unit and
            // recording a rate for it would be recording that 1 = 1.
            'currency' => ['required', 'string', Rule::in($this->foreignCurrencies())],
            'rate' => ['required', 'numeric', 'gt:0', 'decimal:0,'.ExchangeRates::RATE_PRECISION],
            'effective_on' => ['required', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rate.gt' => 'A rate has to be more than nothing.',
            'rate.decimal' => 'Use at most '.ExchangeRates::RATE_PRECISION.' decimal places.',
        ];
    }

    public function currency(): string
    {
        return strtoupper($this->string('currency')->toString());
    }

    public function rate(): string
    {
        return $this->string('rate')->toString();
    }

    public function effectiveOn(): string
    {
        return $this->date('effective_on')->toDateString();
    }

    /**
     * Everything but the base. The base is its own unit, and recording a rate
     * for it would be recording that 1 = 1.
     *
     * @return list<string>
     */
    private function foreignCurrencies(): array
    {
        $currencies = app(CurrencyService::class);
        $base = $currencies->base();

        return array_values(array_filter(
            array_map(
                static fn (Currency $currency): string => $currency->code,
                $currencies->all(),
            ),
            static fn (string $code): bool => $code !== $base,
        ));
    }
}
