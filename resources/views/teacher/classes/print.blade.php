<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $schoolClass->grade_level }} - {{ $schoolClass->name }} class list</title>
    <style>
        @page { size: A4 portrait; margin: 14mm; }
        body { color: #111827; font-family: Arial, sans-serif; font-size: 12px; margin: 0; }
        .header { border-bottom: 2px solid #166534; margin-bottom: 16px; padding-bottom: 10px; text-align: center; }
        h1 { color: #166534; font-size: 20px; margin: 0 0 4px; }
        h2 { font-size: 15px; margin: 0; }
        .meta { display: flex; justify-content: space-between; margin: 12px 0; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #9ca3af; padding: 7px 6px; text-align: left; }
        th { background: #f0fdf4; color: #14532d; font-size: 10px; text-transform: uppercase; }
        .number { width: 34px; text-align: center; }
        .admission { width: 110px; }
        .marks { width: 85px; }
        .remarks { width: 160px; }
        .footer { color: #6b7280; margin-top: 16px; text-align: center; }
        .actions { margin-bottom: 14px; text-align: right; }
        .actions button { background: #15803d; border: 0; border-radius: 5px; color: #fff; cursor: pointer; padding: 8px 14px; }
        @media print { .actions { display: none; } }
    </style>
</head>
<body>
    <div class="actions"><button type="button" onclick="window.print()">Print class list</button></div>
    <header class="header">
        <h1>{{ config('school.name') }}</h1>
        <h2>{{ $schoolClass->grade_level }} - {{ $schoolClass->name }} Class List</h2>
    </header>
    <div class="meta"><span>Academic year: <strong>{{ $schoolClass->academic_year }}</strong></span><span>Teacher: <strong>{{ $schoolClass->classTeacher?->full_name ?? '________________' }}</strong></span><span>Date: __________________</span></div>
    <table>
        <thead><tr><th class="number">No.</th><th class="admission">Admission no.</th><th>Learner name</th><th class="marks">Marks</th><th class="remarks">Remarks</th></tr></thead>
        <tbody>
            @forelse($learners as $number => $learner)
                <tr><td class="number">{{ $number + 1 }}</td><td>{{ $learner->admission_number }}</td><td>{{ $learner->full_name }}</td><td></td><td></td></tr>
            @empty
                <tr><td colspan="5" style="text-align:center">No active learners in this class.</td></tr>
            @endforelse
        </tbody>
    </table>
    <p class="footer">Printed from {{ config('school.name') }} school management system.</p>
</body>
</html>
