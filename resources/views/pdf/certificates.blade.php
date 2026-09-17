<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; }
    .page { page-break-after: always; text-align: center; padding: 60px 40px; border: 6px double #1a5c2a; margin: 20px; }
    .page:last-child { page-break-after: auto; }
    .school-name { font-size: 20px; font-weight: bold; color: #1a5c2a; text-transform: uppercase; }
    .title { font-size: 30px; font-weight: bold; margin: 30px 0 10px; text-transform: uppercase; letter-spacing: 2px; }
    .subtitle { font-size: 13px; color: #666; }
    .name { font-size: 26px; font-weight: bold; margin: 25px 0; border-bottom: 2px solid #1a5c2a; display: inline-block; padding-bottom: 6px; }
    .body-text { font-size: 14px; color: #333; max-width: 500px; margin: 0 auto; line-height: 1.6; }
    .footer { margin-top: 60px; display: flex; justify-content: space-between; padding: 0 40px; font-size: 11px; color: #555; }
</style>
</head>
<body>
@foreach ($records as $record)
    <div class="page">
        <div class="school-name">{{ config('school.name') }}</div>
        <div class="title">
            @if ($certificateType === 'completion') Certificate of Completion
            @elseif ($certificateType === 'good_conduct') Certificate of Good Conduct
            @else Certificate of Transfer
            @endif
        </div>
        <div class="subtitle">This is to certify that</div>
        <div class="name">{{ $record->full_name ?? trim(($record->first_name ?? '') . ' ' . ($record->last_name ?? '')) }}</div>
        <div class="body-text">
            @if ($certificateType === 'completion')
                has successfully completed studies at {{ config('school.name') }} and is hereby awarded this certificate in recognition of their achievement.
            @elseif ($certificateType === 'good_conduct')
                was a learner of good conduct and character during their time at {{ config('school.name') }}.
            @else
                was a bona fide learner at {{ config('school.name') }} and is transferring in good standing.
            @endif
        </div>
        <div class="footer">
            <span>Date: {{ now()->format('d F Y') }}</span>
            <span>{{ $type === 'learners' ? 'Admission No: ' . $record->admission_number : 'Staff No: ' . $record->staff_number }}</span>
            <span>______________________<br>Head Teacher</span>
        </div>
    </div>
@endforeach
</body>
</html>

