<?php

namespace App\Http\Controllers\Me;

use App\Http\Controllers\Controller;
use App\Models\Library\Preference;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PreferenceController extends Controller
{
    public function show(Request $request): View
    {
        return view('me.preferences', ['digest' => $request->user()->libraryPreference?->digest ?? 'off']);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['digest' => ['required', 'in:'.implode(',', Preference::DIGESTS)]]);
        $pref = Preference::firstOrCreate(['user_id' => $request->user()->id], ['last_scan_at' => now()->subDays(7)]);
        $pref->update(['digest' => $data['digest']] + ($data['digest'] !== 'off' && $pref->digest === 'off' ? ['last_digest_at' => now()] : []));

        return redirect()->route('me.preferences')->with('status', $data['digest'] === 'off' ? 'The email digest is off.' : 'You will get the '.$data['digest'].' digest, and only when there is something to say.');
    }

    /** Everything the library holds about the person, in one file. */
    public function export(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'exported_at' => now()->toIso8601String(),
            'account' => ['name' => $user->name, 'email' => $user->email],
            'preferences' => $user->libraryPreference?->only(['digest', 'last_digest_at']),
            'watching' => $user->libraryWatches()->get(['kind', 'key', 'place', 'created_at']),
            'saved' => $user->librarySaved()->get(['kind', 'ref', 'title', 'created_at']),
            'alerts' => $user->libraryAlerts()->get(['kind', 'title', 'detail', 'href', 'created_at', 'read_at']),
        ], 200, ['Content-Disposition' => 'attachment; filename="my-library.json"'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /** Deletes what the library keeps about the person. The account itself belongs to InfoDot and is not touched. */
    public function erase(Request $request): RedirectResponse
    {
        $request->validate(['confirm' => ['required', 'in:erase']]);
        $user = $request->user();

        $user->libraryWatches()->delete();
        $user->librarySaved()->delete();
        $user->libraryAlerts()->delete();
        $user->libraryPreference()->delete();

        return redirect()->route('me.home')->with('status', 'Everything the library kept about you is deleted.');
    }

    /** From the link in an email. Idempotent, and says what happened. */
    public function stop(Request $request, User $user): View
    {
        Preference::where('user_id', $user->id)->update(['digest' => 'off']);

        return view('me.stopped');
    }
}
