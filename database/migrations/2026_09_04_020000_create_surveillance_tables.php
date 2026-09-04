<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Integrated Disease Surveillance and Response (IDSR). The catalogue lists
     * Nigeria's priority diseases with the rules that apply to them; a case is
     * opened from a source record (today a coded diagnosis, later a maternal
     * death or an AEFI) and carries a snapshot of the rules in force when it
     * was detected, so later catalogue edits never rewrite history.
     */
    public function up(): void
    {
        Schema::create('notifiable_diseases', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            // immediate: notify the DSNO within notification_hours. weekly: on the IDSR 002 return.
            $table->string('category', 20)->index();
            // diagnosis: opened by an ICD-10 code. event: opened by another module through the surveillance service.
            $table->string('detection', 20)->default('diagnosis');
            // ICD-10 code prefixes that identify the disease, e.g. ["A00"] or ["B50","B51"]. Empty for event-detected entries.
            $table->json('icd_prefixes');
            $table->text('case_definition')->nullable();
            // Hours allowed to reach the DSNO. Null when no individual notification is required.
            $table->unsignedSmallInteger('notification_hours')->nullable();
            $table->boolean('requires_contact_tracing')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('surveillance_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notifiable_disease_id')->constrained()->restrictOnDelete();
            // A case is part of the legal surveillance record: it outlives nothing.
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('encounter_id')->nullable()->constrained()->nullOnDelete();

            // The record that opened the case (a problem today; a delivery,
            // birth or immunization later). One case per source record. The
            // link is cleared explicitly by the service, never by the database,
            // so the audit trail records it.
            $table->nullableMorphs('source');
            $table->string('icd_code', 10)->nullable();

            // Snapshot of the catalogue rules at detection.
            $table->string('category', 20)->index();
            $table->text('case_definition')->nullable();
            $table->boolean('requires_contact_tracing')->default(false);
            $table->timestamp('notification_due_at')->nullable()->index();

            $table->string('classification', 20)->default('suspected')->index();
            $table->timestamp('classified_at')->nullable();
            $table->foreignId('classified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('onset_date')->nullable();
            $table->string('outcome', 20)->default('unknown');

            $table->timestamp('detected_at');
            $table->foreignId('detected_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('notification_status', 20)->index();
            $table->timestamp('notified_at')->nullable();
            $table->foreignId('notified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notified_to')->nullable();
            $table->string('notification_reference')->nullable();

            // The patient's residence when the case was detected, for
            // aggregation by LGA and state that does not drift as the folder
            // is edited.
            $table->string('residence_state', 100)->nullable()->index();
            $table->string('residence_lga', 100)->nullable()->index();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['source_type', 'source_id']);
            $table->index(['detected_at', 'notifiable_disease_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('surveillance_cases');
        Schema::dropIfExists('notifiable_diseases');
    }
};
