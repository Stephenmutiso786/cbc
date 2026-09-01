@extends('layouts.admin')
@section('header', 'Analytics')
@section('content')
<div class="space-y-6">
    <div><h2 class="text-2xl font-bold text-gray-900">School analytics</h2><p class="text-sm text-gray-500">Live exam performance, rankings, subject summaries, and trends.</p></div>
    <form method="GET" class="card flex flex-wrap items-end gap-4 p-5">
        <label class="block min-w-56 text-sm text-gray-700">Exam<select name="exam_id" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2"><option value="">Latest exam</option>@foreach($exams as $item)<option value="{{ $item->id }}" @selected($exam?->id === $item->id)>{{ $item->name }} - {{ $item->learningArea?->name }}</option>@endforeach</select></label>
        <label class="block min-w-56 text-sm text-gray-700">Class<select name="class_id" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2"><option value="">First active class</option>@foreach($classes as $item)<option value="{{ $item->id }}" @selected($class?->id === $item->id)>{{ $item->name }}</option>@endforeach</select></label>
        <button class="rounded-lg bg-green-700 px-5 py-2 text-sm font-semibold text-white">View analytics</button>
        @if($exam && $class)<a href="{{ route('admin.reports.export', ['exam_id' => $exam->id, 'class_id' => $class->id]) }}" class="rounded-lg border border-green-700 px-5 py-2 text-sm font-semibold text-green-700">Export CSV</a>@endif
    </form>
    @if($exam && $class)
    <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
        <div class="rounded-xl bg-green-50 p-5"><p class="text-sm text-gray-500">Exam</p><p class="mt-1 font-bold text-gray-900">{{ $exam->name }}</p></div>
        <div class="rounded-xl bg-blue-50 p-5"><p class="text-sm text-gray-500">Class</p><p class="mt-1 font-bold text-gray-900">{{ $class->name }}</p></div>
        <div class="rounded-xl bg-yellow-50 p-5"><p class="text-sm text-gray-500">Learners with marks</p><p class="mt-1 text-2xl font-bold text-gray-900">{{ $ranking->count() }}</p></div>
        <div class="rounded-xl bg-purple-50 p-5"><p class="text-sm text-gray-500">Class mean</p><p class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($ranking->avg('percentage') ?: 0, 2) }}%</p></div>
    </div>
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <section class="card overflow-hidden"><div class="border-b p-5"><h3 class="font-bold text-gray-900">Learner ranking</h3><p class="text-xs text-gray-500">Ranked by mean percentage with position assigned from live results.</p></div><div class="overflow-x-auto"><table class="min-w-full"><thead class="bg-gray-50 text-left text-xs uppercase text-gray-500"><tr><th class="px-4 py-3">Pos.</th><th class="px-4 py-3">Learner</th><th class="px-4 py-3">Subjects</th><th class="px-4 py-3">Mean</th><th class="px-4 py-3">Grade</th></tr></thead><tbody class="divide-y">@forelse($ranking as $row)<tr><td class="px-4 py-3 text-sm font-bold">{{ $row['position'] }}</td><td class="px-4 py-3 text-sm font-semibold"><a class="text-green-700 hover:underline" href="{{ route('admin.reports.student', ['learner' => $row['learner']->id, 'exam_id' => $exam->id]) }}">{{ $row['learner']?->full_name }}</a></td><td class="px-4 py-3 text-sm">{{ $row['subjects'] }}</td><td class="px-4 py-3 text-sm">{{ number_format($row['percentage'], 2) }}%</td><td class="px-4 py-3 text-sm font-bold">{{ $row['grade'] }}</td></tr>@empty<tr><td colspan="5" class="p-8 text-center text-sm text-gray-400">No marks recorded for this class and exam.</td></tr>@endforelse</tbody></table></div></section>
        <section class="card overflow-hidden"><div class="border-b p-5"><h3 class="font-bold text-gray-900">Subject performance</h3><p class="text-xs text-gray-500">Mean, highest, lowest, and grade calculated from saved marks.</p></div><div class="overflow-x-auto"><table class="min-w-full"><thead class="bg-gray-50 text-left text-xs uppercase text-gray-500"><tr><th class="px-4 py-3">Subject</th><th class="px-4 py-3">Entries</th><th class="px-4 py-3">Mean</th><th class="px-4 py-3">High / low</th><th class="px-4 py-3">Grade</th></tr></thead><tbody class="divide-y">@forelse($subjectBreakdown as $row)<tr><td class="px-4 py-3 text-sm font-semibold">{{ $row['subject'] }}</td><td class="px-4 py-3 text-sm">{{ $row['entries'] }}</td><td class="px-4 py-3 text-sm">{{ number_format($row['mean'], 2) }}%</td><td class="px-4 py-3 text-sm">{{ number_format($row['highest'], 2) }} / {{ number_format($row['lowest'], 2) }}</td><td class="px-4 py-3 text-sm font-bold">{{ $row['grade'] }}</td></tr>@empty<tr><td colspan="5" class="p-8 text-center text-sm text-gray-400">No subject data available.</td></tr>@endforelse</tbody></table></div></section>
    </div>
    <section class="card p-5"><h3 class="font-bold text-gray-900">Class mean trend</h3><div class="mt-4 flex flex-wrap items-end gap-4">@forelse($trend as $item)<div class="w-24 text-center"><div class="mx-auto flex h-32 items-end justify-center"><div class="w-12 rounded-t bg-green-600" style="height: {{ max(8, min(100, $item['mean'])) }}%"></div></div><p class="mt-2 truncate text-xs font-semibold">{{ $item['exam'] }}</p><p class="text-xs text-gray-500">{{ number_format($item['mean'], 1) }}%</p></div>@empty<p class="text-sm text-gray-400">No trend data available.</p>@endforelse</div></section>
    @else
    <div class="card p-10 text-center text-sm text-gray-500">Create an exam and enter marks to activate analytics.</div>
    @endif
</div>
@endsection
