<div>
    @if(session('success'))<div class="mb-4 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>@endif
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-xl font-bold text-gray-800">Exams Management</h2>
        <button wire:click="$set('showCreateModal', true)" class="bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-green-800">
            + Create Exam
        </button>
    </div>
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Exam Name</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Grade</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Learning Area</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Term</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-100">
                @forelse($exams as $exam)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm font-semibold text-gray-900">{{ $exam->name }}</td>
                    <td class="px-4 py-3 text-sm text-gray-700">{{ $exam->schoolClass?->name ?: $exam->grade_level }}</td>
                    <td class="px-4 py-3 text-sm text-gray-700">{{ $exam->learningArea->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-sm text-gray-700 capitalize">{{ str_replace('_',' ',$exam->exam_type) }}</td>
                    <td class="px-4 py-3 text-sm text-gray-700">Term {{ $exam->term }}</td>
                    <td class="px-4 py-3 text-sm text-gray-700">{{ $exam->exam_date?->format('d M Y') ?? '—' }}</td>
                    <td class="px-4 py-3 text-center">
                        <span class="px-2 py-1 rounded-full text-xs font-medium {{ $exam->status === 'published' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                            {{ ucfirst($exam->status) }}
                        </span>
                    </td>
                    <td class="px-4 py-3 flex flex-wrap gap-2">
                        @if(!$exam->isLocked())<button wire:click="loadMarkEntry({{ $exam->id }})" class="text-blue-600 hover:text-blue-800 text-xs font-medium">Enter Marks</button>@else<span class="text-xs font-medium text-gray-500">Locked</span>@endif
                        @if(auth()->user()->hasAnyRole(['admin', 'super-admin']))<button wire:click="editExam({{ $exam->id }})" class="text-green-600 hover:text-green-800 text-xs font-medium">Edit</button><button wire:click="deleteExam({{ $exam->id }})" wire:confirm="Delete this exam and all its results? This cannot be undone." class="text-red-600 hover:text-red-800 text-xs font-medium">Delete</button>@endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="px-4 py-12 text-center text-gray-400 text-sm">No exams created yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-4 py-3 border-t bg-gray-50">{{ $exams->links() }}</div>
    </div>

    @if($showCreateModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-xl bg-white p-6 shadow-2xl">
            <div class="mb-5 flex items-center justify-between">
                <h3 class="text-lg font-bold text-gray-900">{{ $editingExamId ? 'Edit exam' : 'Create exam' }}</h3>
                <button type="button" wire:click="$set('showCreateModal', false)" class="rounded p-2 text-gray-500 hover:bg-gray-100" aria-label="Close">&times;</button>
            </div>
            <form wire:submit="createExam" class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <label class="block md:col-span-2"><span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Exam name</span><input wire:model="examName" type="text" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" placeholder="e.g. Term 1 Mathematics Exam">@error('examName')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
                <label class="block"><span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Grade</span><select wire:model="examGrade" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm"><option value="">Select grade</option>@foreach(array_merge(...array_values(config('school.grade_levels'))) as $grade)<option value="{{ $grade }}">{{ $grade }}</option>@endforeach</select>@error('examGrade')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
                <label class="block"><span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Class</span><select wire:model="examClassId" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm"><option value="">Select class</option>@foreach($classes as $class)<option value="{{ $class->id }}">{{ $class->name }}</option>@endforeach</select>@error('examClassId')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
                <label class="block"><span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Learning area</span><select wire:key="exam-subjects-{{ $examClassId ?: 'all' }}" wire:model="examAreaId" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" @disabled(!$examClassId)><option value="">{{ $examClassId ? 'Select class subject' : 'Select class first' }}</option>@foreach($learningAreas as $area)<option value="{{ $area->id }}">{{ $area->name }}@if($area->pivot?->lessons_per_week) ({{ $area->pivot->lessons_per_week }} lessons/week)@endif</option>@endforeach</select><span class="text-[11px] text-gray-500">Subjects are loaded automatically from the selected class.</span>@error('examAreaId')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
                <label class="block"><span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Exam type</span><select wire:model="examType" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm"><option value="cat">CAT</option><option value="mid_term">Mid term</option><option value="end_term">End term</option><option value="mock">Mock</option><option value="kpsea">KPSEA</option><option value="kcse">KCSE</option></select></label>
                <label class="block"><span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Term</span><select wire:model="examTerm" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm">@foreach([1,2,3] as $term)<option value="{{ $term }}">Term {{ $term }}</option>@endforeach</select>@error('examTerm')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
                <label class="block"><span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Exam date</span><input wire:model="examDate" type="date" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm"></label>
                <label class="block"><span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Total marks</span><input wire:model="totalMarks" type="number" min="1" step="0.01" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm"></label>
                <label class="block"><span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Pass mark</span><input wire:model="passMark" type="number" min="0" step="0.01" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm"></label>
                <div class="flex justify-end gap-3 md:col-span-2"><button type="button" wire:click="$set('showCreateModal', false)" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm text-gray-700">Cancel</button><button type="submit" wire:loading.attr="disabled" class="rounded-lg bg-green-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-green-800 disabled:opacity-50">{{ $editingExamId ? 'Save changes' : 'Create exam' }}</button></div>
            </form>
        </div>
    </div>
    @endif
    @if($tab === 'marks' && $selectedExam)
    <div class="mt-5 rounded-xl bg-white p-5 shadow-sm">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2"><h3 class="font-bold text-gray-900">Enter marks</h3><button wire:click="$set('tab', 'exams')" class="text-sm text-green-700">Back to exams</button></div>
        <div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200"><thead class="bg-gray-50"><tr><th class="px-4 py-3 text-left text-xs uppercase">#</th><th class="px-4 py-3 text-left text-xs uppercase">Learner</th><th class="px-4 py-3 text-left text-xs uppercase">Marks</th></tr></thead><tbody class="divide-y divide-gray-100">@forelse($marks as $learnerId => $mark)<tr><td class="px-4 py-3 text-sm text-gray-500">{{ $loop->iteration }}</td><td class="px-4 py-3 text-sm font-semibold">{{ $mark['name'] }}</td><td class="px-4 py-3"><input wire:model="marks.{{ $learnerId }}.marks" type="number" min="0" step="0.01" class="w-32 rounded-lg border border-gray-300 px-3 py-2 text-sm"></td></tr>@empty<tr><td colspan="3" class="p-8 text-center text-sm text-gray-400">No active learners found for this class.</td></tr>@endforelse</tbody></table></div>
        <div class="mt-4 flex flex-wrap justify-end gap-2"><button wire:click="saveMarks" wire:loading.attr="disabled" class="rounded-lg bg-green-700 px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-50">Save marks</button>@if(auth()->user()->can('publish results'))<button wire:click="lockResults" class="rounded-lg bg-gray-800 px-5 py-2.5 text-sm font-semibold text-white">Lock results</button>@endif</div>
    </div>
    @endif
</div>
