<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ config('school.name') }} merit list</title>
    <style>
        @page { size: A4 landscape; margin: 10mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #172033; font-family: Arial, sans-serif; font-size: 8px; }
        .toolbar { padding: 12px; text-align: center; background: #f3f4f6; }
        .toolbar button { padding: 9px 18px; color: #fff; background: #166534; border: 0; border-radius: 6px; cursor: pointer; }
        .header { display: flex; align-items: center; gap: 10px; padding-bottom: 7px; border-bottom: 3px solid #166534; }
        .logo { width: 48px; height: 48px; object-fit: contain; }
        .school { color: #166534; font-size: 16px; font-weight: 700; }
        .details { margin-top: 2px; color: #5b6472; font-size: 8px; }
        h1 { margin: 10px 0 2px; color: #166534; font-size: 15px; text-align: center; text-transform: uppercase; }
        .subtitle { margin-bottom: 9px; color: #5b6472; text-align: center; }
        .meta { display: grid; grid-template-columns: 2fr 1fr 1fr; margin-bottom: 9px; border: 1px solid #b9c2ce; }
        .meta div { padding: 5px; border-right: 1px solid #b9c2ce; }
        .meta div:last-child { border-right: 0; }
        .label { display: block; margin-bottom: 2px; color: #6b7280; font-size: 7px; text-transform: uppercase; }
        .value { font-weight: 700; }
        table { width: 100%; border-collapse: collapse; }
        th { color: #fff; background: #166534; text-align: left; font-size: 7px; }
        th, td { padding: 4px; border: 1px solid #b9c2ce; vertical-align: middle; }
        tbody tr:nth-child(even) { background: #f0fdf4; }
        .num { text-align: center; }
        .summary { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 10px; }
        .panel { padding: 6px; border: 1px solid #b9c2ce; }
        .panel h2 { margin: 0 0 5px; color: #166534; font-size: 9px; }
        .bar-row { display: flex; align-items: center; margin: 3px 0; }
        .bar-label { width: 125px; overflow: hidden; white-space: nowrap; }
        .bar-track { height: 7px; flex: 1; background: #e5e7eb; }
        .bar { height: 7px; background: #16a34a; }
        .bar-value { width: 38px; text-align: right; }
        .note { margin-top: 8px; color: #166534; }
        .footer { display: grid; grid-template-columns: 1fr 1fr; gap: 35px; margin-top: 13px; }
        .signature { padding-top: 4px; border-top: 1px solid #374151; color: #5b6472; font-size: 8px; }
        @media print { .toolbar { display: none; } }
    </style>
</head>
<body>
@php($logo = config('school.logo_data'))
<div class="toolbar"><button type="button" onclick="window.print()">Print merit list</button></div>
<script>window.addEventListener('load', function () { window.print(); });</script>
<div class="header">
    @if(is_string($logo) && str_starts_with($logo, 'data:image/')) <img class="logo" src="{{ $logo }}" alt="School logo"> @endif
    <div><div class="school">{{ config('school.name') }}</div><div class="details">{{ config('school.address') }} | {{ config('school.phone') }} | {{ config('school.email') }}</div><div class="details">{{ config('school.motto') }}</div></div>
</div>
<h1>Examination Merit List</h1>
<div class="subtitle">{{ $exam->typeLabel() }} - {{ $exam->name }}</div>
<div class="meta"><div><span class="label">Class / grade</span><span class="value">{{ $exam->schoolClass?->name ?? $exam->grade_level }}</span></div><div><span class="label">Academic year</span><span class="value">{{ $exam->academic_year }}</span></div><div><span class="label">Term</span><span class="value">Term {{ $exam->term }}</span></div></div>
<table><thead><tr><th class="num">Position</th><th>Admission number</th><th>Learner name</th>@foreach($subjects as $subject)<th class="num">{{ $subject['name'] }}<br>Marks</th>@endforeach<th class="num">Total</th><th class="num">Mean</th><th class="num">Overall grade</th></tr></thead><tbody>
@foreach($rows as $row)<tr><td class="num"><strong>{{ $row['position'] }}</strong></td><td>{{ $row['learner']['admission_number'] ?: '-' }}</td><td>{{ $row['learner']['name'] }}</td>@foreach($subjects as $subject)@php($score = $row['subject_scores'][$subject['id']] ?? null)<td class="num">{{ $score && $score['marks'] !== null ? $score['marks'].' / '.$score['total'] : 'NS' }}</td>@endforeach<td class="num">{{ rtrim(rtrim(number_format($row['total_obtained'], 2, '.', ''), '0'), '.') }} / {{ rtrim(rtrim(number_format($row['total_possible'], 2, '.', ''), '0'), '.') }}</td><td class="num">{{ number_format($row['percentage'], 1) }}%</td><td class="num">{{ $row['grade'] }}</td></tr>@endforeach
</tbody></table>
<div class="summary"><div class="panel"><h2>Best 5 learners</h2><table><thead><tr><th>Position</th><th>Learner</th><th class="num">Mean</th><th class="num">Grade</th></tr></thead><tbody>@foreach($topFive as $row)<tr><td>{{ $row['position'] }}</td><td>{{ $row['learner']['name'] }}</td><td class="num">{{ number_format($row['percentage'], 1) }}%</td><td class="num">{{ $row['grade'] }}</td></tr>@endforeach</tbody></table></div><div class="panel"><h2>Subject performance means</h2>@foreach($subjectMeans as $subject)<div class="bar-row"><div class="bar-label">{{ $subject['name'] }}</div><div class="bar-track"><div class="bar" style="width:{{ min(100, max(0, (float) $subject['mean'])) }}%"></div></div><div class="bar-value">{{ number_format($subject['mean'], 1) }}%</div></div>@endforeach</div></div>
<div class="note">Overall mean is calculated from total marks obtained divided by total possible marks across all {{ count($subjects) }} subjects. NS means the learner did not sit that subject.</div>
<div class="footer"><div class="signature">Prepared by Admin, Kyandulu</div><div class="signature">Headteacher signature and date</div></div>
</body>
</html>
