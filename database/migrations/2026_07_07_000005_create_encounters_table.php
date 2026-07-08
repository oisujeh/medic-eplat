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
        Schema::create('encounters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('queue_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('clinician_id')->nullable()->constrained('users')->nullOnDelete();

            // SOAP-style clinical documentation.
            $table->text('presenting_complaint')->nullable();
            $table->text('history')->nullable();
            $table->text('examination')->nullable();
            $table->text('diagnosis')->nullable();
            $table->text('plan')->nullable();

            $table->string('status')->default('open');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'created_at']);
            $table->index(['visit_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('encounters');
    }
};
