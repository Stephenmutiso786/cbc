@extends('layouts.parent')
@section('header', 'Notes')
@section('content')
<div class="card p-6"><h2 class="text-xl font-bold text-gray-800 mb-5">Learning notes</h2><div class="space-y-3">@forelse($notes as $note)<div class="rounded-lg border p-4"><p class="font-semibold">{{ $note->title }}</p><p class="text-sm text-gray-500">{{ $note->learningArea?->name }} · {{ $note->grade_level }} · {{ $note->term }}</p>@if($note->external_url)<a href="{{ $note->external_url }}" target="_blank" class="mt-2 inline-block text-sm font-semibold text-green-700">Open resource</a>@elseif($note->file_path)<a href="{{ route('files.notes', $note) }}" class="mt-2 inline-block text-sm font-semibold text-green-700">Download resource</a>@endif</div>@empty<p class="py-8 text-center text-gray-400">No published notes for your linked learners' grades.</p>@endforelse</div></div>
@endsection
