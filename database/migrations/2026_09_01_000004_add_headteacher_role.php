<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('roles')) return;

        $principal = DB::table('roles')->where(['name' => 'principal', 'guard_name' => 'web'])->first();
        $headteacher = DB::table('roles')->where(['name' => 'headteacher', 'guard_name' => 'web'])->first();
        if (! $headteacher) {
            $id = DB::table('roles')->insertGetId(['name' => 'headteacher', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
            $headteacher = (object) ['id' => $id];
        }

        if ($principal && Schema::hasTable('role_has_permissions')) {
            DB::table('role_has_permissions')->insertUsing(
                ['permission_id', 'role_id'],
                DB::table('role_has_permissions')->select(['permission_id', DB::raw((int) $headteacher->id)])->where('role_id', $principal->id)
                    ->whereNotIn('permission_id', DB::table('role_has_permissions')->where('role_id', $headteacher->id)->pluck('permission_id'))
            );
        }

        if ($principal && Schema::hasTable('model_has_roles')) {
            $principalAssignments = DB::table('model_has_roles')->where('role_id', $principal->id)->get();
            foreach ($principalAssignments as $assignment) {
                $exists = DB::table('model_has_roles')->where(['role_id' => $headteacher->id, 'model_type' => $assignment->model_type, 'model_id' => $assignment->model_id])->exists();
                if (! $exists) {
                    DB::table('model_has_roles')->insert(['role_id' => $headteacher->id, 'model_type' => $assignment->model_type, 'model_id' => $assignment->model_id]);
                }
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('roles')) return;
        $role = DB::table('roles')->where(['name' => 'headteacher', 'guard_name' => 'web'])->first();
        if (! $role) return;
        DB::table('model_has_roles')->where('role_id', $role->id)->delete();
        DB::table('role_has_permissions')->where('role_id', $role->id)->delete();
        DB::table('roles')->where('id', $role->id)->delete();
    }
};
