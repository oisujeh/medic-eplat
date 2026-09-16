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
            // A second number, and whose phone the primary one is (self,
            // spouse, parent...), for tracing and reminders.
            $table->string('phone_alternate')->nullable();
            $table->string('phone_owner', 20)->nullable();
            // Whether the patient agreed to SMS reminders at registration.
            $table->boolean('sms_consent')->default(false);
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
            // When the patient was first registered at the facility. Differs
            // from created_at when a paper register is entered later.
            $table->timestamp('registered_at')->nullable()->index();

            // Metaphone of the surname and first name, so search can find a
            // name by how it sounds.
            $table->string('phonetic_key', 60)->nullable()->index();

            // Where the patient stands with the facility (App\Enums\PatientStatus).
            $table->string('status', 20)->default('active')->index();
            $table->timestamp('status_changed_at')->nullable();
            $table->string('status_reason')->nullable();
            $table->string('transferred_to', 200)->nullable();

            // A restricted record opens only with the break-glass permission
            // and a logged reason.
            $table->boolean('is_restricted')->default(false);
            $table->string('restricted_reason')->nullable();

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
