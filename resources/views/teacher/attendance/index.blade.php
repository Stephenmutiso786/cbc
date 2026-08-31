@extends('layouts.teacher')
@section('header', 'Attendance')
@section('content')
<div class="card p-6">@php($staffId = auth()->user()->staffMember?->id) @php($records = $staffId ? \App\Models\Attendance::where('recorded_by', $staffId)->latest('date')->take(50)->get() : collect())<h2 class="text-xl font-bold text-gray-800 mb-5">Attendance records</h2><p class="mb-4 text-sm text-gray-500">{{ $records->count() }} recent records entered by you.</p><div class="space-y-2">@forelse($records as $record)<div class="flex justify-between rounded-lg border p-3 text-sm"><span>{{ $record->date?->format('d M Y') }} · Learner #{{ $record->learner_id }}</span><span class="capitalize">{{ $record->status }}</span></div>@empty<p class="py-8 text-center text-gray-400">No attendance records yet.</p>@endforelse</div></div>
@endsection
