@extends('layouts.finance')
@section('header', 'Finance Dashboard')
@section('content')
<div class="card p-6">
    @php($year = config('school.academic_year'))
    @php($invoices = \App\Models\FeeInvoice::where('academic_year', $year))
    <h2 class="text-xl font-bold text-gray-800 mb-1">Finance Dashboard</h2><p class="text-sm text-gray-500 mb-6">Live financial position for {{ $year }}.</p>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-3"><div class="rounded-xl bg-blue-50 p-5"><p class="text-sm text-gray-500">Invoiced</p><p class="mt-2 text-2xl font-bold">KES {{ number_format((clone $invoices)->sum('total_amount'),2) }}</p></div><div class="rounded-xl bg-green-50 p-5"><p class="text-sm text-gray-500">Collected</p><p class="mt-2 text-2xl font-bold">KES {{ number_format((clone $invoices)->sum('amount_paid'),2) }}</p></div><div class="rounded-xl bg-red-50 p-5"><p class="text-sm text-gray-500">Outstanding</p><p class="mt-2 text-2xl font-bold">KES {{ number_format((clone $invoices)->sum('balance'),2) }}</p></div></div>
</div>
@endsection
