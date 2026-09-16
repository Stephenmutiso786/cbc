<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Gate;
use App\Models\Exam;
use App\Policies\ExamPolicy;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Exam::class, ExamPolicy::class);
        Gate::before(function ($user, string $ability) {
            return $user->hasRole('super-admin') ? true : null;
        });

        // Per-school settings load after authentication via LoadSchoolSettings.

        if (app()->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
