<!DOCTYPE html>
<html lang="en">
<body style="margin:0;padding:24px;background:#f4f6f8;color:#10151c;font-family:'IBM Plex Sans',Helvetica,Arial,sans-serif;font-size:16px;line-height:1.5">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">
<table role="presentation" width="600" style="max-width:600px;background:#ffffff;border:1px solid #d7dce3">
<tr><td style="padding:24px 28px 8px"><h1 style="margin:0;font-size:24px;line-height:1.2">Your {{ $frequency }} digest</h1><p style="margin:6px 0 0;color:#566070">From Dot.Analytics. Every line links to where it came from.</p></td></tr>
@if($digest['figures'] !== [])
<tr><td style="padding:16px 28px 0"><h2 style="margin:0 0 8px;font-size:18px">How what you watch stands</h2>
@foreach($digest['figures'] as $f)
<p style="margin:0 0 10px"><a href="{{ $f['href'] }}" style="color:#2338d8"><b>{{ $f['name'] }}</b></a><br>{{ $f['value'] }} <span style="color:#566070">({{ $f['when'] }}, {{ $f['source'] }})</span>@if($f['change'])<br><span style="color:#566070">{{ $f['change'] }}.</span>@endif</p>
@endforeach
</td></tr>
@endif
@if($digest['movements'] !== [])
<tr><td style="padding:16px 28px 0"><h2 style="margin:0 0 8px;font-size:18px">Moved more than usual</h2><p style="margin:0 0 8px;color:#566070">Worked out from the numbers. No model wrote these.</p>
@foreach($digest['movements'] as $m)
<p style="margin:0 0 10px"><a href="{{ $m->href }}" style="color:#2338d8">{{ $m->title }}</a><br><span style="color:#566070">{{ $m->detail }}</span></p>
@endforeach
</td></tr>
@endif
@if($digest['developments'] !== [])
<tr><td style="padding:16px 28px 0"><h2 style="margin:0 0 8px;font-size:18px">Published since {{ $digest['since']->format('j F') }}</h2>
@foreach($digest['developments'] as $d)
<p style="margin:0 0 10px"><a href="{{ $d->href }}" style="color:#2338d8">{{ $d->title }}</a><br><span style="color:#566070">{{ $d->detail }}</span></p>
@endforeach
</td></tr>
@endif
<tr><td style="padding:20px 28px 28px;color:#566070;font-size:14px;border-top:1px solid #d7dce3;margin-top:16px">You get this because you turned on the {{ $frequency }} digest in your library. <a href="{{ $stopUrl }}" style="color:#2338d8">Turn it off in one click</a>.</td></tr>
</table></td></tr></table>
</body>
</html>
