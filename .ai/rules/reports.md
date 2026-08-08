---
paths:
  - 'resources/js/components/reports/**'
---

# Reports

## The trend chart is inline SVG, and identity comes from form not colour
`TrendChart` is hand-drawn inline SVG. No charting library was added and none is needed: two series over a date axis is a few dozen lines of geometry, and any dependency would outweigh the file.

**This application's palette is achromatic** — every `--chart-*` token is `oklch(… 0 0)`, and those tokens are identical in light and dark, so `--chart-5` is nearly invisible on the dark background. Do not reach for them.

So the two series are told apart by **form**: a soft `fill-muted-foreground/20` area for the context series against a 2px `stroke-foreground` line for the one the chart is about. That separation survives any colour vision, a monochrome print and forced-colours mode. Validated: the pair separates at ΔE 31.2 (light) / 27.6 (dark) against a target of 8, and clears 3:1 contrast in both.

Rules the chart holds to, and any new one should too:
- **One y-axis, always.** Both series here are money on one scale. A second scale lets any two lines be made to tell any story.
- A legend is present for two or more series; a single series gets none (the card title names it).
- Gridlines are 1px solid `stroke-border`, recessive. Axis text wears text tokens, never a series colour.
- Every tone is a theme token, so light and dark each get their own step rather than one being flipped from the other.
- Empty buckets are plotted as zero, never skipped — a gap reads as missing data, a zero reads as a quiet week. `ReportPeriod::buckets()` supplies them.
