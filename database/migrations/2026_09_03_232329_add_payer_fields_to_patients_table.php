<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Link HMO-covered patients to a payer on record, with their plan and the
     * expiry of their enrolment.
     */
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->foreignId('payer_id')->nullable()->after('hmo_name')->constrained()->nullOnDelete();
            $table->string('hmo_plan', 100)->nullable()->after('hmo_number');
            $table->date('hmo_expires_at')->nullable()->after('hmo_plan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payer_id');
            $table->dropColumn(['hmo_plan', 'hmo_expires_at']);
        });
    }
};
