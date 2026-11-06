<x-public-layout title="Industries" section="intel.industries" description="Seventeen industries, each with the figures and developments we hold, and a plain account of what is missing.">
    <div class="wrap page-head">
        <h1>Seventeen industries, and how much we really hold on each.</h1>
        <p class="lede">Coverage is graded from the series that are current, not from how much we would like to show. South Africa is the deepest record.</p>
    </div>
    <div class="wrap section">
        <ul class="index">
            @foreach($rows as $row)
                <li>
                    <a href="{{ route('intel.industry', $row['industry']) }}">
                        <span class="name">{{ $row['industry']->name }}</span>
                        <span class="says">{{ $row['industry']->summary }}</span>
                        <span class="grade"><b>{{ $row['grade'] }} coverage</b><x-intel.cover :cells="$row['cells']"/></span>
                    </a>
                </li>
            @endforeach
        </ul>
        <x-intel.cover :cells="[]" :key="true"/>
    </div>
</x-public-layout>
