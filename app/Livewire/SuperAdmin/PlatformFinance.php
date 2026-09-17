<?php

namespace App\Livewire\SuperAdmin;

use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\School;
use App\Models\SubscriptionPayment;
use App\Support\Tenant;
use Livewire\Component;

class PlatformFinance extends Component
{
    public string $range = '30'; // days

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasRole('super-admin'), 403);
    }

    public function render()
    {
        $since = now()->subDays((int) $this->range);

        // Cross-school aggregation: no tenant scope applies here since
        // super-admin has no school_id (Tenant::id() is null), so these
        // Eloquent queries naturally span every school.
        $subscriptionRevenue = SubscriptionPayment::where('status', 'confirmed')->where('created_at', '>=', $since)->sum('amount');

        $perSchool = School::query()
            ->withCount(['learners' => fn ($q) => $q->where('is_active', true)])
            ->get()
            ->map(function (School $school) use ($since) {
                $collected = Tenant::run($school->id, fn () => FeePayment::where('status', 'confirmed')->where('created_at', '>=', $since)->sum('amount'));
                $outstanding = Tenant::run($school->id, fn () => FeeInvoice::sum('balance'));
                $subRevenue = SubscriptionPayment::where('school_id', $school->id)->where('status', 'confirmed')->where('created_at', '>=', $since)->sum('amount');

                return [
                    'school' => $school,
                    'fees_collected' => (float) $collected,
                    'fees_outstanding' => (float) $outstanding,
                    'subscription_paid' => (float) $subRevenue,
                ];
            })
            ->sortByDesc('fees_collected')
            ->values();

        return view('livewire.super-admin.platform-finance', [
            'perSchool' => $perSchool,
            'totalFeesCollected' => $perSchool->sum('fees_collected'),
            'totalOutstanding' => $perSchool->sum('fees_outstanding'),
            'subscriptionRevenue' => (float) $subscriptionRevenue,
        ])->layout('layouts.admin');
    }
}

