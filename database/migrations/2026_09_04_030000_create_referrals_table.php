<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Outbound referrals: the record behind a printed referral letter, and
     * the tracking of whether the receiving facility saw the patient.
     */
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->string('referral_number', 20)->unique();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('encounter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('referred_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('urgency', 20)->index();
            $table->string('destination_facility', 150);
            $table->string('destination_department', 100)->nullable();
            $table->string('destination_contact', 150)->nullable();

            $table->text('reason');
            $table->string('diagnosis')->nullable();
            $table->text('clinical_summary')->nullable();
            $table->text('treatment_given')->nullable();

            $table->string('status', 20)->index();
            $table->text('feedback')->nullable();
            $table->timestamp('feedback_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('referred_at')->index();
            $table->timestamp('printed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
