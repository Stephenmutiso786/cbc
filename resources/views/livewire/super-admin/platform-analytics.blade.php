<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Platform Analytics</h1>
        <p class="text-sm text-gray-500">Enrollment, attendance and academic performance across every school (last 30 days).</p>
    </div>

    <div class="grid gap-4 md:grid-cols-3">
        <div class="card p-5"><p class="text-xs font-semibold uppercase text-gray-500">Schools</p><p class="mt-2 text-2xl font-bold text-gray-800">{{ $totalSchools }}</p></div>
        <div class="card p-5"><p class="text-xs font-semibold uppercase text-gray-500">Total learners</p><p class="mt-2 text-2xl font-bold text-gray-800">{{ number_format($totalLearners) }}</p></div>
        <div class="card p-5"><p class="text-xs font-semibold uppercase text-gray-500">Total staff</p><p class="mt-2 text-2xl font-bold text-gray-800">{{ number_format($totalStaff) }}</p></div>
    </div>

    <div class="card overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-4 py-3">School</th>
                    <th class="px-4 py-3">Learners</th>
                    <th class="px-4 py-3">Staff</th>
                    <th class="px-4 py-3">Attendance rate (30d)</th>
                    <th class="px-4 py-3">Avg exam score (30d)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($rows as $row)
                    <tr>
                        <td class="px-4 py-3 font-medium text-gray-800">{{ $row['school']->name }}</td>
                        <td class="px-4 py-3">{{ $row['school']->active_learners_count }}</td>
                        <td class="px-4 py-3">{{ $row['school']->active_staff_count }}</td>
                        <td class="px-4 py-3">{{ $row['attendance_rate'] !== null ? $row['attendance_rate'] . '%' : '—' }}</td>
                        <td class="px-4 py-3">{{ $row['avg_score'] !== null ? $row['avg_score'] . '%' : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">No schools yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

