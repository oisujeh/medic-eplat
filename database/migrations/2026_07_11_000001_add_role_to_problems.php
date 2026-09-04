<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `role` categorises a diagnosis within the current assessment
     * (primary/secondary/differential). It is distinct from `status`, which
     * tracks the longitudinal state of the problem (active/chronic/resolved).
     * Null for problems added outside the assessment workflow.
     */
    public function up(): void
    {
        Schema::table('problems', function (Blueprint $table) {
            $table->string('role')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('problems', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
