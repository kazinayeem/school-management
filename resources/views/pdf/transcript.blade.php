@extends('pdf.layout')
@section('title', $mode === 'file' ? 'Student file' : 'Academic transcript')
@section('subtitle', $student['name'].' · '.$student['admission_no'])
@section('content')
<table width="100%"><tr>
<td width="80">@if($photo)<img src="{{ $photo }}" width="70" height="86">@endif</td>
<td>
<table class="kv">
<tr><td class="muted">Name</td><td><strong>{{ $student['name'] }}</strong></td><td class="muted">Admission no.</td><td>{{ $student['admission_no'] }}</td></tr>
<tr><td class="muted">Class</td><td>{{ $student['class_name'] }} · {{ $student['section'] }} · Roll {{ $student['roll_no'] }}</td><td class="muted">Session</td><td>{{ $student['session'] }}</td></tr>
<tr><td class="muted">Date of birth</td><td>{{ $student['date_of_birth'] }}</td><td class="muted">Gender</td><td>{{ $student['gender'] }}</td></tr>
<tr><td class="muted">Guardian</td><td>{{ $student['guardian_name'] }} {{ $student['guardian_mobile'] }}</td><td class="muted">Address</td><td>{{ $student['address'] }}</td></tr>
</table></td>
<td align="right"><img src="{{ $barcode }}" height="30"></td></tr></table>

<h2>Academic results</h2>
@forelse($results as $exam)
<p><strong>{{ $exam['exam'] }}</strong> — GPA {{ $exam['gpa'] ?? '—' }} {{ $exam['grade'] ? '('.$exam['grade'].')' : '' }}@if($exam['class_position']) · Class position {{ $exam['class_position'] }}@endif</p>
<table class="grid"><thead><tr><th>Subject</th><th class="num">Marks</th><th class="num">Full</th><th>Grade</th></tr></thead><tbody>
@foreach($exam['subjects'] as $s)<tr><td>{{ $s['subject'] }}</td><td class="num">{{ $s['absent'] ? 'Absent' : $s['marks'] }}</td><td class="num">{{ $s['full_marks'] }}</td><td>{{ $s['grade'] }}</td></tr>@endforeach
<tr><th>Total</th><th class="num">{{ $exam['total'] }}</th><th class="num">{{ $exam['full_total'] }}</th><th></th></tr>
</tbody></table>
@empty<p class="muted">No published results yet.</p>@endforelse

@if($mode === 'file')
<h2>Attendance</h2>
<p>Present {{ $attendance['present'] }} · Late {{ $attendance['late'] }} · Absent {{ $attendance['absent'] }} · Leave {{ $attendance['leave'] }}@if($attendance['percent'] !== null) · {{ $attendance['percent'] }}%@endif</p>
<h2>Fees</h2>
<p>Billed {{ number_format($fees['billed']) }} · Paid {{ number_format($fees['paid']) }} · Due {{ number_format($fees['due']) }}</p>
<table class="grid"><thead><tr><th>Invoice</th><th>Fee</th><th>Due date</th><th class="num">Payable</th><th class="num">Paid</th><th>Status</th></tr></thead><tbody>
@foreach($fees['invoices'] as $i)<tr><td>{{ $i['invoice_no'] }}</td><td>{{ $i['label'] }}</td><td>{{ $i['due_on'] }}</td><td class="num">{{ number_format($i['payable']) }}</td><td class="num">{{ number_format($i['paid']) }}</td><td>{{ $i['status'] }}</td></tr>@endforeach
</tbody></table>
<h2>Certificates</h2>
@forelse($certificates as $c)<div>{{ $c['number'] }} — {{ $c['title'] }} ({{ $c['issued_on'] }})</div>@empty<p class="muted">None issued.</p>@endforelse
<h2>Library</h2>
@forelse($loans as $l)<div>{{ $l['title'] }} — issued {{ $l['issued_on'] }} {{ $l['returned_on'] ? 'returned '.$l['returned_on'] : '('.$l['status'].')' }}</div>@empty<p class="muted">No loans.</p>@endforelse
<h2>Notes &amp; conduct</h2>
@forelse($notes as $n)<div><strong>{{ ucfirst($n['kind']) }}</strong> · {{ $n['occurred_on'] }} · {{ $n['title'] }} @if($n['details'])— {{ $n['details'] }}@endif @if($n['action_taken'])<em>({{ $n['action_taken'] }})</em>@endif</div>@empty<p class="muted">No notes.</p>@endforelse
@endif
@endsection
