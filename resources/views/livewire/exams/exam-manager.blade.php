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
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Class / Grade</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Learning Area</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Term</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-100">
                @php($examGroups = $exams->getCollection()->groupBy(fn ($exam) => $exam->grade_level.'|'.($exam->schoolClass?->name ?? '').'|'.$exam->name.'|'.$exam->term))
                @forelse($examGroups as $group)
                <tr class="bg-gray-100"><td colspan="8" class="px-4 py-2 text-xs font-bold uppercase tracking-wide text-gray-600">{{ $group->first()->grade_level }} @if($group->first()->schoolClass) · {{ $group->first()->schoolClass->name }} @endif · {{ $group->first()->name }} · Term {{ $group->first()->term }}</td></tr>
                @foreach($group as $exam)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm font-semibold text-gray-900">{{ $exam->name }}</td>
                    <td class="px-4 py-3 text-sm text-gray-700">{{ $exam->schoolClass?->grade_level }}{{ $exam->schoolClass?->name && $exam->schoolClass?->name !== $exam->schoolClass?->grade_level ? ' - '.$exam->schoolClass?->name : '' }}</td>
                    <td class="px-4 py-3 text-sm text-gray-700">{{ $exam->learningArea->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-sm text-gray-700 capitalize">{{ str_replace('_',' ',$exam->exam_type) }}</td>
                    <td class="px-4 py-3 text-sm text-gray-700">Term {{ $exam->term }}</td>
                    <td class="px-4 py-3 text-sm text-gray-700">{{ $exam->exam_date?->format('d M Y') ?? '—' }}</td>
                    <td class="px-4 py-3 text-center">
                        <span class="px-2 py-1 rounded-full text-xs font-medium {{ $exam->marks_status === 'approved' ? 'bg-green-100 text-green-700' : ($exam->marks_status === 'submitted' ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-600') }}">
                            {{ $exam->marks_status === 'approved' ? 'Results published' : 'Marks '.str_replace('_', ' ', $exam->marks_status) }}
                        </span>
                    </td>
                    <td class="px-4 py-3 flex flex-wrap gap-2">
                        @if(!$exam->isLocked() && $exam->marks_status !== 'submitted')<button wire:click="loadMarkEntry({{ $exam->id }})" class="text-blue-600 hover:text-blue-800 text-xs font-medium">Enter Marks</button>@elseif($exam->isLocked())<span class="text-xs font-medium text-gray-500">Locked</span>@endif
                        @if($exam->marks_status === 'submitted' && auth()->user()->hasAnyRole(['admin', 'super-admin', 'headteacher', 'principal', 'deputy-principal']))<button wire:click="openMarksReview({{ $exam->id }})" class="text-amber-700 hover:text-amber-900 text-xs font-medium">Review marks</button>@endif
                        @if(auth()->user()->hasAnyRole(['admin', 'super-admin', 'headteacher', 'principal', 'deputy-principal']) && $exam->marks_status === 'approved' && $exam->results->count() > 0)
                            <a href="{{ route('admin.exams.report-cards', $exam) }}" target="_blank" class="text-indigo-700 hover:text-indigo-900 text-xs font-medium">Print report cards</a>
                            <a href="{{ route('admin.exams.merit-list', $exam) }}" target="_blank" class="text-indigo-700 hover:text-indigo-900 text-xs font-medium">Print merit list</a>
                            @if($exam->results_sms_status === 'sent')<span class="text-xs font-medium text-green-700">Results sent</span>
                            @elseif($exam->results_sms_status === 'queued')<span class="text-xs font-medium text-amber-700">Sending results...</span>
                            @else<button wire:click="sendResults({{ $exam->id }})" wire:confirm="Send each learner's result to their guardian by SMS?" class="text-purple-700 hover:text-purple-900 text-xs font-medium">Send results</button>@endif
                        @endif
                        @if(auth()->user()->hasAnyRole(['admin', 'super-admin']))<button wire:click="editExam({{ $exam->id }})" class="text-green-600 hover:text-green-800 text-xs font-medium">Edit</button><button wire:click="deleteExam({{ $exam->id }})" wire:confirm="Delete this exam and all its results? This cannot be undone." class="text-red-600 hover:text-red-800 text-xs font-medium">Delete</button>@endif
                    </td>
                </tr>
                @endforeach
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
                <label class="block md:col-span-2"><span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Class / Grade</span><select wire:model.live="examClassId" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm"><option value="">Select class</option>@foreach($classes as $class)<option value="{{ $class->id }}">{{ $class->grade_level }}{{ $class->name !== $class->grade_level ? ' - '.$class->name : '' }}</option>@endforeach</select><span class="text-[11px] text-gray-500">The grade is taken automatically from the selected class.</span>@if($examClassId)<span class="mt-2 block rounded-lg bg-green-50 px-3 py-2 text-xs text-green-800"><strong>Automatic grading:</strong> {{ $examScaleName }} @if($examScaleBands) · {{ collect($examScaleBands)->pluck('code')->join(', ') }} @endif</span>@endif @error('examClassId')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
                <fieldset class="block"><legend class="text-xs font-semibold uppercase tracking-wide text-gray-500">Learning areas / subjects</legend><div wire:key="exam-subjects-{{ $examClassId ?: 'all' }}" class="mt-1 max-h-44 space-y-2 overflow-y-auto rounded-lg border border-gray-300 bg-white p-3 @if(!$examClassId) opacity-60 @endif">@forelse($learningAreas as $area)<label class="flex cursor-pointer items-center gap-2 text-sm"><input type="checkbox" wire:model="selectedExamAreaIds" value="{{ $area->id }}" @disabled(!$examClassId) class="rounded border-gray-300 text-green-700 focus:ring-green-600"><span>{{ $area->name }}@if($area->pivot?->lessons_per_week) <span class="text-xs text-gray-500">({{ $area->pivot->lessons_per_week }} lessons/week)</span>@endif</span></label>@empty<span class="text-xs text-gray-500">{{ $examClassId ? 'No subjects assigned to this class.' : 'Select a class first.' }}</span>@endforelse</div><span class="text-[11px] text-gray-500">Subjects are loaded from the selected class. Tick every subject to include in this exam.</span>@error('selectedExamAreaIds')<span class="block text-xs text-red-600">{{ $message }}</span>@enderror</fieldset>
                <label class="block"><span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Exam type</span><select wire:model="examType" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm"><option value="cat">CAT</option><option value="mid_term">Mid term</option><option value="end_term">End term</option><option value="mock">Mock</option><option value="kpsea">KPSEA</option><option value="kcse">KCSE</option></select></label>
                <label class="block"><span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Term</span><select wire:model="examTerm" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm">@foreach([1,2,3] as $term)<option value="{{ $term }}">Term {{ $term }}</option>@endforeach</select>@error('examTerm')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
                <label class="block"><span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Exam date</span><input wire:model="examDate" type="date" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm"></label>
                <label class="block"><span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Total marks</span><input wire:model="totalMarks" type="number" min="1" max="100" step="0.01" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm"><span class="text-[11px] text-gray-500">Maximum allowed: 100</span>@error('totalMarks')<span class="block text-xs text-red-600">{{ $message }}</span>@enderror</label>
                <label class="block"><span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Pass mark</span><input wire:model="passMark" type="number" min="0" max="100" step="0.01" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm">@error('passMark')<span class="block text-xs text-red-600">{{ $message }}</span>@enderror</label>
                <div class="flex justify-end gap-3 md:col-span-2"><button type="button" wire:click="$set('showCreateModal', false)" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm text-gray-700">Cancel</button><button type="submit" wire:loading.attr="disabled" class="rounded-lg bg-green-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-green-800 disabled:opacity-50">{{ $editingExamId ? 'Save changes' : 'Create exam' }}</button></div>
            </form>
        </div>
    </div>
    @endif
    @if($tab === 'marks' && $selectedExam)
    <div class="mt-5 rounded-xl bg-white p-5 shadow-sm">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2"><h3 class="font-bold text-gray-900">Enter marks</h3><button wire:click="$set('tab', 'exams')" class="text-sm text-green-700">Back to exams</button></div>
        <div class="mb-3 rounded-lg bg-blue-50 px-3 py-2 text-xs text-blue-800">Enter marks from 0 to 100, without exceeding the exam total. The class grading scale automatically awards each learner’s rubric level and comment.</div>@error('marks')<div class="mb-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ $message }}</div>@enderror
        <div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200"><thead class="bg-gray-50"><tr><th class="px-4 py-3 text-left text-xs uppercase">#</th><th class="px-4 py-3 text-left text-xs uppercase">Learner</th><th class="px-4 py-3 text-left text-xs uppercase">Marks</th></tr></thead><tbody class="divide-y divide-gray-100">@forelse($marks as $learnerId => $mark)<tr><td class="px-4 py-3 text-sm text-gray-500">{{ $loop->iteration }}</td><td class="px-4 py-3 text-sm font-semibold">{{ $mark['name'] }}</td><td class="px-4 py-3"><input wire:model="marks.{{ $learnerId }}.marks" type="number" min="0" max="100" step="0.01" class="w-32 rounded-lg border border-gray-300 px-3 py-2 text-sm"></td></tr>@empty<tr><td colspan="3" class="p-8 text-center text-sm text-gray-400">No active learners found for this class.</td></tr>@endforelse</tbody></table></div>
        <div class="mt-4 flex flex-wrap justify-end gap-2"><button wire:click="saveMarks" wire:loading.attr="disabled" class="rounded-lg bg-green-700 px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-50">Save marks</button>@if(auth()->user()->can('publish results'))<button wire:click="lockResults" class="rounded-lg bg-gray-800 px-5 py-2.5 text-sm font-semibold text-white">Lock results</button>@endif</div>
    </div>
    @endif
    @if($tab === 'review' && $selectedExam)
    <div class="mt-5 rounded-xl bg-white p-5 shadow-sm"><div class="mb-4 flex flex-wrap items-center justify-between gap-2"><div><h3 class="font-bold text-gray-900">Marks review</h3><p class="text-sm text-gray-500">{{ $reviewExam?->name }} · {{ $reviewExam?->schoolClass?->name }} · {{ $reviewExam?->learningArea?->name }}</p></div><button wire:click="$set('tab', 'exams')" class="text-sm text-green-700">Back to exams</button></div><div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200"><thead class="bg-gray-50"><tr><th class="px-4 py-3 text-left text-xs uppercase">#</th><th class="px-4 py-3 text-left text-xs uppercase">Admission</th><th class="px-4 py-3 text-left text-xs uppercase">Learner</th><th class="px-4 py-3 text-left text-xs uppercase">Marks</th><th class="px-4 py-3 text-left text-xs uppercase">Grade</th><th class="px-4 py-3 text-left text-xs uppercase">Rubric</th><th class="px-4 py-3 text-left text-xs uppercase">Comment</th></tr></thead><tbody class="divide-y divide-gray-100">@forelse($reviewResults as $result)<tr><td class="px-4 py-3 text-sm">{{ $loop->iteration }}</td><td class="px-4 py-3 text-sm">{{ $result['admission_number'] }}</td><td class="px-4 py-3 text-sm font-semibold">{{ $result['name'] }}</td><td class="px-4 py-3 text-sm">{{ $result['marks'] }} / {{ $result['total'] }}</td><td class="px-4 py-3 text-sm">{{ $result['grade'] }}</td><td class="px-4 py-3 text-sm">{{ $result['rubric_level'] ?: '-' }}</td><td class="px-4 py-3 text-sm">{{ $result['remarks'] ?: '-' }}</td></tr>@empty<tr><td colspan="7" class="p-8 text-center text-sm text-red-600">No submitted marks found. Results cannot be published.</td></tr>@endforelse</tbody></table></div><label class="mt-4 block"><span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Review comment</span><textarea wire:model="reviewComment" rows="3" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="Optional feedback for the teacher"></textarea></label><div class="mt-4 flex flex-wrap justify-end gap-2"><button wire:click="returnMarksForCorrection" wire:confirm="Return these marks to the teacher for correction?" class="rounded-lg border border-red-200 px-4 py-2.5 text-sm font-semibold text-red-700">Return for correction</button><button wire:click="approveMarksAndPublish" wire:confirm="Approve all marks and publish results?" class="rounded-lg bg-green-700 px-4 py-2.5 text-sm font-semibold text-white">Approve and publish results</button></div></div>
    @endif
</div>
