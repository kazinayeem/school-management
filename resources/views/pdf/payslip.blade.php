@extends('pdf.layout')
@section('title', 'Payslip · '.$slip->month)
@section('subtitle', $me['name'])
@section('content')
<table class="kv"><tr><td class="muted">Name</td><td><strong>{{ $me['name'] }}</strong></td><td class="muted">Employee no.</td><td>{{ $me['employee_no'] }}</td></tr>
<tr><td class="muted">Designation</td><td>{{ $me['designation'] }}</td><td class="muted">Paid on</td><td>{{ $slip->paid_on }}</td></tr></table>
<table class="grid" style="margin-top:12px"><tbody>
<tr><td>Basic salary</td><td class="num">{{ $currency }} {{ number_format($slip->basic) }}</td></tr>
<tr><td>Allowances</td><td class="num">{{ $currency }} {{ number_format($slip->earnings) }}</td></tr>
<tr><td>Deductions</td><td class="num">− {{ $currency }} {{ number_format($slip->deductions) }}</td></tr>
<tr><th>Net pay</th><th class="num" style="font-size:13px">{{ $currency }} {{ number_format($slip->net) }}</th></tr></tbody></table>
@endsection
