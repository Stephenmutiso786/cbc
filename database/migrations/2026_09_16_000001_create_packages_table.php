<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->string('billing_cycle')->default('yearly'); // monthly, termly, yearly
            $table->unsignedInteger('max_students')->nullable(); // null = unlimited
            $table->unsignedInteger('max_staff')->nullable();
            $table->unsignedInteger('sms_credits_granted')->default(0); // credited each time this package is (re)assigned
            $table->json('features')->nullable(); // e.g. ["sms","kemis","google_drive","inventory"]
            $table->boolean('is_active')->default(true); // still offered to new schools
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};

