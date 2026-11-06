<?php

namespace App\Http\Controllers\Me;

use App\Http\Controllers\Controller;
use App\Models\Intelligence\Item;
use App\Services\Library\Entitlements;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SavedController extends Controller
{
    /** @return array{kind: string, ref: string, title: string}|null */
    private function subject(string $kind, string $ref): ?array
    {
        if ($kind === 'item') {
            $item = Item::listed()->where('uuid', $ref)->first();

            return $item === null ? null : ['kind' => 'item', 'ref' => $item->uuid, 'title' => $item->title];
        }

        $ref = trim(mb_substr($ref, 0, 160));

        return $kind === 'search' && $ref !== '' ? ['kind' => 'search', 'ref' => $ref, 'title' => 'Search: '.Str::limit($ref, 120)] : null;
    }

    public function confirm(Request $request): View
    {
        $subject = $this->subject((string) $request->query('kind'), (string) $request->query('ref'));
        abort_if($subject === null, 404);

        return view('me.save-confirm', ['subject' => $subject, 'already' => $request->user()->librarySaved()->where(['kind' => $subject['kind'], 'ref' => $subject['ref']])->exists()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['kind' => ['required', 'in:item,search'], 'ref' => ['required', 'string', 'max:190']]);
        $subject = $this->subject($data['kind'], $data['ref']);
        abort_if($subject === null, 404);

        $user = $request->user();
        $exists = $user->librarySaved()->where(['kind' => $subject['kind'], 'ref' => $subject['ref']])->exists();

        if (! $exists && $user->librarySaved()->count() >= Entitlements::for($user)->saved()) {
            return redirect()->route('me.saved')->with('error', 'Your saved list is full. Remove something to make room.');
        }

        $user->librarySaved()->firstOrCreate(['kind' => $subject['kind'], 'ref' => $subject['ref']], ['title' => $subject['title']]);

        return redirect()->route('me.saved')->with('status', 'Saved.');
    }

    public function destroy(Request $request, int $saved): RedirectResponse
    {
        $request->user()->librarySaved()->whereKey($saved)->delete();

        return redirect()->route('me.saved')->with('status', 'Removed.');
    }
}
