<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Facility-wide configuration stored as key/value pairs, in the spirit of
     * OpenMRS global properties. The first-run wizard writes the facility
     * profile here and administrators can revise it afterwards.
     */
    public function up(): void
    {
        Schema::create('global_properties', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('global_properties');
    }
};
