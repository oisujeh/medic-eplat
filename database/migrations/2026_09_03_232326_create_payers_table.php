<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Third-party payers: the NHIA, health maintenance organisations and
     * corporate retainer schemes. Each carries the tariff rules applied when a
     * claim is built from a bill.
     */
    public function up(): void
    {
        Schema::create('payers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('code', 20)->unique();
            $table->string('type', 20);
            // Percentage taken off the facility price to reach the agreed tariff.
            $table->decimal('discount_percent', 5, 2)->default(0);
            // Share of drug costs the enrollee pays (NHIA: 10%).
            $table->decimal('drug_copay_percent', 5, 2)->default(0);
            $table->string('contact_person')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payers');
    }
};
