@extends('pdf.layout')
@section('title', 'Fee receipt')
@section('subtitle', $payment->receipt_no)
@section('content')
<table class="kv"><tr><td class="muted">Student</td><td><strong>{{ $student->name }}</strong></td><td class="muted">Class</td><td>{{ $student->class_name }} {{ $student->section }} · Roll {{ $student->roll_no }}</td></tr>
<tr><td class="muted">Admission no.</td><td>{{ $student->admission_no }}</td><td class="muted">Date</td><td>{{ $payment->paid_on }}</td></tr>
<tr><td class="muted">Method</td><td>{{ $payment->method }}</td><td class="muted">Receipt</td><td>{{ $payment->receipt_no }}</td></tr></table>
<table class="grid" style="margin-top:12px"><tbody>
<tr><td>Amount paid</td><td class="num">{{ number_format($payment->amount) }}</td></tr>
@if($payment->late_fee > 0)<tr><td>Late fee</td><td class="num">{{ number_format($payment->late_fee) }}</td></tr>@endif
<tr><th>Total received</th><th class="num">{{ number_format($payment->amount + $payment->late_fee) }}</th></tr></tbody></table>
<p class="muted">This is a computer-generated receipt.</p>
@endsection
