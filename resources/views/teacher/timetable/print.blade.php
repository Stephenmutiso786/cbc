<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $staff->full_name }} timetable</title>
    <style>
        @page { size: landscape; margin: 10mm; }
        body { font-family: Arial, sans-serif; color: #111827; margin: 0; }
        .page { padding: 6mm; }
        .header { text-align: center; margin-bottom: 12px; }
        .header h1 { margin: 0; font-size: 20px; color: #166534; }
        .header p { margin: 4px 0 0; font-size: 12px; color: #4b5563; }
        table { border-collapse: collapse; width: 100%; table-layout: fixed; }
        th, td { border: 1px solid #9ca3af; padding: 7px 6px; vertical-align: top; font-size: 11px; }
        th { background: #eff6ff; color: #1d4ed8; text-transform: uppercase; }
        .time { width: 90px; white-space: nowrap; }
        .slot { min-height: 42px; }
        .subject { font-weight: 700; color: #111827; }
        .class-name, .venue { margin-top: 2px; color: #4b5563; }
    </style>
</head>
<body>
    <div class="page">
        <div class="header">
            <h1>{{ config('school.name') }}</h1>
            <p>{{ $staff->full_name }} | Academic year {{ config('school.academic_year') }} | Term {{ config('school.current_term') }}</p>
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
                @foreach($times as [$start, $end])
                    <tr>
                        <th class="time">{{ $start }} - {{ $end }}</th>
                        @foreach($days as $day)
                            @php($slot = $slots->filter(fn ($item) => $item->day_of_week === $day && substr($item->start_time, 0, 5) === $start)->first())
                            <td class="slot">
                                @if($slot)
                                    <div class="subject">{{ $slot->learningArea?->name }}</div>
                                    <div class="class-name">{{ $slot->schoolClass?->name }}</div>
                                    @if($slot->venue)<div class="venue">{{ $slot->venue }}</div>@endif
                                @else
                                    &nbsp;
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</body>
</html>