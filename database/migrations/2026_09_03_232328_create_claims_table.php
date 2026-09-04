<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A claim to a payer for the services on one bill. The payer's share is
     * settled on the bill as an HMO payment (and any tariff discount as a
     * waiver) so the cashier only collects the enrollee's co-payment.
     */
    public function up(): void
    {
        Schema::create('claims', function (Blueprint $table) {
            $table->id();
            $table->string('claim_number')->unique();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payer_id')->constrained()->restrictOnDelete();
            $table->foreignId('bill_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('claim_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('draft');

            // Enrolment details as they stood when the claim was raised.
            $table->string('enrollee_number', 100)->nullable();
            $table->string('plan', 100)->nullable();
            $table->date('service_date');
            $table->text('diagnosis')->nullable();

            $table->string('authorization_code', 100)->nullable();
            $table->timestamp('authorized_at')->nullable();
            $table->string('authorization_note', 500)->nullable();

            $table->decimal('gross_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('copay_amount', 12, 2)->default(0);
            $table->decimal('payer_amount', 12, 2)->default(0);
            $table->decimal('approved_amount', 12, 2)->nullable();
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->string('rejection_reason', 500)->nullable();

            // The bill payments that stand in for the payer's share.
            $table->foreignId('hmo_payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('waiver_payment_id')->nullable()->constrained('payments')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('remitted_at')->nullable();
            $table->string('remittance_reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['payer_id', 'status']);
            $table->index(['patient_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('claims');
    }
};
