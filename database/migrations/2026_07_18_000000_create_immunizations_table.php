<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Immunizations administered to a patient, linked to the nursing
     * encounter they were given in.
     */
    public function up(): void
    {
        Schema::create('immunizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('encounter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('administered_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('vaccine');                       // e.g. BCG, OPV, Penta
            $table->string('dose_label')->nullable();        // e.g. Birth, OPV 1, Booster
            $table->string('batch_no')->nullable();
            $table->string('site')->nullable();              // e.g. Left thigh
            $table->string('route')->nullable();             // e.g. IM, PO, SC
            $table->text('notes')->nullable();
            $table->timestamp('administered_at')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'administered_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('immunizations');
    }
};
