<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Introduces the longitudinal clinical record tables that back the
     * consultation sidebar: the problem list, medication list, lab results,
     * allergies and manual alerts.
     */
    public function up(): void
    {
        // Allergies — substances the patient reacts to, surfaced as safety flags.
        Schema::create('patient_allergies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('substance');                     // e.g. Penicillin
            $table->string('category')->nullable();          // drug | food | environmental
            $table->string('reaction')->nullable();          // e.g. rash, anaphylaxis
            $table->string('severity')->nullable();          // mild | moderate | severe
            $table->string('status')->default('active');     // active | inactive
            $table->date('noted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'status']);
        });

        // Problem list — the patient's ongoing / resolved conditions.
        Schema::create('problems', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('encounter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('name');                          // e.g. Hypertension
            $table->string('code')->nullable();              // ICD-10
            $table->string('status')->default('active');     // active | chronic | resolved
            $table->date('onset_date')->nullable();
            $table->date('resolved_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'status']);
        });

        // Medication list — orders/prescriptions, current and historical.
        Schema::create('medications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('encounter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('prescribed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('name');                          // e.g. TLD, Amlodipine
            $table->string('dose')->nullable();              // e.g. 5mg
            $table->string('frequency')->nullable();         // e.g. OD, BD
            $table->string('route')->nullable();             // e.g. PO
            $table->string('status')->default('active');     // active | stopped | completed
            $table->date('started_at')->nullable();
            $table->date('stopped_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'status']);
        });

        // Lab results — investigation results linked to a visit / encounter.
        Schema::create('lab_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('encounter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ordered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resulted_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('name');                          // e.g. Viral Load
            $table->string('code')->nullable();              // LOINC
            $table->string('value')->nullable();             // e.g. "Undetectable", "742"
            $table->string('unit')->nullable();              // e.g. cells/mm3
            $table->string('reference_range')->nullable();   // e.g. "13-17"
            $table->string('flag')->nullable();              // normal | low | high | critical
            $table->string('specimen')->nullable();
            $table->string('status')->default('resulted');   // pending | resulted
            $table->timestamp('resulted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'resulted_at']);
        });

        // Manual clinical alerts — flags a clinician should see at a glance.
        Schema::create('patient_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('type')->default('clinical');     // clinical | administrative | safety
            $table->string('severity')->default('warning');  // info | warning | critical
            $table->string('message');
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_alerts');
        Schema::dropIfExists('lab_results');
        Schema::dropIfExists('medications');
        Schema::dropIfExists('problems');
        Schema::dropIfExists('patient_allergies');
    }
};
