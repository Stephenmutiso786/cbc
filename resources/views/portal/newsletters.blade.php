@php($layout = auth()->user()->hasRole('learner') ? 'layouts.student' : (auth()->user()->hasRole('parent') ? 'layouts.parent' : 'layouts.teacher'))
@extends($layout)
@section('header', 'School Newsletters')
@section('content')
<div class="mx-auto max-w-4xl space-y-4"><div><h2 class="text-2xl font-bold text-gray-900">School newsletters</h2><p class="mt-1 text-sm text-gray-500">Official communications published by your school.</p></div>@forelse($newsletters as $newsletter)<article class="rounded-xl bg-white p-5 shadow-sm"><p class="text-xs font-semibold uppercase text-green-700">{{ $newsletter->issued_on?->format('d M Y') }}</p><h3 class="mt-1 text-lg font-bold">{{ $newsletter->subject }}</h3><p class="mt-3 whitespace-pre-line text-sm text-gray-700">{{ $newsletter->body }}</p><p class="mt-4 text-sm font-semibold">{{ $newsletter->signatory_name }} {{ $newsletter->signatory_title ? '· '.$newsletter->signatory_title : '' }}</p></article>@empty<div class="rounded-xl bg-white p-10 text-center text-gray-500">No newsletters have been published yet.</div>@endforelse</div>
@endsection
