@extends('pdf.layout')
@section('title', 'Admit cards')
@section('css')<style>@page{margin:20px}.card{border:1.5px solid #111;padding:8px;margin:6px;page-break-inside:avoid}</style>@endsection
@section('bare')
@foreach($students as $s)
<div class="card">
<table width="100%"><tr><td width="64">@if($s['photo'])<img src="{{ $s['photo'] }}" width="56" height="70">@endif</td>
<td><strong style="font-size:13px">{{ $school['name'] }}</strong><br>{{ $exam->name }} — <strong>ADMIT CARD</strong><br>
<span class="muted">Name</span> <strong>{{ $s['name'] }}</strong> · <span class="muted">Class</span> {{ $class }} {{ $s['section'] }} · <span class="muted">Roll</span> {{ $s['roll_no'] }} · <span class="muted">ID</span> {{ $s['admission_no'] }}</td>
<td align="right"><img src="{{ $s['barcode'] }}" height="26"></td></tr></table>
<table class="grid" style="margin-top:6px"><thead><tr><th>Date</th><th>Subject</th><th>Time</th><th>Room</th></tr></thead><tbody>
@foreach($schedule as $r)<tr><td>{{ $r['date'] }}</td><td>{{ $r['subject'] }}</td><td>{{ $r['time'] }}</td><td>{{ $r['room'] }}</td></tr>@endforeach
</tbody></table>
<p class="muted" style="font-size:8px">Bring this card to every exam. Arrive 15 minutes early. Signature of principal: ____________________</p>
</div>
@endforeach
@endsection
