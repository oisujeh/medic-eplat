<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * An encounter is any documented clinical contact with a patient — a
     * physician consultation, a triage or nursing session, a ward round or a
     * discharge — distinguished by `type`. Every clinical record produced
     * during the contact (diagnoses, prescriptions, orders, observations,
     * immunizations) links back to it.
     *
     * The narrative follows SOAP: `presenting_complaint` + `subjective`
     * (history), `objective` (examination), `assessment` (impression) and
     * `plan`. `structured` carries the per-type detail as JSON:
     *
     *   consultation: { subjective: {...}, examination: { general, systems },
     *                   plan: { procedures[], imaging[], referrals[],
     *                   counseling[] }, follow_up: { interval,
     *                   monitoring_goals[], patient_instructions } }
     *   nursing:      { family_planning: { method, counseling } }
     *
     * Coded diagnoses live in `problems`; measurements in `observations`.
     */
    public function up(): void
    {
        Schema::create('encounters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('queue_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_point_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('type', 30);                            // EncounterType
            $table->string('status', 20)->default('in_progress');  // EncounterStatus

            // SOAP narrative
            $table->text('presenting_complaint')->nullable();
            $table->text('subjective')->nullable();
            $table->text('objective')->nullable();
            $table->text('assessment')->nullable();
            $table->text('plan')->nullable();
            $table->json('structured')->nullable();

            // Disposition
            $table->string('outcome', 20)->nullable();             // EncounterOutcome
            $table->timestamp('follow_up_at')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'signed_at']);
            $table->index(['type', 'status', 'signed_at']);
            $table->index('visit_id');
            $table->unique('queue_entry_id');
        });

        // A signed encounter is immutable; corrections and late findings are
        // appended as addenda, each with its own author and time.
        Schema::create('encounter_addenda', function (Blueprint $table) {
            $table->id();
            $table->foreignId('encounter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['encounter_id', 'recorded_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('encounter_addenda');
        Schema::dropIfExists('encounters');
    }
};
