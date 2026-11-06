<?php

namespace App\Http\Controllers\Intelligence;

use App\Http\Controllers\Controller;
use App\Models\Intelligence\Geography;
use App\Models\Intelligence\Indicator;
use App\Models\Intelligence\Observation;
use App\Services\Intelligence\Reading\Format;
use App\Services\Intelligence\Reading\Reader;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class IndicatorController extends Controller
{
    public function index(): View
    {
        $indicators = Indicator::where('is_published', true)->whereHas('observations')->with('source')->orderBy('topic')->orderBy('name')->get();

        return view('intel.indicators', ['byTopic' => $indicators->groupBy('topic')]);
    }

    public function show(Request $request, Indicator $indicator, Reader $reader): View
    {
        abort_unless($indicator->is_published, 404);

        $place = $this->place($request, $indicator);
        $figure = $reader->figure($indicator, $place);
        abort_if($figure === null, 404);

        return view('intel.indicator', ['indicator' => $indicator, 'place' => $place, 'figure' => $figure, 'compare' => $this->compare($indicator, $reader)]);
    }

    public function csv(Request $request, Indicator $indicator, Reader $reader): Response
    {
        abort_unless($indicator->is_published, 404);

        $place = $this->place($request, $indicator);
        $figure = $reader->figure($indicator, $place, 1000);
        abort_if($figure === null, 404);

        $lines = ['# '.$indicator->name.', '.$place->name.'. Unit: '.$indicator->unit.'.', '# Source: '.$indicator->source->name.'. '.$indicator->source->attribution, 'period,value'];

        foreach ($figure->series as $row) {
            $lines[] = $row['period']->toDateString().','.$row['value'];
        }

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="'.$indicator->key.'-'.$place->code.'.csv"']);
    }

    private function place(Request $request, Indicator $indicator): Geography
    {
        $code = strtoupper((string) $request->query('place', ''));
        $has = Observation::where('indicator_id', $indicator->id);
        $place = $code !== '' ? Geography::where('code', $code)->whereIn('id', (clone $has)->select('geography_id'))->first() : null;

        return $place ?? Geography::whereIn('id', (clone $has)->select('geography_id'))->orderByRaw("case code when 'ZAF' then 0 when 'WLD' then 1 else 2 end")->orderBy('name')->firstOrFail();
    }

    /** The newest value for every place that has one, newest first, so the figure is never read alone. */
    private function compare(Indicator $indicator, Reader $reader): array
    {
        $latest = Observation::where('indicator_id', $indicator->id)->selectRaw('geography_id, max(period) as period')->groupBy('geography_id')->pluck('period', 'geography_id');
        $rows = Observation::where('indicator_id', $indicator->id)->whereIn('geography_id', $latest->keys())->get()
            ->filter(fn ($o) => $o->period->toDateString() === substr((string) $latest[$o->geography_id], 0, 10))->values();
        $places = Geography::whereIn('id', $rows->pluck('geography_id'))->get()->keyBy('id');

        return $rows->map(fn ($o) => [
            'geography' => $places[$o->geography_id], 'value' => Format::figure($o->value, $indicator->unit, $indicator->decimals), 'raw' => $o->value,
            'period' => Format::period($o->period, $indicator->frequency), 'stale' => $o->period->diffInDays(now()) > $indicator->stale_after_days,
        ])->sortByDesc('raw')->values()->all();
    }
}
