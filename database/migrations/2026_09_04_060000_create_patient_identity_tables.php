<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The Master Patient Index tables: every identifier a patient carries,
     * searchable in one place, and the record of a patient's death.
     */
    public function up(): void
    {
        Schema::create('patient_identifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);                       // App\Enums\IdentifierType
            $table->string('value', 100);
            // Who issued it: an HMO, a previous facility, NIMC.
            $table->string('issuer', 150)->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->unique(['patient_id', 'type', 'value']);
            $table->index(['type', 'value']);
        });

        // How two patients on the register are related: mother and child,
        // members of one household, an index case and a contact. One row
        // per direction, written as a pair.
        Schema::create('patient_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('related_patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('type', 30);                       // App\Enums\RelationshipType
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['patient_id', 'related_patient_id', 'type']);
            $table->index('related_patient_id');
        });

        Schema::create('deaths', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            // One death per patient; the record outlives nothing.
            $table->foreignId('patient_id')->unique()->constrained()->restrictOnDelete();
            $table->timestamp('died_at');
            $table->string('place', 30)->default('facility');  // facility | community | other
            $table->text('cause')->nullable();
            // The clinical record that carried the death: a discharge, a delivery.
            $table->nullableMorphs('source');
            $table->foreignId('certified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('died_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deaths');
        Schema::dropIfExists('patient_relationships');
        Schema::dropIfExists('patient_identifiers');
    }
};
