@extends('layouts.parent')
@section('header', 'Fees')
@section('content')
<div class="card p-6">@php($learnerIds = auth()->user()->guardian?->learners()->pluck('learners.id') ?? collect()) @php($invoices = \App\Models\FeeInvoice::whereIn('learner_id', $learnerIds)->with('learner')->latest()->get())<h2 class="text-xl font-bold text-gray-800 mb-5">My learners' fees</h2><div class="space-y-3">@forelse($invoices as $invoice)<div class="flex justify-between rounded-lg border p-4 text-sm"><span>{{ $invoice->learner?->full_name }} · {{ $invoice->invoice_number }}</span><span>KES {{ number_format($invoice->balance,2) }} outstanding</span></div>@empty<p class="py-8 text-center text-gray-400">No fee invoices found.</p>@endforelse</div></div>
@endsection
