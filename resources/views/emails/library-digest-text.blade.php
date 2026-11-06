Your {{ $frequency }} digest from Dot.Analytics

@if($digest['figures'] !== [])
HOW WHAT YOU WATCH STANDS
@foreach($digest['figures'] as $f)
- {{ $f['name'] }}: {{ $f['value'] }} ({{ $f['when'] }}, {{ $f['source'] }}){{ $f['change'] ? '. '.$f['change'].'.' : '' }}
  {{ $f['href'] }}
@endforeach

@endif
@if($digest['movements'] !== [])
MOVED MORE THAN USUAL (worked out from the numbers)
@foreach($digest['movements'] as $m)
- {{ $m->title }}
  {{ $m->detail }} {{ $m->href }}
@endforeach

@endif
@if($digest['developments'] !== [])
PUBLISHED SINCE {{ strtoupper($digest['since']->format('j F')) }}
@foreach($digest['developments'] as $d)
- {{ $d->title }}
  {{ $d->detail }} {{ $d->href }}
@endforeach

@endif
You get this because you turned on the {{ $frequency }} digest in your library. Every line links to where it came from.
Turn it off in one click: {{ $stopUrl }}
