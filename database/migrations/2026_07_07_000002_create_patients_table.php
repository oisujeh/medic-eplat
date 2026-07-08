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
            $table->string('file_number')->unique();

            // Demographics
            $table->string('title')->nullable();
            $table->string('surname');
            $table->string('first_name');
            $table->string('other_names')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('sex');
            $table->string('marital_status')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('nationality')->default('Nigerian');
            $table->string('state')->nullable();
            $table->string('lga')->nullable();

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
