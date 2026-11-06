<x-me-layout title="Preferences">
    <div class="wrap page-head"><h1>Preferences, and your data.</h1><p class="lede">The library keeps what you watch and save, the alerts it wrote for you, and this setting. Nothing about what you read is recorded against you.</p></div>

    <section class="wrap section" aria-labelledby="d-h">
        <div class="section-head"><h2 id="d-h">Email digest</h2></div>
        <p class="prose">A short email of how what you watch stands, and what was collected since the last one. It is off until you turn it on, nothing is sent when there is nothing to say, and every email has a one-click link to turn it off.</p>
        <form method="post" action="{{ route('me.preferences.update') }}" class="form-stack">
            @csrf @method('PUT')
            <label>How often
                <select name="digest">@foreach(['off' => 'Off', 'daily' => 'Every morning', 'weekly' => 'Monday mornings'] as $v => $l)<option value="{{ $v }}" @selected($digest === $v)>{{ $l }}</option>@endforeach</select>
            </label>
            <button class="btn" type="submit">Save</button>
        </form>
    </section>

    <section class="wrap section" aria-labelledby="x-h">
        <div class="section-head"><h2 id="x-h">Your data</h2></div>
        <p class="prose">Download everything the library keeps about you, or delete it. Deleting removes your watchlist, saved items, alerts and this setting at once. Your account itself belongs to InfoDot and is not touched.</p>
        <p class="btn-row"><a class="btn quiet" href="{{ route('me.export') }}">Download my data</a></p>
        <form method="post" action="{{ route('me.erase') }}" class="form-stack">
            @csrf @method('DELETE')
            <label>Type <b>erase</b> to confirm<input type="text" name="confirm" autocomplete="off"></label>
            <button class="btn danger" type="submit">Delete my library data</button>
        </form>
    </section>
</x-me-layout>
