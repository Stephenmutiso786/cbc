<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('learner_risk_predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('learner_id')->constrained()->cascadeOnDelete();
            $table->decimal('risk_score', 5, 2);
            $table->string('risk_level', 10);
            $table->json('features');
            $table->json('top_factors')->nullable();
            $table->timestamp('computed_at');
            $table->timestamps();
            $table->unique(['school_id', 'learner_id']);
            $table->index(['school_id', 'risk_level']);
        });

        DB::table('packages')->where('name', 'Shule Pro')->orderBy('id')->get()->each(function ($package) {
            $features = json_decode($package->features ?: '[]', true) ?: [];
            if (! in_array('predictive_analytics', $features, true)) {
                $features[] = 'predictive_analytics';
                DB::table('packages')->where('id', $package->id)->update(['features' => json_encode($features), 'updated_at' => now()]);
            }
        });
    }
    public function down(): void { Schema::dropIfExists('learner_risk_predictions'); }
};
