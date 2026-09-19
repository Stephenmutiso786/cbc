<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('school.name') }} timetable</title>
    <style>
        @page { size: landscape; margin: 10mm; }
        body { font-family: Arial, sans-serif; color: #111827; margin: 0; }
        .page { padding: 6mm; }
        .header { text-align: center; margin-bottom: 10px; }
        .header h1 { margin: 0; font-size: 20px; color: #166534; }
        .header p { margin: 4px 0 0; font-size: 12px; color: #4b5563; }
        .timetable { margin-top: 12px; page-break-after: always; }
        .timetable:last-child { page-break-after: auto; }
        .title { margin: 0 0 8px; font-size: 15px; color: #111827; }
        table { border-collapse: collapse; width: 100%; table-layout: fixed; }
        th, td { border: 1px solid #9ca3af; padding: 7px 6px; vertical-align: top; font-size: 11px; }
        th { background: #f0fdf4; color: #14532d; text-transform: uppercase; }
        .time { width: 90px; white-space: nowrap; }
        .slot { min-height: 42px; }
        .subject { font-weight: 700; color: #111827; }
        .teacher, .venue { margin-top: 2px; color: #4b5563; }
        .empty { color: #d1d5db; }
        .meta { display: flex; justify-content: space-between; gap: 12px; margin-bottom: 10px; font-size: 11px; color: #374151; }
        .print-note { display: none; }
        @media print { .print-note { display: none; } }
    </style>
</head>
<body>
    <div class="page">
        <div class="header">
            <h1>{{ config('school.name') }}</h1>
            <p>Academic year {{ $academicYear }} | Term {{ $term }} | School timetable</p>
        </div>

        @forelse($classTimetables as $entry)
            @php($schoolClass = $entry['class'])
            <section class="timetable">
                <h2 class="title">{{ $schoolClass->grade_level }}{{ $schoolClass->name !== $schoolClass->grade_level ? ' - ' . $schoolClass->name : '' }}</h2>
                <div class="meta">
                    <span>Class teacher: <strong>{{ $schoolClass->classTeacher?->full_name ?? '________________' }}</strong></span>
                    <span>Template: <strong>{{ $entry['template']['label'] }}</strong></span>
                    <span>Printed: {{ now()->format('d M Y') }}</span>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th class="time">Time</th>
                            @foreach($days as $day)
                                <th>{{ ucfirst($day) }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($entry['blocks'] as $block)
                            @if($block['is_break'])
                            <tr><th class="time">{{ $block['start'] }} - {{ $block['end'] }}</th><td colspan="5" style="text-align:center;background:#fffbeb;font-weight:700">{{ $block['label'] }}</td></tr>
                            @else
                            <tr>
                                <th class="time">{{ $block['start'] }} - {{ $block['end'] }}</th>
                                @foreach($days as $day)
                                    @php($slot = $entry['slots']->get($day . '|' . $block['start']))
                                    <td class="slot {{ $slot ? '' : 'empty' }}">
                                        @if($slot)
                                            <div class="subject">{{ $slot->learningArea?->name }}</div>
                                            <div class="teacher">{{ $slot->teacher?->full_name }}</div>
                                            @if($slot->venue)<div class="venue">{{ $slot->venue }}</div>@endif
                                        @else
                                            &nbsp;
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </section>
        @empty
            <section class="timetable">
                <p>No timetable has been generated for the selected filters.</p>
            </section>
        @endforelse
    </div>
</body>
</html>
