<?php

namespace App\Http\Controllers;

use App\Services\SchoolAdminDashboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class AdminDashboardController extends Controller
{
    public function __invoke(SchoolAdminDashboardService $dashboard): Response|RedirectResponse
    {
        if (auth()->user()?->hasRole('super-admin')) {
            return redirect()->route('admin.platform-dashboard.index');
        }
        try {
            // Render here, rather than letting the framework render it after
            // the controller returns, so a faulty optional dashboard widget
            // cannot take the whole authenticated portal down.
            return response(view('admin.dashboard', ['intelligence' => $dashboard->summary()])->render());
        } catch (\Throwable $exception) {
            report($exception);
            error_log(sprintf('[CBE dashboard fallback] %s: %s in %s:%d', $exception::class, $exception->getMessage(), $exception->getFile(), $exception->getLine()));

            return response(view('admin.dashboard-fallback', ['exceptionId' => substr(sha1($exception->getMessage() . $exception->getFile()), 0, 12)])->render(), 200);
        }
    }
}
