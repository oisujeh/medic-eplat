<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The forms engine (PRD 4.3). A form is a clinical data-capture template
     * defined in JSON — pages, sections, fields, repeating groups — with its
     * skip logic, calculations and validation declared in the definition
     * rather than in code. Publishing freezes the definition as an immutable
     * version; a submission points at the version it was filled with, so it
     * always renders the way it was entered (FRM-06).
     *
     * Every field binds to a dictionary concept (FRM-03), and every value a
     * completed submission holds is copied into `form_values`, one row per
     * answer, so line lists can be written in plain SQL (FRM-14).
     */
    public function up(): void
    {
        Schema::create('forms', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('slug', 60)->unique();
            $table->string('name');
            $table->string('description', 500)->nullable();
            $table->string('status', 20)->default('draft');           // FormStatus
            $table->string('entry_mode', 10)->default('guided');      // FormEntryMode, the default when opened
            // Where the form is offered (FRM-11): the encounter types it is
            // assigned to, and the roles that may fill it (empty: anyone
            // who can document the encounter).
            $table->json('encounter_types');
            $table->json('roles');
            // The working draft. Publishing copies it into form_versions.
            $table->json('definition');
            $table->unsignedSmallInteger('published_version')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'name']);
        });

        Schema::create('form_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('version');
            // The definition as published, with every concept resolved to
            // its id. Never changes once written.
            $table->json('definition');
            $table->string('notes', 500)->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at');
            $table->timestamp('created_at')->nullable();

            $table->unique(['form_id', 'version']);
        });

        Schema::create('form_submissions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('form_id')->constrained()->cascadeOnDelete();
            $table->foreignId('form_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('encounter_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('draft');           // FormSubmissionStatus
            $table->string('entry_mode', 10);                          // FormEntryMode it was filled in
            // The answers keyed by field key; a repeating section holds a
            // list of instances, each its own object (FRM-05).
            $table->json('data')->nullable();
            $table->foreignId('filled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('last_saved_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'status']);
            $table->index(['encounter_id', 'form_id']);
            $table->index(['form_id', 'status', 'completed_at']);
        });

        // The flattened observation table: one row per answer on a
        // completed submission, queryable by patient and concept.
        Schema::create('form_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('form_id')->constrained()->cascadeOnDelete();
            $table->foreignId('form_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('encounter_id')->nullable()->constrained()->nullOnDelete();
            $table->string('field_key', 60);
            $table->foreignId('concept_id')->nullable()->constrained()->nullOnDelete();
            // Which repeat of which group the answer came from, when the
            // field sits in a repeating section.
            $table->string('group_key', 60)->nullable();
            $table->unsignedSmallInteger('repeat_index')->nullable();
            $table->string('value_type', 20);                          // numeric | text | coded | date | datetime | boolean
            $table->decimal('value_numeric', 14, 4)->nullable();
            $table->text('value_text')->nullable();
            $table->foreignId('value_concept_id')->nullable()->constrained('concepts')->nullOnDelete();
            // The code or id of a catalogue entry the answer names: an ICD-10
            // code, a laboratory test id.
            $table->string('value_reference', 60)->nullable();
            $table->date('value_date')->nullable();
            $table->timestamp('value_datetime')->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->string('unit', 30)->nullable();
            $table->timestamp('recorded_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['patient_id', 'concept_id', 'recorded_at']);
            $table->index(['concept_id', 'recorded_at']);
            $table->index(['form_id', 'field_key']);
            $table->index('encounter_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_values');
        Schema::dropIfExists('form_submissions');
        Schema::dropIfExists('form_versions');
        Schema::dropIfExists('forms');
    }
};
