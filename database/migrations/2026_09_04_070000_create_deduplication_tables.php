<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Patient deduplication: the weights behind the matching score, and the
     * queue of record pairs that scored high enough for a person to review.
     */
    public function up(): void
    {
        // One row per matching signal (App\Enums\MatchSignal). A signal with
        // no row uses its default weight; an inactive one contributes nothing.
        Schema::create('matching_rules', function (Blueprint $table) {
            $table->id();
            $table->string('signal', 40)->unique();
            $table->smallInteger('weight');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // A pair of records that may be the same person. patient_id is always
        // the lower id of the pair, so each pair appears once.
        Schema::create('patient_match_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained('patients')->cascadeOnDelete();
            $table->smallInteger('score');
            $table->string('level', 10);                       // App\Enums\MatchLevel
            $table->json('signals');                           // the MatchSignal values that fired
            $table->string('status', 12)->default('pending');  // App\Enums\MatchCandidateStatus
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->unique(['patient_id', 'candidate_id']);
            $table->index(['status', 'score']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_match_candidates');
        Schema::dropIfExists('matching_rules');
    }
};
