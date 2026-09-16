@extends('layouts.app')

@section('header', 'Admin Dashboard')

@section('content')
<div class="mx-auto max-w-3xl rounded-xl border border-amber-200 bg-amber-50 p-6 text-amber-950 shadow-sm">
    <h2 class="text-xl font-bold">Dashboard is recovering</h2>
    <p class="mt-2 text-sm">Your account and school data are available. An optional dashboard widget did not load, so the system kept this safe dashboard open instead of showing a 500 error.</p>
    <p class="mt-2 text-xs text-amber-800">Reference: {{ $exceptionId }}</p>
    <div class="mt-5 flex flex-wrap gap-3">
        <a href="{{ route('admin.students.index') }}" class="rounded-lg bg-green-700 px-4 py-2 text-sm font-semibold text-white">Learners</a>
        <a href="{{ route('admin.classes.index') }}" class="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-green-800 ring-1 ring-green-300">Classes</a>
        <a href="{{ route('admin.settings.index') }}" class="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-green-800 ring-1 ring-green-300">School settings</a>
    </div>
</div>
@endsection
