<?php

namespace App\Http\Controllers;

use App\Models\LearningNote;
use App\Services\DataTransferPolicy;
use App\Services\GoogleDriveStorage;

class StoredFileController extends Controller
{
    public function note(LearningNote $note, GoogleDriveStorage $storage, DataTransferPolicy $transferPolicy)
    {
        abort_unless($note->is_published || auth()->user()->can('view notes'), 403);
        abort_unless($note->file_path, 404);

        $contents = $storage->contents($note->file_path);
        $transferPolicy->assertFileSize(strlen($contents), 'Stored learning note');

        return response($contents, 200, [
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
