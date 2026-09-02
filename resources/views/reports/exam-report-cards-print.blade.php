<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ config('school.name') }} report cards</title>
    <style>
        @page { size: A4 portrait; margin: 12mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #172033; font-family: Arial, sans-serif; font-size: 11px; }
        .toolbar { padding: 12px; text-align: center; background: #f3f4f6; }
        .toolbar button { padding: 9px 18px; color: #fff; background: #166534; border: 0; border-radius: 6px; cursor: pointer; }
        .card { min-height: 270mm; position: relative; }
        .card + .card { page-break-before: always; }
        .header { display: flex; align-items: center; gap: 14px; padding-bottom: 10px; border-bottom: 3px solid #166534; }
        .logo { width: 58px; height: 58px; object-fit: contain; }
        .school { color: #166534; font-size: 18px; font-weight: 700; }
        .details { margin-top: 3px; color: #5b6472; font-size: 9px; }
        h1 { margin: 18px 0 12px; color: #166534; font-size: 16px; text-align: center; text-transform: uppercase; }
        .meta, .learner, .summary { display: grid; border: 1px solid #b9c2ce; }
        .meta { grid-template-columns: repeat(3, 1fr); margin-bottom: 14px; }
        .learner { grid-template-columns: 2fr 1fr 1fr; border-bottom: 0; }
        .summary { grid-template-columns: repeat(4, 1fr); margin-top: 14px; }
        .meta > div, .learner > div, .summary > div { padding: 8px; border-right: 1px solid #b9c2ce; }
        .meta > div:last-child, .learner > div:last-child, .summary > div:last-child { border-right: 0; }
        .label { display: block; margin-bottom: 3px; color: #6b7280; font-size: 8px; text-transform: uppercase; }
        .value { font-weight: 700; }
        table { width: 100%; border-collapse: collapse; }
        th { color: #fff; background: #166534; text-align: left; font-size: 9px; }
        th, td { padding: 9px; border: 1px solid #b9c2ce; }
        .num { text-align: center; }
        .big { display: block; margin-top: 3px; color: #166534; font-size: 16px; font-weight: 700; text-align: center; }
        .remark { min-height: 48px; margin-top: 18px; padding: 10px; border: 1px solid #b9c2ce; }
        .signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 45px; margin-top: 35px; }
        .signature { padding-top: 5px; border-top: 1px solid #374151; color: #5b6472; font-size: 9px; }
        .signature img { display: block; width: 150px; height: 34px; margin-bottom: 4px; object-fit: contain; object-position: left bottom; }
        .verification { position: absolute; right: 0; bottom: 20px; left: 0; color: #5b6472; font-size: 7px; text-align: center; }
        .verification svg { display: block; width: 72px; height: 72px; margin: 0 auto 2px; }
        .footer { position: absolute; bottom: 0; width: 100%; padding-top: 6px; border-top: 1px solid #d1d5db; color: #6b7280; font-size: 8px; text-align: center; }
        @media print { .toolbar { display: none; } }
    </style>
</head>
<body>
@php($logoUrl = is_string(config('school.logo_data')) && str_starts_with(config('school.logo_data'), 'data:image/') ? route('school.logo') : null)
<div class="toolbar"><button type="button" onclick="window.print()">Print all report cards</button></div>
<script>window.addEventListener('load', function () { window.print(); });</script>
@foreach($cards as $card)
    <section class="card">
        <div class="header">
            @if($logoUrl) <img class="logo" src="{{ $logoUrl }}" alt="School logo"> @endif
            <div><div class="school">{{ config('school.name') }}</div><div class="details">{{ config('school.address') }} | {{ config('school.phone') }} | {{ config('school.email') }}</div><div class="details">{{ config('school.motto') }}</div></div>
        </div>
        <h1>Examination Results Report Card</h1>
        <div class="meta"><div><span class="label">Exam</span><span class="value">{{ $exam->typeLabel() }} - {{ $exam->name }}</span></div><div><span class="label">Academic year</span><span class="value">{{ $exam->academic_year }}</span></div><div><span class="label">Term</span><span class="value">Term {{ $exam->term }}</span></div></div>
        <div class="learner"><div><span class="label">Learner</span><span class="value">{{ $card['learner']['name'] }}</span></div><div><span class="label">Admission number</span><span class="value">{{ $card['learner']['admission_number'] }}</span></div><div><span class="label">Class</span><span class="value">{{ $card['learner']['class'] }}</span></div></div>
        <table><thead><tr><th>Learning area</th><th class="num">Rubric</th><th class="num">Points</th><th class="num">Grade</th><th>Teacher remarks</th></tr></thead><tbody>
            @foreach($card['subjects'] as $subject)<tr><td>{{ $subject['name'] }}</td><td class="num">{{ $subject['rubric'] }}</td><td class="num">{{ $subject['points'] }}</td><td class="num">{{ $subject['grade'] }}</td><td>{{ $subject['remarks'] }}</td></tr>@endforeach
        </tbody></table>
        <div class="summary"><div><span class="label">Subjects</span><span class="big">{{ $card['subject_count'] }}</span></div><div><span class="label">Total marks</span><span class="big">{{ rtrim(rtrim(number_format($card['total_obtained'], 2, '.', ''), '0'), '.') }} / {{ rtrim(rtrim(number_format($card['total_possible'], 2, '.', ''), '0'), '.') }}</span></div><div><span class="label">Mean</span><span class="big">{{ $card['overall_percentage'] }}%</span></div><div><span class="label">Overall grade</span><span class="big">{{ $card['overall_grade'] }}</span></div></div>
        <div class="remark"><span class="label">Official comment</span>Keep working consistently and use the teacher's feedback to strengthen the next competency.</div>
        <div class="signatures"><div class="signature">@if($classTeacherSignatureUrl)<img src="{{ $classTeacherSignatureUrl }}" alt="Class teacher signature">@endif{{ $classTeacherName ? $classTeacherName . ' - ' : '' }}Class teacher signature and date</div><div class="signature">@if($officialSignatureUrl)<img src="{{ $officialSignatureUrl }}" alt="Headteacher signature">@endif@if($officialStampUrl)<img src="{{ $officialStampUrl }}" alt="Official school stamp" style="width:70px;height:34px;object-position:left center">@endifHeadteacher signature and date</div></div>
        <div class="verification">{!! $card['verificationQr'] !!}<div>Scan to verify this report card</div></div>
        <div class="footer">{{ config('school.name') }} | Official examination result | {{ now()->format('d M Y') }}</div>
    </section>
@endforeach
</body>
</html>
