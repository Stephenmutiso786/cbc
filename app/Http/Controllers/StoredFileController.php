<?php

namespace App\Http\Controllers;

use App\Models\LearningNote;
use App\Services\GoogleDriveStorage;

class StoredFileController extends Controller
{
    public function note(LearningNote $note, GoogleDriveStorage $storage)
    {
        abort_unless($note->is_published || auth()->user()->hasAnyRole(['admin', 'super-admin', 'teacher', 'class-teacher', 'hod']), 403);
        abort_unless($note->file_path, 404);

        return response($storage->contents($note->file_path), 200, [
            'Content-Type' => $this->mimeType($note->file_path, $note->resource_type),
            'Content-Disposition' => 'inline; filename="' . addslashes($note->title) . '"',
        ]);
    }

    private function mimeType(string $path, string $type): string
    {
        return match ($type) {
            'pdf' => 'application/pdf',
            'image' => 'image/*',
            'video' => 'video/*',
            default => 'application/octet-stream',
        };
    }
}
