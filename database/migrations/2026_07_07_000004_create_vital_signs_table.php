<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('service_points', function (Blueprint $table) {
            // Whether vitals / anthropometrics are captured when servicing a
            // patient here (e.g. Triage, ANC, Immunization).
            $table->boolean('captures_vitals')->default(false)->after('module_slug');
        });

        Schema::create('vital_signs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('queue_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_point_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();

            // Vitals
            $table->decimal('temperature_c', 4, 1)->nullable();
            $table->unsignedSmallInteger('systolic_bp')->nullable();
            $table->unsignedSmallInteger('diastolic_bp')->nullable();
            $table->unsignedSmallInteger('pulse_bpm')->nullable();
            $table->unsignedSmallInteger('respiratory_rate')->nullable();
            $table->unsignedTinyInteger('spo2')->nullable();
            $table->decimal('blood_glucose', 4, 1)->nullable();
            $table->unsignedTinyInteger('pain_score')->nullable();

            // Anthropometrics
            $table->decimal('weight_kg', 5, 2)->nullable();
            $table->decimal('height_cm', 5, 1)->nullable();
            $table->decimal('bmi', 4, 1)->nullable();
            $table->decimal('muac_cm', 4, 1)->nullable();
            $table->decimal('head_circumference_cm', 4, 1)->nullable();

            $table->text('notes')->nullable();
            $table->timestamp('recorded_at')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'recorded_at']);
            $table->index(['visit_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vital_signs');

        Schema::table('service_points', function (Blueprint $table) {
            $table->dropColumn('captures_vitals');
        });
    }
};
