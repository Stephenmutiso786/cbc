<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 landscape; margin: 8mm; }
        * { box-sizing: border-box; }
        body { color: #172033; font-family: DejaVu Sans, sans-serif; font-size: 7.5px; }
        .header { display: table; width: 100%; padding-bottom: 6px; border-bottom: 3px solid #166534; }
        .logo-cell, .school-cell { display: table-cell; vertical-align: middle; }
        .logo-cell { width: 58px; }
        .logo { width: 48px; height: 48px; object-fit: contain; }
        .school { color: #166534; font-size: 15px; font-weight: bold; }
        .details { margin-top: 2px; color: #5b6472; font-size: 7.5px; }
        .title { margin: 8px 0 2px; color: #166534; font-size: 14px; font-weight: bold; text-align: center; text-transform: uppercase; }
        .subtitle { margin-bottom: 7px; color: #5b6472; text-align: center; }
        .meta { display: table; width: 100%; margin-bottom: 7px; border: 1px solid #b9c2ce; }
        .meta-cell { display: table-cell; width: 33.33%; padding: 4px; border-right: 1px solid #b9c2ce; }
        .meta-cell:last-child { border-right: 0; }
        .label { display: block; margin-bottom: 1px; color: #6b7280; font-size: 6.5px; text-transform: uppercase; }
        .value { font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        th { color: #fff; background: #166534; font-size: 6.5px; text-align: left; }
        th, td { padding: 3px; border: 1px solid #b9c2ce; vertical-align: middle; }
        tbody tr:nth-child(even) { background: #f0fdf4; }
        .num { text-align: center; }
        .summary { display: table; width: 100%; margin-top: 7px; }
        .summary-cell { display: table-cell; width: 50%; padding: 5px; border: 1px solid #b9c2ce; vertical-align: top; }
        .summary-cell + .summary-cell { border-left: 0; }
        .summary-title { margin: 0 0 4px; color: #166534; font-size: 8px; font-weight: bold; }
        .bar-row { display: table; width: 100%; margin: 2px 0; }
        .bar-label, .bar-track, .bar-value { display: table-cell; vertical-align: middle; }
        .bar-label { width: 120px; white-space: nowrap; }
        .bar-track { height: 6px; background: #e5e7eb; }
        .bar { height: 6px; background: #16a34a; }
        .bar-value { width: 36px; text-align: right; }
        .note { margin-top: 6px; color: #166534; }
        .footer { display: table; width: 100%; margin-top: 10px; }
        .signature { display: table-cell; width: 50%; padding-top: 4px; border-top: 1px solid #374151; color: #5b6472; font-size: 7.5px; }
    </style>
</head>
<body>
@php($logo = config('school.logo_data'))
<div class="header">
    <div class="logo-cell">
        @if(is_string($logo) && str_starts_with($logo, 'data:image/'))<img class="logo" src="{{ $logo }}" alt="School logo">@endif
    </div>
    <div class="school-cell">
        <div class="school">{{ config('school.name') }}</div>
        <div class="details">{{ config('school.address') }} | {{ config('school.phone') }} | {{ config('school.email') }}</div>
        <div class="details">{{ config('school.motto') }}</div>
    </div>
</div>

<div class="title">Examination Merit List</div>
<div class="subtitle">{{ $exam->typeLabel() }} - {{ $exam->name }}</div>
<div class="meta">
    <div class="meta-cell"><span class="label">Class / grade</span><span class="value">{{ $exam->schoolClass?->name ?? $exam->grade_level }}</span></div>
    <div class="meta-cell"><span class="label">Academic year</span><span class="value">{{ $exam->academic_year }}</span></div>
    <div class="meta-cell"><span class="label">Term</span><span class="value">Term {{ $exam->term }}</span></div>
</div>

<table>
    <thead>
    <tr>
        <th class="num">Position</th><th>Admission number</th><th>Learner name</th>
        @foreach($subjects as $subject)<th class="num">{{ $subject['name'] }}<br>Marks</th>@endforeach
        <th class="num">Total</th><th class="num">Mean</th><th class="num">Overall grade</th>
    </tr>
    </thead>
    <tbody>
    @foreach($rows as $row)
        <tr>
            <td class="num"><strong>{{ $row['position'] }}</strong></td>
            <td>{{ $row['learner']['admission_number'] ?: '-' }}</td>
            <td>{{ $row['learner']['name'] }}</td>
            @foreach($subjects as $subject)
                @php($score = $row['subject_scores'][$subject['id']] ?? null)
                <td class="num">{{ $score && $score['marks'] !== null ? $score['marks'].' / '.$score['total'] : 'NS' }}</td>
            @endforeach
            <td class="num">{{ rtrim(rtrim(number_format($row['total_obtained'], 2, '.', ''), '0'), '.') }} / {{ rtrim(rtrim(number_format($row['total_possible'], 2, '.', ''), '0'), '.') }}</td>
            <td class="num">{{ number_format($row['percentage'], 1) }}%</td>
            <td class="num">{{ $row['grade'] }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<div class="summary">
    <div class="summary-cell">
        <div class="summary-title">Best 5 learners</div>
        <table>
            <thead><tr><th>Position</th><th>Learner</th><th class="num">Mean</th><th class="num">Grade</th></tr></thead>
            <tbody>
            @foreach($topFive as $row)<tr><td>{{ $row['position'] }}</td><td>{{ $row['learner']['name'] }}</td><td class="num">{{ number_format($row['percentage'], 1) }}%</td><td class="num">{{ $row['grade'] }}</td></tr>@endforeach
            </tbody>
        </table>
    </div>
    <div class="summary-cell">
        <div class="summary-title">Subject performance means</div>
        @foreach($subjectMeans as $subject)
            <div class="bar-row"><div class="bar-label">{{ $subject['name'] }}</div><div class="bar-track"><div class="bar" style="width:{{ min(100, max(0, (float) $subject['mean'])) }}%"></div></div><div class="bar-value">{{ number_format($subject['mean'], 1) }}%</div></div>
        @endforeach
    </div>
</div>

<div class="note">Overall mean is calculated from total marks obtained divided by total possible marks across all {{ count($subjects) }} subjects. NS means the learner did not sit that subject.</div>
<div class="footer"><div class="signature">Prepared by Admin, Kyandulu</div><div class="signature">Headteacher signature and date</div></div>
</body>
</html>
