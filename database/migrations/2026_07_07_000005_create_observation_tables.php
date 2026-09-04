<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Observations are measurements — vital signs, anthropometrics, antenatal
     * findings — stored one row per measurement, keyed by an ObservationCode.
     * A set groups the readings taken at one moment (a "vitals panel") and
     * carries the context they were taken in and the worst alert level found.
     */
    public function up(): void
    {
        Schema::table('service_points', function (Blueprint $table) {
            // Whether observations are captured when servicing a patient here
            // (e.g. Triage, ANC, Immunization).
            $table->boolean('captures_vitals')->default(false)->after('module_slug');
        });

        Schema::create('observation_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('encounter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('queue_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('alert_level', 10)->default('normal');  // AlertLevel
            $table->text('notes')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['patient_id', 'recorded_at']);
            $table->index(['visit_id', 'recorded_at']);
        });

        Schema::create('observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('observation_set_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();

            $table->string('code', 40);                            // ObservationCode
            $table->decimal('value', 8, 2)->nullable();            // numeric reading
            $table->string('text_value')->nullable();              // categorical reading, e.g. presentation
            $table->string('unit', 20)->nullable();
            $table->string('level', 10)->nullable();               // AlertLevel, when interpreted
            $table->string('flag')->nullable();                    // e.g. "Fever", "Hypotension"
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->unique(['observation_set_id', 'code']);
            $table->index(['patient_id', 'code', 'recorded_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('observations');
        Schema::dropIfExists('observation_sets');

        Schema::table('service_points', function (Blueprint $table) {
            $table->dropColumn('captures_vitals');
        });
    }
};
