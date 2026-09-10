<?php

use App\Support\RolePermissions;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration {
    public function up(): void
    {
        $role = Role::where('name', 'learner')->where('guard_name', 'web')->first();
        if ($role) {
            $role->syncPermissions(RolePermissions::byRole()['learner']);
        }
    }

    public function down(): void
    {
        $role = Role::where('name', 'learner')->where('guard_name', 'web')->first();
        if ($role) {
            $role->syncPermissions(['view notes', 'view timetable', 'submit support tickets']);
        }
    }
};
