<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The terminology service: one place that knows what a drug, a vaccine,
     * an allergen, an observation or a laboratory test *is*, and what each
     * external coding system (LOINC, ICD-10, the essential medicines list)
     * calls it. Clinical records point at a concept; reports and exports
     * read the mapping they need.
     */
    public function up(): void
    {
        Schema::create('concept_sources', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();         // LOINC, ICD10, NEML, LOCAL
            $table->string('name');
            $table->string('version', 50)->nullable();
            $table->string('url')->nullable();
            $table->timestamps();
        });

        Schema::create('concepts', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('class', 30)->index();          // App\Enums\ConceptClass
            $table->string('name');
            $table->string('description', 500)->nullable();
            // For observations and tests: numeric | text | coded, and the unit.
            $table->string('datatype', 20)->nullable();
            $table->string('unit', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();

            $table->index(['class', 'name']);
        });

        // A concept's code in one coding system. A code identifies one
        // concept within its source.
        Schema::create('concept_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('concept_id')->constrained()->cascadeOnDelete();
            $table->foreignId('concept_source_id')->constrained()->cascadeOnDelete();
            $table->string('code', 60);
            $table->timestamps();

            $table->unique(['concept_source_id', 'code']);
            $table->index('concept_id');
        });

        // Other names a concept is known by: brand names, abbreviations,
        // the spellings people type.
        Schema::create('concept_synonyms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('concept_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['concept_id', 'name']);
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('concept_synonyms');
        Schema::dropIfExists('concept_mappings');
        Schema::dropIfExists('concepts');
        Schema::dropIfExists('concept_sources');
    }
};
