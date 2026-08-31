@extends('layouts.admin')
@section('header', 'Reports')
@section('content')
<div class="card p-6">
    @php($year = config('school.academic_year'))
    @php($learnerCount = \App\Models\Learner::where('academic_year', $year)->count())
    @php($assessmentCount = \App\Models\Assessment::where('academic_year', $year)->count())
    @php($invoiceCount = \App\Models\FeeInvoice::where('academic_year', $year)->count())
    @php($collected = \App\Models\FeePayment::whereHas('invoice', fn($q) => $q->where('academic_year', $year))->sum('amount'))
    <h2 class="text-xl font-bold text-gray-800 mb-1">Reports overview</h2><p class="text-sm text-gray-500 mb-6">Live totals for academic year {{ $year }}.</p>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-4">@foreach([['Learners',$learnerCount,'bg-green-50'],['Assessments',$assessmentCount,'bg-blue-50'],['Invoices',$invoiceCount,'bg-yellow-50'],['Collected','KES '.number_format($collected,2),'bg-purple-50']] as [$label,$value,$color])<div class="rounded-xl {{ $color }} p-5"><p class="text-sm text-gray-500">{{ $label }}</p><p class="mt-2 text-2xl font-bold text-gray-900">{{ $value }}</p></div>@endforeach</div>
    <div class="mt-6 rounded-lg border border-gray-200 p-5"><h3 class="font-semibold text-gray-800">Available reports</h3><p class="mt-2 text-sm text-gray-500">Use the learner register to open individual report-card data. Fee totals above are calculated directly from recorded payments.</p></div>
</div>
@endsection
