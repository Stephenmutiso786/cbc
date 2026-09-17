<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /** Modules a plan can grant/withhold. Kept here as the single source of truth. */
    public static array $moduleKeys = [
        'fees', 'inventory', 'notifications', 'kemis', 'lesson_plans', 'portfolio', 'timetable', 'id_cards',
    ];

    public function up(): void
    {
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('package_id')->nullable()->constrained('packages')->nullOnDelete();
            $table->unsignedInteger('student_count');
            $table->decimal('amount', 12, 2);
            $table->string('phone')->nullable();
            $table->string('checkout_request_id')->nullable()->unique();
            $table->string('merchant_request_id')->nullable();
            $table->string('mpesa_receipt_number')->nullable();
            $table->string('status')->default('pending'); // pending, confirmed, failed, cancelled
            $table->string('failure_reason')->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Shule Basic / Shule Pro — editable afterwards by super-admin in
        // Manage Plans, this is just a sensible starting point.
        DB::table('packages')->insert([
            [
                'name' => 'Shule Basic',
                'description' => 'Core school management at KSh 100 per student, per term.',
                'price' => 100,
                'billing_cycle' => 'termly',
                'max_students' => null,
                'max_staff' => null,
                'sms_credits_granted' => 0,
                'features' => json_encode(['fees', 'notifications']),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Shule Pro',
                'description' => 'Everything in Basic plus inventory, KEMIS, lesson plans, portfolios, timetabling and ID cards, at KSh 150 per student, per term.',
                'price' => 150,
                'billing_cycle' => 'termly',
                'max_students' => null,
                'max_staff' => null,
                'sms_credits_granted' => 50,
                'features' => json_encode(static::$moduleKeys),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
        DB::table('packages')->whereIn('name', ['Shule Basic', 'Shule Pro'])->delete();
    }
};

