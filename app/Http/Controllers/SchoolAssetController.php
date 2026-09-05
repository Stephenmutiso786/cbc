<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\Response;

class SchoolAssetController extends Controller
{
    public function logo(): Response
    {
        $logo = (string) config('school.logo_data', '');

        abort_unless(str_starts_with($logo, 'data:image/'), 404);

        [$metadata, $encoded] = explode(',', $logo, 2);
        $mimeType = str_contains($metadata, ';')
            ? substr($metadata, 5, strpos($metadata, ';') - 5)
            : substr($metadata, 5);

        $contents = base64_decode($encoded, true);
        abort_unless(is_string($contents), 404);
        $transferPolicy->assertFileSize(strlen($contents), 'School logo');

        return response($contents, 200, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'public, max-age=86400',
            'Content-Disposition' => 'inline; filename="school-logo"',
        ]);
    }
}
