<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::before(function ($user, string $ability) {
            return $user->hasAnyRole(['admin', 'super-admin']) ? true : null;
        });

        try {
            foreach (DB::table('school_settings')->pluck('value', 'key') as $key => $value) {
                config()->set('school.' . $key, $value);
            }
        } catch (\Throwable) {
            // The table is unavailable during a first install or migration.
        }

        if (app()->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
