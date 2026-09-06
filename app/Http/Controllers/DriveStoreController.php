<?php

namespace App\Http\Controllers;

use App\Services\GoogleDriveStorage;
use Illuminate\Contracts\View\View;

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
}
