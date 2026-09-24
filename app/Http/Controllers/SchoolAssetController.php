<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Support\SchoolSettingsLoader;
use Symfony\Component\HttpFoundation\Response;

class SchoolAssetController extends Controller
{
    public function logo(int $school): Response
    {
        // This endpoint is intentionally public because a browser/print engine
        // requests the image separately from the authenticated report page.
        // Resolve branding from the requested school, never from an absent
        // request tenant (which previously made every print logo return 404).
        School::query()->findOrFail($school);
        SchoolSettingsLoader::for($school);

        $logo = (string) config('school.logo_data', '');

        abort_unless(str_starts_with($logo, 'data:image/'), 404);

        [$metadata, $encoded] = explode(',', $logo, 2);
        $mimeType = str_contains($metadata, ';')
            ? substr($metadata, 5, strpos($metadata, ';') - 5)
            : substr($metadata, 5);

        $contents = base64_decode($encoded, true);
        abort_unless(is_string($contents), 404);
        // The upload path validates and compresses new images. Do not reject a
        // legacy school logo while serving it: schools created before that
        // compression policy may legitimately have a larger stored image and
        // an HTTP error here turns a valid report header into a broken image.

        return response($contents, 200, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'public, max-age=86400',
            'Content-Disposition' => 'inline; filename="school-logo"',
        ]);
    }
}
