<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration {
    public function up(): void
    {
        $review = Permission::firstOrCreate(['name' => 'review marks', 'guard_name' => 'web']);
        $promotions = Permission::firstOrCreate(['name' => 'manage promotions', 'guard_name' => 'web']);

        foreach (['admin', 'super-admin', 'principal', 'headteacher', 'deputy-headteacher', 'deputy', 'hod'] as $name) {
            $role = Role::where('name', $name)->where('guard_name', 'web')->first();
            if ($role) $role->givePermissionTo($review);
        }
        foreach (['admin', 'super-admin', 'principal', 'headteacher', 'deputy-headteacher', 'deputy'] as $name) {
            $role = Role::where('name', $name)->where('guard_name', 'web')->first();
            if ($role) $role->givePermissionTo($promotions);
        }
    }

    public function down(): void
    {
        foreach (['review marks', 'manage promotions'] as $name) {
            $permission = Permission::where('name', $name)->where('guard_name', 'web')->first();
            if ($permission) $permission->delete();
        }
    }
};
