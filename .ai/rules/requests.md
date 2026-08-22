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

## A bank is required on card and transfer, and forbidden on cash
`PaymentMethod::usesBank()` is the ONE place that decides which methods move through an account. Every request that takes a payment reads it through `App\Http\Requests\Concerns\NamesPayingBank` — `bankRules()` in `rules()`, `...$this->bankMessages()` in `messages()`, `bankId()` in the payload. Never restate the rule per request; a rule enforced on three of four forms leaves untraceable rows on the fourth.

Card/transfer → `required`. Cash → `prohibited`, because a bank left behind by switching the method is a stale value, not a detail somebody meant to record. An empty string passes `prohibited`, which is what lets a form send `bank_id: ''` for cash.

`banks.bank_id` is nullable in the database even so: rows recorded before banks existed carry no bank and no rule can go back and ask them.

Frontend mirror: `<BankField>` renders nothing on cash, and `bankAfterMethodChange()` must be called from every payment-method `onValueChange` to clear the field — otherwise switching card → cash submits a bank and fails a form the user sees nothing wrong with.
