<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; margin: 0; }
    .grid { display: flex; flex-wrap: wrap; }
    .card {
        width: 260px; height: 160px; border: 1px solid #ccc; border-radius: 10px;
        margin: 6px; padding: 10px; box-sizing: border-box; page-break-inside: avoid;
        display: flex;
    }
    .photo { width: 70px; height: 80px; background: #eee; border-radius: 6px; object-fit: cover; margin-right: 10px; }
    .details { flex: 1; }
    .school-name { font-size: 10px; font-weight: bold; color: #1a5c2a; text-transform: uppercase; }
    .name { font-size: 13px; font-weight: bold; margin-top: 4px; }
    .meta { font-size: 10px; color: #444; margin-top: 2px; }
    .badge { font-size: 8px; color: #fff; background: #1a5c2a; border-radius: 4px; padding: 1px 5px; display: inline-block; margin-top: 6px; }
</style>
</head>
<body>
<div class="grid">
@foreach ($records as $record)
    <div class="card">
        @if ($record->photo_path && file_exists(storage_path('app/public/' . $record->photo_path)))
            <img class="photo" src="{{ storage_path('app/public/' . $record->photo_path) }}">
        @else
            <div class="photo"></div>
        @endif
        <div class="details">
            <div class="school-name">{{ config('school.name') }}</div>
            <div class="name">{{ $record->full_name ?? trim(($record->first_name ?? '') . ' ' . ($record->last_name ?? '')) }}</div>
            @if ($type === 'learners')
                <div class="meta">Adm No: {{ $record->admission_number }}</div>
                <div class="meta">Class: {{ $record->schoolClass?->name }}</div>
            @else
                <div class="meta">Staff No: {{ $record->staff_number }}</div>
                <div class="meta">{{ ucfirst(str_replace('_', ' ', $record->employment_type ?? '')) }}</div>
            @endif
            <div class="badge">{{ $type === 'learners' ? 'LEARNER' : 'STAFF' }}</div>
        </div>
    </div>
@endforeach
</div>
</body>
</html>

