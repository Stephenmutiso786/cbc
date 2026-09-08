<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('legal_terms_accepted_at')->nullable();
            $table->timestamp('legal_privacy_accepted_at')->nullable();
            $table->string('legal_acceptance_version', 30)->nullable();
            $table->ipAddress('legal_acceptance_ip')->nullable();
            $table->text('legal_acceptance_user_agent')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'legal_terms_accepted_at',
                'legal_privacy_accepted_at',
                'legal_acceptance_version',
                'legal_acceptance_ip',
                'legal_acceptance_user_agent',
            ]);
        });
    }
};
