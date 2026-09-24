<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void { Schema::table('newsletters', function (Blueprint $table): void { $table->boolean('is_published')->default(false)->index()->after('copies'); $table->timestamp('published_at')->nullable()->after('is_published'); $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete()->after('published_at'); }); }
    public function down(): void { Schema::table('newsletters', function (Blueprint $table): void { $table->dropConstrainedForeignId('published_by'); $table->dropColumn(['is_published', 'published_at']); }); }
};
