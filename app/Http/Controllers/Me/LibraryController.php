<?php

namespace App\Http\Controllers\Me;

use App\Http\Controllers\Controller;
use App\Models\Intelligence\Geography;
use App\Models\Intelligence\Indicator;
use App\Services\Intelligence\Reading\Reader;
use App\Services\Library\Entitlements;
use App\Services\Library\Targets;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LibraryController extends Controller
{
    public function home(Request $request, Targets $targets, Reader $reader): View
    {
        $user = $request->user();
        $watching = $targets->watching($user);

        $figures = $watching->filter(fn ($w) => $w['watch']->kind === 'indicator' && $w['target'])->take(6)->map(function ($w) use ($reader) {
            $indicator = Indicator::where('key', $w['watch']->key)->first();
            $place = Geography::where('code', $w['watch']->place)->first();

            return $indicator && $place ? $reader->figure($indicator, $place, 24) : null;
        })->filter()->values();

        return view('me.home', [
            'watching' => $watching, 'figures' => $figures,
            'alerts' => $user->libraryAlerts()->orderByDesc('id')->limit(8)->get(),
            'saved' => $user->librarySaved()->orderByDesc('id')->limit(5)->get()->map(fn ($s) => ['saved' => $s, 'target' => $targets->forSaved($s)]),
            'limits' => Entitlements::for($user),
        ]);
    }

    public function watchlist(Request $request, Targets $targets): View
    {
        return view('me.watchlist', ['watching' => $targets->watching($request->user()), 'limits' => Entitlements::for($request->user())]);
    }

    public function saved(Request $request, Targets $targets): View
    {
        return view('me.saved', ['rows' => $request->user()->librarySaved()->orderByDesc('id')->get()->map(fn ($s) => ['saved' => $s, 'target' => $targets->forSaved($s)])]);
    }

    public function alerts(Request $request): View
    {
        return view('me.alerts', ['alerts' => $request->user()->libraryAlerts()->orderByDesc('id')->paginate(30)]);
    }

    public function readAlerts(Request $request): RedirectResponse
    {
        $request->user()->libraryAlerts()->whereNull('read_at')->update(['read_at' => now()]);

        return redirect()->route('me.alerts')->with('status', 'All alerts marked as read.');
    }
}
