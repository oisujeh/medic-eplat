<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A diagnosis picked from the ICD catalogue keeps a link to it, so
     * morbidity returns can group by code family even if the text is edited.
     */
    public function up(): void
    {
        Schema::table('problems', function (Blueprint $table) {
            $table->foreignId('icd_code_id')->nullable()->after('code')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('problems', function (Blueprint $table) {
            $table->dropConstrainedForeignId('icd_code_id');
        });
    }
};
