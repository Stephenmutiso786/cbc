@extends('layouts.admin')
@section('header', 'Settings')
@section('content')
<div class="card p-6">
    <h2 class="text-xl font-bold text-gray-800 mb-1">School settings</h2>
    <p class="mb-6 text-sm text-gray-500">Changes here are saved to the database and used by the system.</p>
    @if(session('success'))<div class="mb-5 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="mb-5 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('admin.settings.update') }}" class="grid grid-cols-1 gap-4 md:grid-cols-2">
        @csrf @method('PUT')
        @foreach([['name','School name',config('school.name'),true],['motto','Motto',config('school.motto'),false],['academic_year','Academic year',config('school.academic_year'),true],['current_term','Current term (1-3)',config('school.current_term'),true],['address','Address',config('school.address'),false],['phone','Phone',config('school.phone'),false],['email','Email',config('school.email'),false]] as [$key,$label,$value,$required])
            <label class="block"><span class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $label }}</span><input name="{{ $key }}" value="{{ old($key, $value) }}" {{ $required ? 'required' : '' }} class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm"></label>
        @endforeach
        <label class="block"><span class="text-xs font-semibold uppercase tracking-wide text-gray-500">School type</span><select name="type" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm">@foreach(['primary','secondary','mixed'] as $type)<option value="{{ $type }}" @selected(old('type', config('school.type')) === $type)>{{ ucfirst($type) }}</option>@endforeach</select></label>
        <div class="md:col-span-2"><button class="rounded-lg bg-green-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-green-800">Save settings</button></div>
    </form>
</div>
@endsection
