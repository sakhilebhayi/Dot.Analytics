@if ($errors->any())
    <div {{ $attributes->merge(['class' => 'rounded-lg border border-red-400/30 bg-red-400/10 px-4 py-3']) }}>
        <div class="font-medium text-sm text-red-400">{{ __('Whoops! Something went wrong.') }}</div>

        <ul class="mt-2 list-disc list-inside text-sm text-red-400/90">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
