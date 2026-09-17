<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Platform Finance</h1>
        <p class="text-sm text-gray-500">Fee collections across every school, and your own subscription revenue.</p>
    </div>

    <div class="grid gap-4 md:grid-cols-3">
        <div class="card p-5"><p class="text-xs font-semibold uppercase text-gray-500">Subscription revenue (last {{ $range }} days)</p><p class="mt-2 text-2xl font-bold text-blue-700">KSh {{ number_format($subscriptionRevenue, 0) }}</p></div>
        <div class="card p-5"><p class="text-xs font-semibold uppercase text-gray-500">Parent fees collected (last {{ $range }} days)</p><p class="mt-2 text-2xl font-bold text-green-700">KSh {{ number_format($totalFeesCollected, 0) }}</p></div>
        <div class="card p-5"><p class="text-xs font-semibold uppercase text-gray-500">Outstanding fee balances (all-time)</p><p class="mt-2 text-2xl font-bold text-red-600">KSh {{ number_format($totalOutstanding, 0) }}</p></div>
    </div>

    <div class="card overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-4 py-3">School</th>
                    <th class="px-4 py-3">Learners</th>
                    <th class="px-4 py-3">Fees collected</th>
                    <th class="px-4 py-3">Fees outstanding</th>
                    <th class="px-4 py-3">Subscription paid</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($perSchool as $row)
                    <tr>
                        <td class="px-4 py-3 font-medium text-gray-800">{{ $row['school']->name }}</td>
                        <td class="px-4 py-3">{{ $row['school']->learners_count }}</td>
                        <td class="px-4 py-3">KSh {{ number_format($row['fees_collected'], 0) }}</td>
                        <td class="px-4 py-3 {{ $row['fees_outstanding'] > 0 ? 'text-red-600' : '' }}">KSh {{ number_format($row['fees_outstanding'], 0) }}</td>
                        <td class="px-4 py-3">KSh {{ number_format($row['subscription_paid'], 0) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">No schools yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

