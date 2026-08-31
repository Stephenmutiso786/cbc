@extends('layouts.teacher')
@section('header', 'Teacher Dashboard')
@section('content')
<div class="card p-6">@php($teacher = auth()->user()->staffMember)<h2 class="text-xl font-bold text-gray-800 mb-1">Teacher Dashboard</h2><p class="text-sm text-gray-500 mb-6">Welcome, {{ $teacher?->full_name ?: auth()->user()->name }}.</p><div class="grid grid-cols-1 gap-4 md:grid-cols-3"><div class="rounded-xl bg-blue-50 p-5"><p class="text-sm text-gray-500">Assigned classes</p><p class="mt-2 text-2xl font-bold">{{ $teacher?->classes()->count() ?? 0 }}</p></div><div class="rounded-xl bg-green-50 p-5"><p class="text-sm text-gray-500">Assessments entered</p><p class="mt-2 text-2xl font-bold">{{ $teacher?->assessments()->count() ?? 0 }}</p></div><div class="rounded-xl bg-yellow-50 p-5"><p class="text-sm text-gray-500">Lessons scheduled</p><p class="mt-2 text-2xl font-bold">{{ $teacher?->timetableSlots()->count() ?? 0 }}</p></div></div></div>
@endsection
