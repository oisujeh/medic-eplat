<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The ICD-10 catalogue clinicians code diagnoses against. Seeded with the
     * conditions Nigerian facilities see most; the full WHO list can be
     * imported with `php artisan icd:import`.
     */
    public function up(): void
    {
        Schema::create('icd_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('description');
            $table->string('chapter', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('icd_codes');
    }
};
