<?php

namespace App\Http\Controllers;

use App\Models\StaffMember;
use Symfony\Component\HttpFoundation\Response;

class SchoolAssetController extends Controller
{
    public function logo(): Response
    {
        return $this->imageResponse((string) config('school.logo_data', ''));
    }

    public function official(string $asset): Response
    {
        abort_unless(in_array($asset, ['signature', 'stamp'], true), 404);

        return $this->imageResponse((string) config("school.official_{$asset}_data", ''));
    }

    public function staffSignature(string $staff): Response
    {
        $signature = StaffMember::query()->findOrFail($staff)->signature_data;

        return $this->imageResponse((string) $signature);
    }

    private function imageResponse(string $logo): Response
    {
        abort_unless(str_starts_with($logo, 'data:image/'), 404);

        [$metadata, $encoded] = explode(',', $logo, 2);
        $mimeType = str_contains($metadata, ';')
            ? substr($metadata, 5, strpos($metadata, ';') - 5)
            : substr($metadata, 5);
        $contents = base64_decode($encoded, true);

        abort_unless($contents !== false, 404);

        return response($contents, 200, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'public, max-age=86400',
            'Content-Disposition' => 'inline; filename="school-logo"',
        ]);
    }
}
