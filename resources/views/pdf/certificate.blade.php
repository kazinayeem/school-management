@extends('pdf.layout')
@section('title', $certificate->title)
@section('css')
<style>
    @page { margin: 0; }
    body { margin: 0; }
    .frame { margin: 22px; border: 3px solid #b8860b; padding: 8px; height: 520px; }
    .inner { border: 1px solid #b8860b; height: 504px; text-align: center; padding: 20px 50px; }
    .title { font-size: 30px; letter-spacing: 4px; color: #8a5a00; margin: 14px 0 6px; font-family: 'DejaVu Serif', serif; }
    .name { font-size: 22px; font-weight: bold; }
    .sig td { text-align: center; font-size: 9px; padding-top: 40px; }
    .line { border-top: 1px solid #555; padding-top: 3px; }
    .madeby { bottom: 8px; }
</style>
@endsection
@section('bare')
<div class="frame"><div class="inner">
    @if($school['logo'])<img src="{{ $school['logo'] }}" height="42">@endif
    <div style="font-size:15px;font-weight:bold">{{ $school['name'] }}</div>
    <div class="title">{{ strtoupper($certificate->title) }}</div>
    @if($photo)<img src="{{ $photo }}" width="64" height="78" style="margin:4px">@endif
    <p style="font-size:13px;line-height:1.7">{!! nl2br(e($certificate->body)) !!}</p>
    <p class="muted" style="font-size:9px">{{ $certificate->number }} · {{ $certificate->issued_on }}</p>
    <table width="100%" class="sig"><tr><td width="33%"><div class="line">Class teacher</div></td><td><img src="{{ $barcode }}" height="26"><div>{{ $certificate->number }}</div></td><td width="33%"><div class="line">Principal</div></td></tr></table>
</div></div>
@endsection
