<?php

use App\Models\School;
use App\Services\SchoolAcademicSetupService;
use App\Support\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backfill the usable CBE setup for tenants that were created before
     * school provisioning created their classes. This only adds missing
     * records and relationships; it never removes custom classes or subjects.
     */
    public function up(): void
    {
        if (! Schema::hasTable('schools') || ! Schema::hasTable('school_classes')) {
            return;
        }

        School::query()->orderBy('id')->each(function (School $school): void {
            Tenant::run($school->id, function (): void {
                app(SchoolAcademicSetupService::class)->initializeCurrentSchool();
            });
        });
    }

    /** The setup is additive and must not delete a school's academic data. */
    public function down(): void
    {
    }
};
