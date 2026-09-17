<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">ID Cards & Certificates</h1>
        <p class="text-sm text-gray-500">Upload a photo, select people, and generate a printable PDF.</p>
    </div>

    @if (session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ session('error') }}</div>
    @endif

    <div class="flex flex-wrap items-center gap-3">
        <div class="inline-flex rounded-lg border border-gray-300 overflow-hidden">
            <button wire:click="$set('tab', 'learners')" class="px-4 py-2 text-sm {{ $tab === 'learners' ? 'bg-green-700 text-white' : 'bg-white text-gray-700' }}">Learners</button>
            <button wire:click="$set('tab', 'staff')" class="px-4 py-2 text-sm {{ $tab === 'staff' ? 'bg-green-700 text-white' : 'bg-white text-gray-700' }}">Staff</button>
        </div>

        @if ($tab === 'learners')
            <select wire:model.live="classFilter" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
                <option value="">All classes</option>
                @foreach ($classes as $class)
                    <option value="{{ $class->id }}">{{ $class->name }}</option>
                @endforeach
            </select>
        @endif

        <select wire:model="docType" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
            <option value="id_card">ID Cards</option>
            <option value="certificate">Certificates</option>
        </select>

        @if ($docType === 'certificate')
            <select wire:model="certificateType" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
                <option value="completion">Completion</option>
                <option value="good_conduct">Good Conduct</option>
                <option value="transfer">Transfer</option>
            </select>
        @endif

        <span class="text-sm text-gray-500">{{ count($selected) }} selected</span>

        <button wire:click="generate" class="ml-auto rounded-lg bg-green-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-green-800">Generate PDF</button>
    </div>

    <div class="card overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-4 py-3"><button type="button" wire:click="toggleAll(true)" class="text-xs text-blue-600 hover:underline">All</button> / <button type="button" wire:click="toggleAll(false)" class="text-xs text-gray-500 hover:underline">None</button></th>
                    <th class="px-4 py-3">Photo</th>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">{{ $tab === 'learners' ? 'Admission No.' : 'Staff No.' }}</th>
                    <th class="px-4 py-3">{{ $tab === 'learners' ? 'Class' : 'Role' }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($items as $item)
                    <tr>
                        <td class="px-4 py-3"><input type="checkbox" value="{{ $item->id }}" wire:model="selected"></td>
                        <td class="px-4 py-3">
                            @if ($item->photo_path)
                                <img src="{{ \Illuminate\Support\Facades\Storage::url($item->photo_path) }}" class="h-10 w-10 rounded object-cover">
                            @else
                                <label class="text-xs text-blue-600 cursor-pointer">
                                    Upload
                                    <input type="file" class="hidden" wire:model="photoUploads.{{ $item->id }}" wire:change="uploadPhoto({{ $item->id }})">
                                </label>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $item->full_name ?? trim(($item->first_name ?? '') . ' ' . ($item->last_name ?? '')) }}</td>
                        <td class="px-4 py-3">{{ $tab === 'learners' ? $item->admission_number : $item->staff_number }}</td>
                        <td class="px-4 py-3">{{ $tab === 'learners' ? $item->schoolClass?->name : ucfirst(str_replace('_',' ', $item->employment_type ?? '')) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="p-4">{{ $items->links() }}</div>
    </div>
</div>

