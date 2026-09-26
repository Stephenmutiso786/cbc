<?php

namespace App\Http\Controllers;

use App\Livewire\SuperAdmin\PackageManager;
use App\Models\Package;
use Illuminate\Http\Request;

class FeatureAccessController extends Controller
{
    public function upgrade(Request $request, string $feature)
    {
        $labels = PackageManager::AVAILABLE_FEATURES;
        abort_unless(array_key_exists($feature, $labels), 404);

        $school = $request->user()?->school;
        abort_unless($school, 403);

        return view('access.feature-upgrade', [
            'feature' => $feature,
            'featureLabel' => $labels[$feature],
            'school' => $school->load('package'),
            'packages' => Package::query()
                ->where('is_active', true)
                ->orderBy('price')
                ->get()
                ->filter(fn (Package $package) => $package->hasFeature($feature)),
        ]);
    }
}
