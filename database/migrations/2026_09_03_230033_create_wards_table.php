<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Wards and the beds within them. A ward optionally points at the fee
     * schedule entry that prices a day in one of its beds.
     */
    public function up(): void
    {
        Schema::create('wards', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 20)->unique();
            $table->string('type', 30);
            $table->foreignId('bed_service_charge_id')->nullable()->constrained('service_charges')->nullOnDelete();
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('beds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ward_id')->constrained()->cascadeOnDelete();
            $table->string('label', 50);
            $table->string('status', 20)->default('available');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['ward_id', 'label']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('beds');
        Schema::dropIfExists('wards');
    }
};
