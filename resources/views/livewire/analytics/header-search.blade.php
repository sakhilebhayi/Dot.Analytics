<div class="hs">
    <x-dot.search wire="query" name="q" :action="route('search')" :value="$query" placeholder="Search…" label="Search your company's intelligence" maxlength="100"
        results-nav escape-clears :root-attributes="['wire:ignore.self' => '']"
        loading-text="Searching your workspace…">
        <x-slot:results>
            @if(! $this->ready())
                <div class="dsr-quick">
                    <div class="dsr-head">Go to</div>
                    <div class="dsr-chips">
                        @foreach ([['dashboard', 'Overview'], ['workspace.figures', 'Figures'], ['workspace.insights', 'Insights'], ['workspace.reports', 'Reports'], ['workspace.sources', 'Sources']] as [$route, $label])
                            <a class="dsr-chip" href="{{ route($route) }}">{{ $label }}</a>
                        @endforeach
                    </div>
                    <p class="dsr-foot">Type two letters to search figures, sources, insights, alerts, recommendations, reports and dashboards.</p>
                </div>
            @else
                <div class="dsr-quick">
                    @forelse($this->groups as $group)
                        <x-dot.search-head>{{ $group['head'] }}</x-dot.search-head>
                        @foreach($group['rows'] as $i => $row)
                            <x-dot.search-row :href="$row['href']" :label="$row['label']" :tag="$row['tag']" wire:key="hs-{{ Str::slug($group['head']) }}-{{ $i }}" />
                        @endforeach
                    @empty
                        <p class="dsr-foot" role="status">Nothing in your workspace matches &ldquo;{{ trim($query) }}&rdquo;.</p>
                    @endforelse
                    <a class="dsr-item dsr-item--action" role="option" href="{{ route('search', ['q' => trim($query)]) }}"><span class="dsr-label">See all results for &ldquo;{{ trim($query) }}&rdquo;</span></a>
                </div>
            @endif
        </x-slot:results>
    </x-dot.search>
</div>
