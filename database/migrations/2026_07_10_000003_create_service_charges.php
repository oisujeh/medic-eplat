<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The facility fee schedule: an editable price list of billable services
     * (consultation, admission, bed/day, procedures…). This supersedes the
     * one-off fee that briefly lived on service_points.
     */
    public function up(): void
    {
        Schema::create('service_charges', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('category')->default('other'); // App\Enums\ServiceCategory
            $table->string('unit')->nullable();            // e.g. per visit, per day
            $table->decimal('price', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['category', 'is_active']);
        });

        Schema::table('service_points', function (Blueprint $table) {
            $table->dropColumn('fee');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_points', function (Blueprint $table) {
            $table->decimal('fee', 12, 2)->nullable()->after('captures_vitals');
        });

        Schema::dropIfExists('service_charges');
    }
};
