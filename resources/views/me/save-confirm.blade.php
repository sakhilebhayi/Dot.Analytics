<x-me-layout title="Save">
    <div class="wrap page-head"><h1>Save this?</h1><p class="lede">{{ $subject['title'] }}</p></div>
    <div class="wrap section">
        @if($already)
            <p class="empty-note"><b>Already saved.</b> <a href="{{ route('me.saved') }}">See your saved list</a>.</p>
        @else
            <form method="post" action="{{ route('me.save.store') }}" class="btn-row">
                @csrf
                <input type="hidden" name="kind" value="{{ $subject['kind'] }}"><input type="hidden" name="ref" value="{{ $subject['ref'] }}">
                <button class="btn" type="submit">Save</button>
            </form>
        @endif
    </div>
</x-me-layout>
