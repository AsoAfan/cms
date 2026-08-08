---
paths:
  - 'app/Http/Requests/**'
---

# Requests

## Currency is converted to the base currency once, here
Form Requests are the ONLY place currency is converted. Use `App\Http\Requests\Concerns\ConvertsToBaseCurrency`: `baseMoney()` for a top-level field, `baseMoneyIn()` for a line/cost row, `documentCurrency()`/`documentRate()` for the header columns.

Every money field may carry a sibling `{field}_currency` key (one field can be in USD while its neighbours stay in IQD); a field without one follows the document's `currency`. Validate every currency field against `$this->enterableCurrencies()` — only currencies with a rate on record — so `MissingExchangeRateException` stays an internal guard.

Override `currencyDate()` to the document's own date column (`invoiced_on`/`sold_on`/`spent_on`) so back-dated paperwork converts at the rate of its own day. Use `dateOrNull()`, because `rules()` runs before validation and the date may still be nonsense.

Everything downstream — Actions, Services, Models, App\Queries, Csv — must only ever see base-currency minor units. An arch test enforces it.
