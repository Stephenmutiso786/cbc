@extends('layouts.student')
@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <div><p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">{{ config('school.name') }}</p><h2 class="mt-1 text-2xl font-bold text-gray-900">Welcome, {{ $learner->first_name }}</h2><p class="text-sm text-gray-500">{{ $learner->full_name }} · {{ $learner->admission_number }} · {{ $learner->schoolClass?->name ?: $learner->grade_level }}</p></div>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-3"><div class="rounded-xl bg-white p-5 shadow-sm"><p class="text-sm text-gray-500">Published results</p><p class="mt-2 text-3xl font-bold text-emerald-700">{{ $resultCount }}</p></div><div class="rounded-xl bg-white p-5 shadow-sm"><p class="text-sm text-gray-500">Average percentage</p><p class="mt-2 text-3xl font-bold text-blue-700">{{ number_format($average, 1) }}%</p></div><div class="rounded-xl bg-white p-5 shadow-sm"><p class="text-sm text-gray-500">Learning notes</p><p class="mt-2 text-3xl font-bold text-amber-600">{{ $notesCount }}</p></div></div>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-3"><a href="{{ route('student.results') }}" class="rounded-xl bg-emerald-700 p-5 font-semibold text-white hover:bg-emerald-800">View my results</a><a href="{{ route('student.notes') }}" class="rounded-xl bg-blue-700 p-5 font-semibold text-white hover:bg-blue-800">Open learning notes</a><a href="{{ route('student.support') }}" class="rounded-xl bg-amber-600 p-5 font-semibold text-white hover:bg-amber-700">Get support</a></div>
</div>
@endsection
