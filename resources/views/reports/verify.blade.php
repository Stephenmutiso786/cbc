<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Report verification | {{ config('school.name') }}</title>
    <style>
        body{margin:0;background:#f3f6f4;color:#172033;font:16px Arial,sans-serif}.wrap{max-width:680px;margin:32px auto;padding:0 16px}.card{background:#fff;border:1px solid #d6ded9;border-radius:14px;box-shadow:0 8px 24px #173b2712;padding:28px}.valid{color:#166534;font-weight:700}.school{color:#166534;font-size:22px;font-weight:700}.muted{color:#667085;font-size:13px}.meta{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin:24px 0}.meta div{border:1px solid #d6ded9;border-radius:8px;padding:12px}.label{display:block;color:#667085;font-size:11px;text-transform:uppercase;margin-bottom:5px}table{width:100%;border-collapse:collapse}th,td{padding:10px;border-bottom:1px solid #e5e7eb;text-align:left}th{color:#fff;background:#166534;font-size:12px}.notice{margin-top:22px;padding:12px;border-radius:8px;background:#ecfdf3;color:#166534;font-size:13px}@media(max-width:500px){.meta{grid-template-columns:1fr}.card{padding:20px}}
    </style>
</head>
<body>
<main class="wrap"><section class="card">
    <div class="valid">Verified official report card</div>
    <h1 class="school">{{ config('school.name') }}</h1>
    <p class="muted">This report was verified from the school system. The QR code and report details are authentic.</p>
    <div class="meta">
        <div><span class="label">Learner</span><strong>{{ $learner->full_name }}</strong></div>
        <div><span class="label">Admission number</span><strong>{{ $learner->admission_number }}</strong></div>
        <div><span class="label">Exam</span><strong>{{ $exam->typeLabel() }} - {{ $exam->name }}</strong></div>
        <div><span class="label">Class and term</span><strong>{{ $exam->schoolClass?->name ?? $exam->grade_level }} | Term {{ $exam->term }} {{ $exam->academic_year }}</strong></div>
    </div>
    <table><thead><tr><th>Learning area</th><th>Rubric</th><th>Points</th><th>Grade</th></tr></thead><tbody>
    @foreach($results as $result)<tr><td>{{ $result->exam?->learningArea?->name ?? 'Learning area' }}</td><td>{{ $result->rubric_level?->value ?? '-' }}</td><td>{{ $result->rubric_level?->numericValue() ?? '-' }}</td><td>{{ $result->grade ?: '-' }}</td></tr>@endforeach
    </tbody></table>
    <div class="notice">Verified on {{ $verifiedAt->format('d M Y H:i') }}. This page does not expose marks that were not submitted.</div>
</section></main>
</body>
</html>
