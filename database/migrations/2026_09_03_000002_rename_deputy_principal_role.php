<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $old = DB::table('roles')->where('name', 'deputy-principal')->first();
        $new = DB::table('roles')->where('name', 'deputy-headteacher')->first();
        if (! $old) return;
        if ($new) {
            DB::table('model_has_roles')->where('role_id', $old->id)->update(['role_id' => $new->id]);
            DB::table('role_has_permissions')->where('role_id', $old->id)->delete();
            DB::table('roles')->where('id', $old->id)->delete();
            return;
        }
        DB::table('roles')->where('id', $old->id)->update(['name' => 'deputy-headteacher']);
    }

    public function down(): void
    {
        DB::table('roles')->where('name', 'deputy-headteacher')->update(['name' => 'deputy-principal']);
    }
};
