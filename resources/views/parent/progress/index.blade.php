@extends('layouts.parent')
@section('header', 'Progress')
@section('content')
<div class="card p-6">@php($learnerIds = auth()->user()->guardian?->learners()->pluck('learners.id') ?? collect()) @php($assessments = \App\Models\Assessment::whereIn('learner_id', $learnerIds)->with(['learner','learningArea'])->latest()->get())<h2 class="text-xl font-bold text-gray-800 mb-5">Learner progress</h2><div class="space-y-3">@forelse($assessments as $assessment)<div class="flex justify-between rounded-lg border p-4 text-sm"><span>{{ $assessment->learner?->full_name }} · {{ $assessment->learningArea?->name }}</span><span>{{ $assessment->rubric_level?->value ?: 'Not graded' }}</span></div>@empty<p class="py-8 text-center text-gray-400">No assessment records found.</p>@endforelse</div></div>
@endsection
