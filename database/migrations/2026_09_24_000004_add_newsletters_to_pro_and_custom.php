<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('packages')->orderBy('id')->get()->each(function (object $package): void {
            $planName = strtolower(trim($package->name));
            if ($planName !== 'shule pro' && ! str_contains($planName, 'custom')) return;
            $features = json_decode($package->features ?: '[]', true) ?: [];
            if (! in_array('newsletters', $features, true)) {
                $features[] = 'newsletters';
                DB::table('packages')->where('id', $package->id)->update(['features' => json_encode($features), 'updated_at' => now()]);
            }
        });
    }
    public function down(): void { }
};
