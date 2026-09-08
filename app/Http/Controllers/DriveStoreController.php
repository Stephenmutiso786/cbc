<?php

namespace App\Http\Controllers;

use App\Services\GoogleDriveStorage;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\RedirectResponse;

class DriveStoreController extends Controller
{
    public function index(GoogleDriveStorage $drive): View
    {
        $files = [];
        $error = null;

        try {
            $files = $drive->listFiles();
        } catch (\Throwable $exception) {
            report($exception);
            $error = $exception->getMessage();
        }

        return view('admin.drive-store', compact('files', 'error'));
    }

    public function syncRecords(): RedirectResponse
    {
        abort_unless(auth()->user()->can('manage system settings'), 403);

        try {
            $exitCode = Artisan::call('records:drive');
            $output = trim(Artisan::output());
            if ($exitCode !== 0) {
                return redirect()->route('admin.drive-store.index')->withErrors(['drive' => $output ?: 'The class records could not be synced to Google Drive.']);
            }

            return redirect()->route('admin.drive-store.index')->with('success', $output ?: 'Class records synced to Google Drive.');
        } catch (\Throwable $exception) {
            report($exception);
            return redirect()->route('admin.drive-store.index')->withErrors(['drive' => $exception->getMessage()]);
        }
    }
}
