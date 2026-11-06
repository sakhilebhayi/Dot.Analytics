<?php

namespace App\Http\Controllers\Me;

use App\Http\Controllers\Controller;
use App\Models\Intelligence\DataIssue;
use App\Models\Intelligence\Indicator;
use App\Models\Intelligence\Item;
use App\Models\Intelligence\Observation;
use App\Models\Intelligence\PlatformCheck;
use App\Models\Intelligence\Source;
use App\Models\Intelligence\SourceRun;
use App\Services\Intelligence\SiteMeasures;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** How the library is doing: whether it is collecting, whether anyone is reading, and what searches find nothing. For the platform's operators only. */
class OperateController extends Controller
{
    public function __invoke(Request $request, SiteMeasures $measures): View
    {
        Gate::authorize('operate-platform');
        $db = $measures->connection();
        $from = now()->subDays(13)->toDateString();

        $days = $db->table('visits')->where('area', '_all')->where('day', '>=', $from)->orderByDesc('day')->get(['day', 'visitors', 'returning']);
        $views = $db->table('visits')->where('area', '!=', '_all')->where('day', '>=', $from)->selectRaw('day, sum(views) as views')->groupBy('day')->pluck('views', 'day');

        $file = config('database.connections.'.config('intelligence.connection').'.database');

        return view('me.operate', [
            'days' => $days->map(fn ($d) => (object) ['day' => $d->day, 'visitors' => $d->visitors, 'returning' => $d->returning, 'views' => (int) ($views[$d->day] ?? 0)]),
            'pages' => $db->table('visits')->where('area', '!=', '_all')->where('day', '>=', $from)->selectRaw('area, target, sum(views) as views')->groupBy('area', 'target')->orderByDesc('views')->limit(15)->get(),
            'empty' => $db->table('search_log')->where('day', '>=', $from)->where('hits', 0)->selectRaw('query, count(*) as asked')->groupBy('query')->orderByDesc('asked')->limit(15)->get(),
            'searches' => $db->table('search_log')->where('day', '>=', $from)->selectRaw('count(*) as asked, sum(case when hits = 0 then 1 else 0 end) as empty')->first(),
            'referrals' => $db->table('referrals')->where('day', '>=', $from)->selectRaw('kind, target, sum(clicks) as clicks')->groupBy('kind', 'target')->orderByDesc('clicks')->limit(15)->get(),
            'sources' => Source::orderBy('status')->orderBy('name')->get(),
            'runs' => SourceRun::with('source:id,name')->orderByDesc('id')->limit(15)->get(),
            'issues' => DataIssue::whereNull('resolved_at')->orderByDesc('last_seen_at')->limit(20)->get(),
            'checks' => PlatformCheck::orderBy('key')->get(),
            'counts' => ['series' => Indicator::where('is_published', true)->count(), 'readings' => Observation::count(), 'items' => Item::count(), 'size' => is_string($file) && is_file($file) ? round(filesize($file) / 1_048_576, 1) : null],
        ]);
    }
}
