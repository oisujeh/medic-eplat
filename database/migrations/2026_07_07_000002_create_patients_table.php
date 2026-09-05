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
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('file_number')->unique();

            // Demographics
            $table->string('title')->nullable();
            $table->string('surname');
            $table->string('first_name');
            $table->string('other_names')->nullable();
            $table->date('date_of_birth')->nullable();
            // Set when the date of birth was worked back from an age given at
            // the desk rather than read from a document.
            $table->boolean('dob_estimated')->default(false);
            $table->string('sex');
            $table->string('marital_status')->nullable();
            // National Identification Number, 11 digits. Mirrored into
            // patient_identifiers, where uniqueness is checked.
            $table->string('nin', 11)->nullable()->index();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('nationality')->default('Nigerian');
            $table->string('state')->nullable();
            $table->string('lga')->nullable();
            // Political ward within the LGA, for aggregation below LGA level.
            $table->string('ward', 100)->nullable();
            // The patient's mother, when she is also on the register (set at
            // newborn registration). The link the MPI uses instead of a
            // free-text mother's name.
            $table->foreignId('mother_patient_id')->nullable()->constrained('patients')->nullOnDelete();

            // Next of kin
            $table->string('next_of_kin_name')->nullable();
            $table->string('next_of_kin_relationship')->nullable();
            $table->string('next_of_kin_phone')->nullable();

            // Billing coverage
            $table->string('coverage')->default('private');
            $table->string('hmo_name')->nullable();
            $table->string('hmo_number')->nullable();

            // Inter-facility transfer
            $table->boolean('is_transfer')->default(false);
            $table->string('transfer_from')->nullable();
            $table->string('transfer_reason')->nullable();
            $table->string('transfer_service')->nullable();

            // Visit category & routing (demographics/routing only — no clinical data)
            $table->string('visit_category')->default('Outpatient');
            $table->string('outpatient_service')->nullable();

            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();

            // Set when this record was merged into another as a duplicate.
            // The row stays as a tombstone so old file numbers and links
            // still lead to the surviving record.
            $table->foreignId('merged_into_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->timestamp('merged_at')->nullable();

            $table->timestamps();

            $table->index(['surname', 'first_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
