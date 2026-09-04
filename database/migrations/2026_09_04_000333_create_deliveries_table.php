<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The delivery that ends a pregnancy: how, when, by whom, and how the
     * mother fared.
     */
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pregnancy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admission_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('delivered_at');
            $table->string('mode', 30);
            $table->string('labour_onset', 20)->nullable();
            $table->unsignedTinyInteger('gestational_age_weeks')->nullable();
            $table->string('place', 30)->default('facility');
            $table->foreignId('attendant_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('complications')->nullable();
            $table->unsignedInteger('blood_loss_ml')->nullable();
            $table->string('maternal_outcome', 20);
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('delivered_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
