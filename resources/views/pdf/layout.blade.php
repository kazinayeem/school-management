<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>@yield('title')</title>
<style>
    @page { margin: 28px 30px; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10.5px; color: #111827; }
    h1 { font-size: 17px; margin: 0; }
    h2 { font-size: 12px; margin: 14px 0 5px; color: {{ $school['color'] ?? '#86146f' }}; }
    .head { border-bottom: 2px solid {{ $school['color'] ?? '#b31299' }}; padding-bottom: 8px; margin-bottom: 10px; }
    .head td { vertical-align: middle; }
    .muted { color: #6b7280; }
    table.grid { width: 100%; border-collapse: collapse; }
    table.grid th { background: #f3e8f5; text-align: left; font-size: 9px; padding: 4px 5px; border: 1px solid #d1d5db; }
    table.grid td { padding: 4px 5px; border: 1px solid #e5e7eb; }
    .num { text-align: right; }
    .foot { margin-top: 18px; font-size: 8.5px; color: #6b7280; }
    .madeby { position: fixed; bottom: -18px; left: 0; right: 0; text-align: center; font-size: 8px; color: #6b7280; }
    .pill { font-size: 8.5px; padding: 1px 5px; border: 1px solid #d1d5db; }
    .kv td { padding: 2px 8px 2px 0; vertical-align: top; }
</style>
@yield('css')
</head>
<body>
<div class="madeby">Made by bornosoft.bd</div>
@hasSection('bare')
@yield('bare')
@else
@if(!empty($school['banner']))
<div class="head" style="padding-bottom:6px"><img src="{{ $school['banner'] }}" style="width:100%"><div style="text-align:right"><strong>@yield('title')</strong> <span class="muted">@yield('subtitle')</span></div></div>
@else
<table class="head" width="100%"><tr>
    <td width="46">@if($school['logo'])<img src="{{ $school['logo'] }}" height="40">@endif</td>
    <td><h1>{{ $school['name'] }}</h1><div class="muted">{{ trim($school['address'].'  '.$school['phone']) }}</div></td>
    <td align="right"><strong>@yield('title')</strong><div class="muted">@yield('subtitle')</div></td>
</tr></table>
@endif
@yield('content')
<div class="foot">Generated {{ now()->format('d M Y, H:i') }} · {{ $school['name'] }}</div>
@endif
</body>
</html>
