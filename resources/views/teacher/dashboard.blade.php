@extends('layouts.teacher')
@section('header', auth()->user()->gradeBandLabel() ?? 'Teacher Dashboard')
@section('content')
@php($teacher = auth()->user()->staffMember)
@php($subjectAllocations = $teacher?->subjectAllocations()->with(['schoolClass', 'learningArea'])->where('academic_year', config('school.academic_year'))->where('term', (int) config('school.current_term'))->where('is_active', true)->orderBy('class_id')->get() ?? collect())
@php($publishedTimetable = $teacher?->timetableSlots()->with(['schoolClass', 'learningArea'])->where('academic_year', config('school.academic_year'))->where('term', (string) config('school.current_term'))->where('is_active', true)->orderByRaw("CASE day_of_week WHEN 'monday' THEN 1 WHEN 'tuesday' THEN 2 WHEN 'wednesday' THEN 3 WHEN 'thursday' THEN 4 ELSE 5 END")->orderBy('start_time')->limit(5)->get() ?? collect())
<div class="space-y-6">
	<div class="card p-6">
		<h2 class="text-xl font-bold text-gray-800 mb-1">{{ auth()->user()->gradeBandLabel() ?? 'Teacher Dashboard' }}</h2>
		<p class="text-sm text-gray-500 mb-6">Welcome, {{ $teacher?->full_name ?: auth()->user()->name }}.</p>
		<div class="grid grid-cols-1 gap-4 md:grid-cols-3">
			<div class="rounded-xl bg-blue-50 p-5">
				<p class="text-sm text-gray-500">Assigned subjects</p>
				<p class="mt-2 text-2xl font-bold">{{ $subjectAllocations->count() }}</p>
			</div>
			<div class="rounded-xl bg-green-50 p-5">
				<p class="text-sm text-gray-500">Published lessons</p>
				<p class="mt-2 text-2xl font-bold">{{ $teacher?->timetableSlots()->where('academic_year', config('school.academic_year'))->where('term', (string) config('school.current_term'))->where('is_active', true)->count() ?? 0 }}</p>
			</div>
			<div class="rounded-xl bg-yellow-50 p-5">
				<p class="text-sm text-gray-500">Assessments entered</p>
				<p class="mt-2 text-2xl font-bold">{{ $teacher?->assessments()->count() ?? 0 }}</p>
			</div>
		</div>
	</div>

	<div class="card p-6">
		<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
			<div>
				<h3 class="text-lg font-bold text-gray-800">My subject allocations</h3>
				<p class="text-sm text-gray-500">Subjects and classes assigned to you for Term {{ config('school.current_term') }}, {{ config('school.academic_year') }}.</p>
			</div>
			<span class="rounded-full bg-green-50 px-3 py-1 text-sm font-semibold text-green-800">{{ $subjectAllocations->count() }} subject{{ $subjectAllocations->count() === 1 ? '' : 's' }}</span>
		</div>
		<div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
			@forelse($subjectAllocations as $allocation)
				<div class="rounded-xl border border-green-100 bg-green-50 p-4">
					<p class="font-semibold text-gray-800">{{ $allocation->learningArea?->name ?? 'Subject' }}</p>
					<p class="mt-1 text-sm text-gray-600">{{ $allocation->schoolClass?->grade_level }}{{ $allocation->schoolClass?->name && $allocation->schoolClass->name !== $allocation->schoolClass->grade_level ? ' - '.$allocation->schoolClass->name : '' }}</p>
				</div>
			@empty
				<div class="rounded-xl border border-dashed border-gray-300 p-5 text-sm text-gray-500 sm:col-span-2 lg:col-span-3">No subjects have been allocated to you for the current term. Ask the school administrator to assign them in Classes, Subjects and Teachers.</div>
			@endforelse
		</div>
	</div>

	<div class="card p-6">
		<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
			<div>
				<h3 class="text-lg font-bold text-gray-800">Published timetable</h3>
				<p class="text-sm text-gray-500">What is already visible in your portal for this term.</p>
			</div>
			<a href="{{ route('teacher.timetable.print') }}" target="_blank" rel="noopener" class="rounded-lg border px-4 py-2 text-sm font-semibold text-gray-700">Print timetable</a>
		</div>
		<div class="overflow-x-auto">
			<table class="min-w-full divide-y">
				<thead>
					<tr>
						<th class="px-3 py-2 text-left text-xs uppercase">Day</th>
						<th class="px-3 py-2 text-left text-xs uppercase">Time</th>
						<th class="px-3 py-2 text-left text-xs uppercase">Class</th>
						<th class="px-3 py-2 text-left text-xs uppercase">Area</th>
					</tr>
				</thead>
				<tbody class="divide-y">
					@forelse($publishedTimetable as $slot)
						<tr>
							<td class="px-3 py-2 text-sm capitalize">{{ $slot->day_of_week }}</td>
							<td class="px-3 py-2 text-sm">{{ substr($slot->start_time, 0, 5) }} - {{ substr($slot->end_time, 0, 5) }}</td>
							<td class="px-3 py-2 text-sm">{{ $slot->schoolClass?->name }}</td>
							<td class="px-3 py-2 text-sm">{{ $slot->learningArea?->name }}</td>
						</tr>
					@empty
						<tr><td colspan="4" class="px-3 py-8 text-center text-gray-400">No published timetable has been released yet.</td></tr>
					@endforelse
				</tbody>
			</table>
		</div>
	</div>

	@livewire('teacher.view-results', ['dashboardOnly' => true])
</div>
@endsection
