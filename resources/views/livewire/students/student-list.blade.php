<div>
    {{-- Header bar --}}
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-xl font-bold text-gray-800">Learners Register</h2>
        <button wire:click="create" class="bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-green-800">
            + Enrol Learner
        </button>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-xl shadow-sm p-4 mb-5 flex flex-wrap gap-4">
        <input wire:model.live.debounce.300ms="search" type="text"
               placeholder="Search by name, admission no., KEMIS UPI..."
               class="flex-1 min-w-48 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent">
        <select wire:model.live="gradeFilter" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            <option value="">All Grades</option>
            @foreach(config('school.grade_levels') as $level => $grades)
                <optgroup label="{{ str_replace('_',' ', ucwords($level)) }}">
                    @foreach($grades as $grade)
                        <option value="{{ $grade }}">{{ $grade }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
        <select wire:model.live="statusFilter" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            <option value="">All Status</option>
            <option value="1">Active</option>
            <option value="0">Inactive</option>
        </select>
        <select wire:model.live="boardingFilter" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            <option value="">Day & Boarding</option>
            <option value="day">Day</option>
            <option value="boarding">Boarding</option>
        </select>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Learner</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Adm. No.</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">KEMIS UPI</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Grade</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-100">
                @forelse($learners as $learner)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <div class="h-9 w-9 rounded-full {{ $learner->gender === 'male' ? 'bg-blue-100' : 'bg-pink-100' }} flex items-center justify-center text-sm font-bold {{ $learner->gender === 'male' ? 'text-blue-700' : 'text-pink-700' }}">
                                {{ strtoupper(substr($learner->first_name,0,1).substr($learner->last_name,0,1)) }}
                            </div>
                            <div>
                                <div class="text-sm font-semibold text-gray-900">{{ $learner->full_name }}</div>
                                <div class="text-xs text-gray-500">{{ ucfirst($learner->gender) }} · Age {{ $learner->age }}</div>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-700 font-mono">{{ $learner->admission_number }}</td>
                    <td class="px-4 py-3 text-sm text-gray-700 font-mono">{{ $learner->kemis_upi ?? '<span class="text-gray-400 italic">Not set</span>' }}</td>
                    <td class="px-4 py-3 text-sm text-gray-700">{{ $learner->grade_level->value }}</td>
                    <td class="px-4 py-3 text-sm text-gray-700">{{ $learner->schoolClass->name ?? '—' }}</td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-1 rounded-full text-xs font-medium {{ $learner->boarding_status === 'boarding' ? 'bg-purple-100 text-purple-700' : 'bg-gray-100 text-gray-700' }}">
                            {{ ucfirst($learner->boarding_status) }}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-1 rounded-full text-xs font-medium {{ $learner->is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                            {{ $learner->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td class="px-4 py-3 flex items-center gap-2">
                        <button wire:click="view({{ $learner->id }})" class="text-blue-600 hover:text-blue-800 text-xs font-medium">View</button>
                        <button wire:click="edit({{ $learner->id }})" class="text-green-600 hover:text-green-800 text-xs font-medium">Edit</button>
                        <button wire:click="generateReport({{ $learner->id }})" class="text-purple-600 hover:text-purple-800 text-xs font-medium">Report</button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-4 py-12 text-center text-gray-400">
                        <svg class="mx-auto h-10 w-10 text-gray-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/></svg>
                        <p class="text-sm">No learners found. Adjust your filters or enrol a new learner.</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-4 py-3 border-t border-gray-200 bg-gray-50">
            {{ $learners->links() }}
        </div>
    </div>
</div>

@if($showForm)
<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
    <div class="w-full max-w-3xl rounded-xl bg-white p-6 shadow-xl">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-lg font-bold text-gray-900">{{ $editingId ? 'Edit Learner' : 'Enrol Learner' }}</h3>
            <button wire:click="$set('showForm', false)" class="text-gray-400 hover:text-gray-700">X</button>
        </div>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            @foreach([
                ['admission_number','Admission number','text'], ['first_name','First name','text'],
                ['middle_name','Middle name','text'], ['last_name','Last name','text'],
                ['date_of_birth','Date of birth','date'], ['admission_date','Admission date','date'],
            ] as [$field, $label, $type])
            <label class="text-sm text-gray-700">{{ $label }}
                <input wire:model="form.{{ $field }}" type="{{ $type }}" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
                @error('form.'.$field)<span class="text-xs text-red-600">{{ $message }}</span>@enderror
            </label>
            @endforeach
            <label class="text-sm text-gray-700">Gender
                <select wire:model="form.gender" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2"><option value="male">Male</option><option value="female">Female</option></select>
            </label>
            <label class="text-sm text-gray-700">Grade
                <select wire:model="form.grade_level" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2"><option value="">Select grade</option>@foreach(config('school.grade_levels') as $grades) @foreach($grades as $grade)<option value="{{ $grade }}">{{ $grade }}</option>@endforeach @endforeach</select>
            </label>
            <label class="text-sm text-gray-700">Class
                <select wire:model="form.class_id" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2"><option value="">Select class</option>@foreach($classes as $class)<option value="{{ $class->id }}">{{ $class->name }}</option>@endforeach</select>
            </label>
            <label class="text-sm text-gray-700">Boarding status
                <select wire:model="form.boarding_status" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2"><option value="day">Day</option><option value="boarding">Boarding</option></select>
            </label>
        </div>
        <div class="mt-6 flex justify-end gap-3"><button wire:click="$set('showForm', false)" class="rounded-lg border px-4 py-2 text-sm">Cancel</button><button wire:click="save" class="rounded-lg bg-green-700 px-5 py-2 text-sm font-semibold text-white">Save learner</button></div>
    </div>
</div>
@endif
