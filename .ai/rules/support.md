---
paths:
  - app/Support/ReportPeriod.php
---

# Support

## ReportPeriod owns the reporting window, and it never throws
`ReportPeriod` is the one place a reporting window is defined. `from` is the start of its day, `to` the end of its, so it can be handed to a timestamp comparison without a sale made at five o'clock falling out of its own day.

`fromInput()` reads a query string, which anyone can edit, so it **never throws** — garbage, a reversed range, a date like `2026-02-31`, or an unknown preset all fall back sensibly. A report answering for the default period beats a 500. Controllers reach it through `InteractsWithReports::reportPeriod()`; the window lives in the URL and nowhere else so a report can be bookmarked or sent to a colleague.

`previous()` is the same **number of days** immediately before, not the previous calendar month — February against January is three days short and would read as a slump.

Averages: a month is the mean 365.25/12 = **30.4375** days, held as an exact fraction over 10000 through `Money::multipliedByFraction()`. Never `/30`, and never a float — a rate measured over a week and one measured over a year must be directly comparable.

Presets never run past today: "this month" on the 8th is eight days, not a month with three weeks of zeroes dragging every average down. `App\Enums\ReportPreset` mirrors the frontend `DateRangePicker` presets exactly, so a preset means the same thing at both ends of the wire.

The class is facade-free on purpose (uses `CarbonImmutable` directly), so all of it is unit-testable without a database or a request.
