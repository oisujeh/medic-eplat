<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One row per business-number series and year (patient file numbers for
     * 2026, lab accession numbers for 2026, and so on). `next_value` is the
     * number the next record will receive; the row is locked while it is
     * taken, so numbers are issued once and in order.
     */
    public function up(): void
    {
        Schema::create('number_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 30);                     // App\Enums\NumberKind
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('next_value')->default(1);
            $table->timestamps();

            $table->unique(['kind', 'year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('number_sequences');
    }
};
