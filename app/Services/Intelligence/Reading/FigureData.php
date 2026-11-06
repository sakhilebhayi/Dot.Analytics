<?php

namespace App\Services\Intelligence\Reading;

use App\Models\Intelligence\Geography;
use App\Models\Intelligence\Indicator;
use App\Models\Intelligence\Source;
use Illuminate\Support\Carbon;

/** One indicator for one place, ready to print: its newest value, what changed, how fresh it is and where it came from. */
final class FigureData
{
    /**
     * @param  list<array{period: Carbon, value: float}>  $series  oldest first
     * @param  list<array{period: string, previous: float, now: float, revised_at: string}>  $revisions
     */
    public function __construct(
        public readonly Indicator $indicator,
        public readonly Geography $geography,
        public readonly Source $source,
        public readonly array $series,
        public readonly array $revisions = [],
        public readonly ?Carbon $collectedAt = null,
    ) {}

    public function latest(): ?array
    {
        return $this->series === [] ? null : $this->series[array_key_last($this->series)];
    }

    public function previous(): ?array
    {
        return count($this->series) < 2 ? null : $this->series[count($this->series) - 2];
    }

    public function freshness(): Freshness
    {
        $latest = $this->latest();

        if ($latest === null) {
            return Freshness::Missing;
        }

        return $latest['period']->diffInDays(now()) > $this->indicator->stale_after_days ? Freshness::Stale : Freshness::Current;
    }

    public function value(): ?string
    {
        $latest = $this->latest();

        return $latest === null ? null : Format::figure($latest['value'], $this->indicator->unit, $this->indicator->decimals);
    }

    public function note(): string
    {
        return Format::note($this->indicator->unit);
    }

    public function period(): ?string
    {
        $latest = $this->latest();

        return $latest === null ? null : Format::period($latest['period'], $this->indicator->frequency);
    }

    /** The change since the previous reading, in words, with the direction and whether it is the kind of move that helps. */
    public function change(): ?array
    {
        $latest = $this->latest();
        $previous = $this->previous();

        if ($latest === null || $previous === null) {
            return null;
        }

        $delta = $latest['value'] - $previous['value'];

        if (abs($delta) < 10 ** (-$this->indicator->decimals) / 2) {
            return ['direction' => 'flat', 'tone' => 'neutral', 'words' => 'Unchanged since '.Format::period($previous['period'], $this->indicator->frequency)];
        }

        $isPercentUnit = str_starts_with($this->indicator->unit, '%');
        $size = $isPercentUnit || abs($previous['value']) < 1e-9
            ? Format::number(abs($delta), $this->indicator->decimals).' points'
            : Format::number(abs($delta / $previous['value'] * 100), 1).'%';

        $up = $delta > 0;
        $good = $this->indicator->good_direction;

        return [
            'direction' => $up ? 'up' : 'down',
            'tone' => $good === 'neutral' ? 'neutral' : (($up && $good === 'up') || (! $up && $good === 'down') ? 'rise' : 'fall'),
            'words' => ($up ? 'Up ' : 'Down ').$size.' on '.Format::period($previous['period'], $this->indicator->frequency),
        ];
    }

    /** "World Bank, World Development Indicators" style line: who published it, for which period, when we last looked. */
    public function provenance(): string
    {
        return $this->source->name.' · '.($this->period() ?? 'no figures yet');
    }
}
