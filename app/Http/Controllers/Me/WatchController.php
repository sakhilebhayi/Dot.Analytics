<?php

namespace App\Http\Controllers\Me;

use App\Http\Controllers\Controller;
use App\Models\Library\Preference;
use App\Services\Library\Entitlements;
use App\Services\Library\Targets;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WatchController extends Controller
{
    public function confirm(Request $request, Targets $targets): View
    {
        $target = $targets->resolve((string) $request->query('kind'), (string) $request->query('key'), (string) $request->query('place'));
        abort_if($target === null, 404);

        return view('me.watch-confirm', [
            'target' => $target,
            'already' => $request->user()->libraryWatches()->where(['kind' => $target['kind'], 'key' => $target['key'], 'place' => $target['place']])->exists(),
            'full' => $request->user()->libraryWatches()->count() >= Entitlements::for($request->user())->watches(),
        ]);
    }

    public function store(Request $request, Targets $targets): RedirectResponse
    {
        $data = $request->validate(['kind' => ['required', 'in:'.implode(',', Targets::KINDS)], 'key' => ['required', 'string', 'max:120'], 'place' => ['nullable', 'string', 'max:8']]);
        $target = $targets->resolve($data['kind'], $data['key'], $data['place'] ?? '');
        abort_if($target === null, 404);

        $user = $request->user();
        $exists = $user->libraryWatches()->where(['kind' => $target['kind'], 'key' => $target['key'], 'place' => $target['place']])->exists();

        if (! $exists && $user->libraryWatches()->count() >= Entitlements::for($user)->watches()) {
            return redirect()->route('me.watchlist')->with('error', 'Your watchlist is full. Stop watching something to make room.');
        }

        // The first thing someone watches also gives them the last week of what the library noticed about it.
        Preference::firstOrCreate(['user_id' => $user->id], ['last_scan_at' => now()->subDays(7)]);
        $user->libraryWatches()->firstOrCreate(['kind' => $target['kind'], 'key' => $target['key'], 'place' => $target['place']]);

        return redirect()->route('me.home')->with('status', 'Now watching '.$target['label'].'.');
    }

    public function destroy(Request $request, int $watch): RedirectResponse
    {
        $request->user()->libraryWatches()->whereKey($watch)->delete();

        return redirect()->route('me.watchlist')->with('status', 'Stopped watching.');
    }
}
