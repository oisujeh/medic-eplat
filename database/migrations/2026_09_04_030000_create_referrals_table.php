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
            $table->ulid('ulid')->unique();
            $table->string('referral_number', 20)->unique();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('encounter_id')->nullable()->constrained()->nullOnDelete();
            // The visit a referral in opened, or an internal referral moved.
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('referred_by')->nullable()->constrained('users')->nullOnDelete();

            // Which way it travels (App\Enums\ReferralDirection). A referral
            // in carries where it came from; a referral out where it goes.
            $table->string('direction', 10)->default('outbound')->index();
            $table->string('source_facility', 150)->nullable();
            $table->string('source_contact', 150)->nullable();
            $table->timestamp('received_at')->nullable();

            $table->string('urgency', 20)->index();
            $table->string('destination_facility', 150)->nullable();
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
