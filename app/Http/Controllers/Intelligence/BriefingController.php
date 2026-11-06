<?php

namespace App\Http\Controllers\Intelligence;

use App\Http\Controllers\Controller;
use App\Models\Intelligence\Briefing;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BriefingController extends Controller
{
    public function index(): View
    {
        $editions = collect((array) config('intelligence_briefings.editions'))->map(fn ($def, $key) => $def + [
            'key' => $key, 'latest' => Briefing::where('edition', $key)->orderByDesc('edition_date')->first(),
        ]);

        return view('intel.briefings', ['editions' => $editions]);
    }

    public function show(string $edition, string $date): View
    {
        $def = config("intelligence_briefings.editions.{$edition}");
        abort_if($def === null || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date), 404);

        return view('intel.briefing', ['def' => $def, 'briefing' => Briefing::where('edition', $edition)->where('edition_date', $date)->firstOrFail(), 'edition' => $edition]);
    }

    public function latest(string $edition): RedirectResponse
    {
        $briefing = Briefing::where('edition', $edition)->orderByDesc('edition_date')->first();
        abort_if($briefing === null || config("intelligence_briefings.editions.{$edition}") === null, 404);

        return redirect()->route('intel.briefing', [$edition, $briefing->edition_date->toDateString()]);
    }
}
