<?php

use App\Models\Learner;
use App\Services\StudentAccountProvisioner;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Learner::withoutSchoolScope()->whereNull('user_id')->orderBy('id')->each(function (Learner $learner): void {
            app(StudentAccountProvisioner::class)->provision($learner);
        });
    }
    public function down(): void {}
};
