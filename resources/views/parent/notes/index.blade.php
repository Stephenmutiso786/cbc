@extends('layouts.parent')
@section('header', 'Notes')
@section('content')
<div class="card p-6">@php($notes = \App\Models\LearningNote::published()->with('learningArea')->latest()->get())<h2 class="text-xl font-bold text-gray-800 mb-5">Learning notes</h2><div class="space-y-3">@forelse($notes as $note)<div class="rounded-lg border p-4"><p class="font-semibold">{{ $note->title }}</p><p class="text-sm text-gray-500">{{ $note->learningArea?->name }} · {{ $note->grade_level }} · {{ $note->term }}</p></div>@empty<p class="py-8 text-center text-gray-400">No published notes found.</p>@endforelse</div></div>
@endsection
