@extends('layouts.admin')
@section('header', 'Staff')
@section('content')
<div class="card p-6">
    @php($staff = \App\Models\StaffMember::with('user')->orderBy('last_name')->paginate(25))
    <div class="flex items-center justify-between mb-5"><div><h2 class="text-xl font-bold text-gray-800">Staff register</h2><p class="text-sm text-gray-500">{{ $staff->total() }} staff records</p></div></div>
    <div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200"><thead class="bg-gray-50"><tr><th class="px-4 py-3 text-left text-xs uppercase">Name</th><th class="px-4 py-3 text-left text-xs uppercase">Staff No.</th><th class="px-4 py-3 text-left text-xs uppercase">Designation</th><th class="px-4 py-3 text-left text-xs uppercase">Contact</th><th class="px-4 py-3 text-left text-xs uppercase">Status</th></tr></thead><tbody class="divide-y divide-gray-100">@forelse($staff as $member)<tr><td class="px-4 py-3 text-sm font-semibold">{{ $member->full_name }}</td><td class="px-4 py-3 text-sm">{{ $member->staff_number }}</td><td class="px-4 py-3 text-sm">{{ $member->designation ?: ucfirst(str_replace('_',' ', $member->staff_type)) }}</td><td class="px-4 py-3 text-sm">{{ $member->email }}<br>{{ $member->phone_number }}</td><td class="px-4 py-3 text-sm"><span class="rounded-full px-2 py-1 text-xs {{ $member->is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">{{ $member->is_active ? 'Active' : 'Inactive' }}</span></td></tr>@empty<tr><td colspan="5" class="px-4 py-10 text-center text-gray-400">No staff records yet.</td></tr>@endforelse</tbody></table></div><div class="mt-4">{{ $staff->links() }}</div>
</div>
@endsection
