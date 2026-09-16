<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One row per baby delivered: the birth register. A live baby can be
     * registered as a patient in their own right and linked here.
     */
    public function up(): void
    {
        Schema::create('births', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete(); // the mother
            $table->foreignId('newborn_patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->unsignedTinyInteger('birth_order')->default(1);
            $table->string('outcome', 30);
            $table->string('sex', 1);
            $table->unsignedInteger('weight_grams')->nullable();
            $table->unsignedTinyInteger('apgar_1')->nullable();
            $table->unsignedTinyInteger('apgar_5')->nullable();
            $table->boolean('resuscitated')->default(false);
            $table->boolean('breastfed_within_hour')->default(false);
            $table->boolean('bcg_given')->default(false);
            $table->boolean('opv0_given')->default(false);
            $table->boolean('hepb0_given')->default(false);
            $table->string('condition', 30)->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('births');
    }
};
