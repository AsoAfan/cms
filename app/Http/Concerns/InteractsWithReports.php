<?php

namespace App\Http\Concerns;

use App\Enums\ReportPreset;
use App\Support\ReportPeriod;

/**
 * Reads the reporting period out of the query string, so every report screen
 * answers for the same window and a URL can be bookmarked or shared.
 *
 * The period lives in the URL rather than in the session on purpose: a report
 * someone sends a colleague has to show them the same figures.
 */
trait InteractsWithReports
{
    protected function reportPeriod(ReportPreset $default = ReportPeriod::DEFAULT_PRESET): ReportPeriod
    {
        return ReportPeriod::fromInput(
            $this->reportParameter('from'),
            $this->reportParameter('to'),
            $this->reportParameter('preset'),
            $default,
        );
    }

    /**
     * The props every report screen shares: the window it covers, and the
     * presets its filter offers.
     *
     * @return array{period: ReportPeriod, presets: list<array{value: string, label: string}>}
     */
    protected function periodProps(ReportPeriod $period): array
    {
        return [
            'period' => $period,
            'presets' => array_map(
                static fn (ReportPreset $preset): array => [
                    'value' => $preset->value,
                    'label' => $preset->label(),
                ],
                ReportPreset::cases(),
            ),
        ];
    }

    private function reportParameter(string $key): ?string
    {
        $value = request()->query($key);

        return is_string($value) ? $value : null;
    }
}
