@extends('pdf.layout')
@section('title', $title)
@section('subtitle', $subtitle ?? '')
@section('content')
@if(!empty($filters))<p class="muted">{{ $filters }}</p>@endif
@if(!empty($summary))
<table class="kv"><tr>@foreach($summary as $s)<td><strong style="font-size:14px">{{ is_numeric($s['value']) ? number_format($s['value']) : $s['value'] }}</strong><br><span class="muted">{{ $s['label'] }}</span></td>@endforeach</tr></table>
@endif
@foreach($sections as $section)
@if(!empty($section['title']))<h2>{{ $section['title'] }}</h2>@endif
<table class="grid">
<thead><tr>@foreach($section['columns'] as $c)<th class="{{ !empty($c['num']) || !empty($c['money']) ? 'num' : '' }}">{{ $c['label'] }}</th>@endforeach</tr></thead>
<tbody>
@forelse($section['rows'] as $row)
<tr>@foreach($section['columns'] as $c)@php($v = $row[$c['key']] ?? '')<td class="{{ !empty($c['num']) || !empty($c['money']) ? 'num' : '' }}">{{ !empty($c['money']) && is_numeric($v) ? number_format($v) : $v }}</td>@endforeach</tr>
@empty
<tr><td colspan="{{ count($section['columns']) }}" class="muted">No records.</td></tr>
@endforelse
@if(!empty($section['footer']))
<tr>@foreach($section['columns'] as $c)@php($v = $section['footer'][$c['key']] ?? '')<th class="{{ !empty($c['num']) || !empty($c['money']) ? 'num' : '' }}">{{ !empty($c['money']) && is_numeric($v) ? number_format($v) : $v }}</th>@endforeach</tr>
@endif
</tbody></table>
@endforeach
@endsection
