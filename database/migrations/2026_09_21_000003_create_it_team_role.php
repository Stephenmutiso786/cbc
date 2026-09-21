<?php
use App\Support\RolePermissions;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
return new class extends Migration {
 public function up(): void { $role=Role::firstOrCreate(['name'=>'it-team','guard_name'=>'web']); $role->syncPermissions(RolePermissions::byRole()['it-team']); app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions(); }
 public function down(): void { Role::where('name','it-team')->where('guard_name','web')->delete(); }
};
