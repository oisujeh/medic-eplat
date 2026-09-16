<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Every configurable catalogue entry (a notifiable disease, a laboratory
     * test, a fee-schedule line) keeps its history here: one row per version,
     * with the dates it was in force and what it said. A record made under a
     * version points at it, so changing today's catalogue never changes how
     * yesterday's record reads.
     */
    public function up(): void
    {
        Schema::create('catalogue_versions', function (Blueprint $table) {
            $table->id();
            $table->string('versionable_type');
            $table->unsignedBigInteger('versionable_id');
            $table->unsignedSmallInteger('version');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            // The versioned attributes as they stood.
            $table->json('snapshot');
            $table->string('reason', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['versionable_type', 'versionable_id', 'version']);
            $table->index(['versionable_type', 'versionable_id', 'effective_from']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalogue_versions');
    }
};
