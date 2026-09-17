<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('platform_broadcasts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('message');
            $table->boolean('send_sms')->default(false);
            $table->string('status')->default('draft'); // draft, sending, sent, failed
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('total_schools')->default(0);
            $table->unsignedInteger('schools_notified')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        // A broadcast-originated notification has no single per-school staff
        // sender. PostgreSQL and MySQL use different ALTER COLUMN syntax.
        $driver = DB::connection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE school_notifications ALTER COLUMN sender_id DROP NOT NULL');
        } elseif ($driver === 'mysql') {
            DB::statement('ALTER TABLE school_notifications MODIFY sender_id BIGINT UNSIGNED NULL');
        }

        Schema::table('school_notifications', function (Blueprint $table) {
            $table->foreignId('broadcast_id')->nullable()->after('sender_id')->constrained('platform_broadcasts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('school_notifications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('broadcast_id');
        });

        DB::statement("UPDATE school_notifications SET sender_id = (SELECT id FROM staff_members WHERE staff_members.school_id = school_notifications.school_id LIMIT 1) WHERE sender_id IS NULL");
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE school_notifications ALTER COLUMN sender_id SET NOT NULL');
        } elseif (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE school_notifications MODIFY sender_id BIGINT UNSIGNED NOT NULL');
        }

        Schema::dropIfExists('platform_broadcasts');
    }
};
