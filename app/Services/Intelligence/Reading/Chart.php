<?php

namespace App\Services\Intelligence\Reading;

use Illuminate\Support\Carbon;

/**
 * Turns a series into the numbers an SVG needs. Drawn on the server so the page needs no script to be read, and tested as
 * plain arithmetic. It never smooths, never fills a gap, and starts the axis at zero only when the data crosses it.
 */
final class Chart
{
    public const W = 640;

    public const H = 240;

    private const PAD_L = 8;

    private const PAD_R = 8;

    private const PAD_T = 12;

    private const PAD_B = 26;

    /**
     * @param  list<array{period: Carbon, value: float}>  $series
     * @return array{path: string, points: list<array{x: float, y: float, w: float, label: string, readout: string}>, ticks: list<array{y: float, label: string}>, zero: ?float, xlabels: list<array{x: float, label: string, anchor: string}>}
     */
    public static function layout(FigureData $figure, array $series): array
    {
        $count = count($series);
        $values = array_column($series, 'value');
        $min = min($values);
        $max = max($values);

        [$lo, $hi] = self::scale($min, $max);
        $plotW = self::W - self::PAD_L - self::PAD_R;
        $plotH = self::H - self::PAD_T - self::PAD_B;
        $step = $count > 1 ? $plotW / ($count - 1) : 0;
        $y = fn (float $v) => self::PAD_T + $plotH * (1 - ($v - $lo) / ($hi - $lo));

        $points = [];
        $path = '';

        foreach ($series as $i => $row) {
            $x = self::PAD_L + ($count > 1 ? $step * $i : $plotW / 2);
            $py = $y($row['value']);
            $when = Format::period($row['period'], $figure->indicator->frequency);
            $path .= ($i === 0 ? 'M' : 'L').round($x, 2).' '.round($py, 2).' ';
            $points[] = [
                'x' => round($x, 2), 'y' => round($py, 2), 'w' => round(max($step, 12), 2), 'label' => $when,
                'readout' => $when.': '.Format::figure($row['value'], $figure->indicator->unit, $figure->indicator->decimals).' '.Format::note($figure->indicator->unit),
            ];
        }

        $ticks = [];

        foreach (self::ticks($lo, $hi) as $t) {
            $ticks[] = ['y' => round($y($t), 2), 'label' => Format::number($t, $figure->indicator->decimals > 0 && ($hi - $lo) < 20 ? 1 : 0)];
        }

        $xlabels = [];

        if ($count > 0) {
            $xlabels[] = ['x' => $points[0]['x'], 'label' => $points[0]['label'], 'anchor' => 'start'];

            if ($count > 4) {
                $mid = intdiv($count, 2);
                $xlabels[] = ['x' => $points[$mid]['x'], 'label' => $points[$mid]['label'], 'anchor' => 'middle'];
            }

            if ($count > 1) {
                $xlabels[] = ['x' => $points[$count - 1]['x'], 'label' => $points[$count - 1]['label'], 'anchor' => 'end'];
            }
        }

        return [
            'path' => trim($path), 'points' => $points, 'ticks' => $ticks, 'xlabels' => $xlabels,
            'zero' => $lo < 0 && $hi > 0 ? round($y(0.0), 2) : null,
        ];
    }

    /** @return array{0: float, 1: float} a range a little wider than the data, including zero only if the data crosses it */
    private static function scale(float $min, float $max): array
    {
        if ($max - $min < 1e-9) {
            $pad = max(abs($max) * 0.1, 1.0);

            return [$min - $pad, $max + $pad];
        }

        $pad = ($max - $min) * 0.12;

        return [$min - $pad, $max + $pad];
    }

    /** @return list<float> three or four round numbers inside the range */
    private static function ticks(float $lo, float $hi): array
    {
        $span = $hi - $lo;
        $rough = $span / 3;
        $pow = 10 ** floor(log10($rough));
        $step = $pow * match (true) {
            $rough / $pow >= 5 => 5, $rough / $pow >= 2 => 2, default => 1,
        };
        $ticks = [];

        for ($t = ceil($lo / $step) * $step; $t <= $hi + 1e-9; $t += $step) {
            $ticks[] = round($t, 6);
        }

        return $ticks;
    }
}
