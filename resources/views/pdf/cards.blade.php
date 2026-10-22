@extends('pdf.layout')
@section('title', $heading)
@section('css')
<style>
    @page { margin: 22px; }
    .card { width: 256px; height: 200px; border: 1px solid #9ca3af; border-radius: 8px; overflow: hidden; position: relative; background: #fff; }
    .band { height: 40px; color: #fff; padding: 5px 8px; }
    .a { background: #7a1066; } .b { background: #0f766e; }
    .nm { font-size: 12px; font-weight: bold; }
    .small td { font-size: 8px; padding: 1px 4px 1px 0; }
    .cell { padding: 5px; }
</style>
@endsection
@section('bare')
<table cellspacing="6">
@foreach(array_chunk($cards, 1) as $pair)
<tr>
@foreach($pair as $c)
<td class="cell">
  <div class="card">
    <div class="band {{ $c['kind'] === 'parent' ? 'b' : 'a' }}"><table width="100%"><tr><td width="46">@if($school['logo'])<img src="{{ $school['logo'] }}" width="44">@endif</td><td><span class="nm">{{ $school['name'] }}</span><br><span style="font-size:7px;letter-spacing:1px">{{ strtoupper(['student' => 'Student identity card', 'employee' => 'Staff identity card', 'parent' => 'Visiting pass'][$c['kind']]) }}</span></td></tr></table></div>
    <table style="margin:6px 8px"><tr><td width="70">@if($c['photo_data'])<img src="{{ $c['photo_data'] }}" width="64" height="80">@endif</td>
    <td><span class="nm">{{ $c['name'] }}</span>
    <table class="small">@foreach(array_slice($c['lines'], 0, 4) as $l)<tr><td class="muted">{{ $l[0] }}</td><td><strong>{{ $l[1] }}</strong></td></tr>@endforeach<tr><td class="muted">ID</td><td><strong>{{ $c['number'] }}</strong></td></tr></table></td></tr></table>
    <div style="position:absolute;bottom:6px;left:8px"><img src="{{ $c['barcode'] }}" height="20" width="120"></div>
    <div style="position:absolute;bottom:6px;right:8px;font-size:7px;text-align:right">Session {{ $c['session'] }}<br>Valid until {{ $c['valid_until'] }}</div>
  </div>
</td>
@if($backs)
<td class="cell">
  <div class="card"><div style="height:8px" class="{{ $c['kind'] === 'parent' ? 'b' : 'a' }}"></div>
  <div style="padding:8px;font-size:8.5px;line-height:1.5">
    @foreach($c['extra'] as $e)<div><span class="muted">{{ $e[0] }}</span><br><strong>{{ $e[1] }}</strong></div>@endforeach
    @if($c['phone'])<div>Phone {{ $c['phone'] }}</div>@endif
    @if($c['address'])<div>{{ $c['address'] }}</div>@endif
    <p class="muted" style="font-size:7.5px;border-top:1px solid #e5e7eb;padding-top:4px">{{ $c['kind'] === 'parent' ? 'Show this pass at the gate with a photo ID. It lets you visit the school; it is not an identity card.' : 'This card belongs to the school and must be carried every day. If found, please return it to:' }}<br><strong>{{ $school['name'] }}</strong> {{ $school['address'] }} {{ $school['phone'] }}</p>
  </div></div>
</td>
@endif
@endforeach
</tr>
@endforeach
</table>
@endsection
