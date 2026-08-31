@extends('layouts.admin')
@section('header', 'Settings')
@section('content')
<div class="card p-6">
    <h2 class="text-xl font-bold text-gray-800 mb-1">School settings</h2><p class="text-sm text-gray-500 mb-6">Current configuration used by the system.</p>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">@foreach([['School name',config('school.name')],['Motto',config('school.motto')],['Academic year',config('school.academic_year')],['Current term','Term '.config('school.current_term')],['School type',ucfirst(config('school.type'))],['Email',config('school.email') ?: 'Not configured'],['Phone',config('school.phone') ?: 'Not configured'],['Application environment',app()->environment()]] as [$label,$value])<div class="rounded-lg border border-gray-200 p-4"><p class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ $label }}</p><p class="mt-1 font-medium text-gray-800">{{ $value }}</p></div>@endforeach</div>
</div>
@endsection
