<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The data-quality engine. A rule is a check that lives in code
     * (App\Support\DataQuality\Rules) paired with a registry row holding what
     * a facility may tune: severity, the role that owns the work, thresholds
     * and whether the check runs at all. Rules are versioned like the other
     * catalogues, so an issue points at the version it was raised under and
     * reads the same however the rule is retuned later.
     *
     * An issue is one record failing one rule. It is raised by a listener as
     * the record is written, or by the nightly sweep for the checks no event
     * can answer, and closes on its own when the record passes again.
     */
    public function up(): void
    {
        Schema::create('data_quality_rules', function (Blueprint $table) {
            $table->id();
            // The check class this row configures, e.g. encounter_unsigned.
            $table->string('key', 60)->unique();
            $table->string('name');
            // App\Enums\DataQualityDimension
            $table->string('dimension', 20)->index();
            // App\Enums\DataQualitySeverity
            $table->string('severity', 20);
            $table->text('description')->nullable();
            // The role slug whose queue the issues land in. Null: everyone
            // with the module sees them.
            $table->string('owner_role', 60)->nullable()->index();
            // Thresholds the check reads, e.g. {"hours": 24}. Shape is the
            // check's own; the registry only stores it.
            $table->json('parameters');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('data_quality_issues', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('data_quality_rule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('catalogue_version_id')->nullable()->constrained()->nullOnDelete();

            // The record that failed the rule. No foreign key: a rule can
            // judge any record, and the morph covers them all.
            $table->morphs('subject');
            // The patient the issue is about, when the subject has one, so a
            // chart can show what is outstanding against it.
            $table->foreignId('patient_id')->nullable()->constrained()->cascadeOnDelete();

            // Snapshot of the rule as it stood when the issue was raised.
            $table->string('dimension', 20)->index();
            $table->string('severity', 20)->index();
            $table->string('owner_role', 60)->nullable()->index();

            // What is wrong, in one line, plus whatever the check wants to
            // show alongside it.
            $table->string('summary', 300);
            $table->json('detail')->nullable();

            // App\Enums\DataQualityIssueStatus
            $table->string('status', 20)->default('open')->index();
            $table->timestamp('first_detected_at');
            // Touched every time the record is checked and still fails, so a
            // stale issue is visible as one nothing has re-confirmed.
            $table->timestamp('last_seen_at');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('resolution', 500)->nullable();
            $table->timestamps();

            // One open issue per record per rule; re-detection touches it.
            $table->unique(['data_quality_rule_id', 'subject_type', 'subject_id']);
            $table->index(['status', 'severity']);
            $table->index(['status', 'first_detected_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_quality_issues');
        Schema::dropIfExists('data_quality_rules');
    }
};
