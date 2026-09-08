<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration {
    public function up(): void
    {
        $adminRole = DB::table('roles')->where('name', 'admin')->where('guard_name', 'web')->first();
        $schoolAdminRole = DB::table('roles')->where('name', 'school-admin')->where('guard_name', 'web')->first();

        if ($adminRole && ! $schoolAdminRole) {
            DB::table('roles')->where('id', $adminRole->id)->update(['name' => 'school-admin']);
        } elseif ($adminRole && $schoolAdminRole) {
            DB::table('model_has_roles')->where('role_id', $adminRole->id)->update(['role_id' => $schoolAdminRole->id]);
            DB::table('role_has_permissions')->where('role_id', $adminRole->id)->delete();
            DB::table('roles')->where('id', $adminRole->id)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $schoolAdminRole = DB::table('roles')->where('name', 'school-admin')->where('guard_name', 'web')->first();
        if ($schoolAdminRole && ! DB::table('roles')->where('name', 'admin')->where('guard_name', 'web')->exists()) {
            DB::table('roles')->where('id', $schoolAdminRole->id)->update(['name' => 'admin']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
