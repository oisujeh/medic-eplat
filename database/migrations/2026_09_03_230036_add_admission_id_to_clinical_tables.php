<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Ward rounds and ward observations are tied to the admission as well as
     * the visit.
     */
    public function up(): void
    {
        Schema::table('encounters', function (Blueprint $table) {
            $table->foreignId('admission_id')->nullable()->after('queue_entry_id')->constrained()->nullOnDelete();
        });

        Schema::table('observation_sets', function (Blueprint $table) {
            $table->foreignId('admission_id')->nullable()->after('queue_entry_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('observation_sets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('admission_id');
        });

        Schema::table('encounters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('admission_id');
        });
    }
};
