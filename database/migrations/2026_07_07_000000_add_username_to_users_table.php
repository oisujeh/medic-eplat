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
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('email');
            // Professional cadre (App\Enums\StaffCadre) and the registration
            // number its council issued (MDCN, NMCN, PCN, MLSCN...).
            $table->string('cadre', 30)->nullable()->after('username');
            $table->string('registration_number', 50)->nullable()->after('cadre');
            $table->string('phone', 30)->nullable()->after('registration_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'cadre', 'registration_number', 'phone']);
        });
    }
};
