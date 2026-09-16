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
        Schema::create('service_points', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->string('icon')->nullable();
            // The module that governs who may work this queue (e.g. "nursing").
            $table->string('module_slug')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Where each member of staff works: the service points they are
        // posted to, for pickers and, later, per-point session rules.
        Schema::create('service_point_user', function (Blueprint $table) {
            $table->foreignId('service_point_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['service_point_id', 'user_id']);
        });

        // A clinical pathway as data: which service points a visit moves
        // through, where it may start, and which steps may follow which.
        // OPD, emergency, antenatal and immunization are seeded; a facility
        // can add its own. The steps themselves stay generic service points.
        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 40)->unique();
            $table->string('name');
            $table->string('description', 500)->nullable();
            // The pathway a visit takes when none fits the entry point better.
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('workflow_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_point_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            // The encounter opened at this step (App\Enums\EncounterType);
            // null falls back to the service point's module.
            $table->string('encounter_type', 30)->nullable();
            // The fee-schedule code posted when the step's encounter is signed.
            $table->string('charge_code', 50)->nullable();
            // Whether a visit may start at this step.
            $table->boolean('is_entry')->default(false);
            $table->timestamps();

            $table->unique(['workflow_id', 'service_point_id']);
        });

        Schema::create('workflow_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_stage_id')->constrained('workflow_stages')->cascadeOnDelete();
            $table->foreignId('to_stage_id')->constrained('workflow_stages')->cascadeOnDelete();
            // The step suggested first when the patient is routed onward.
            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['from_stage_id', 'to_stage_id']);
        });

        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('visit_number')->unique();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            // The pathway the visit follows; null for a visit opened before
            // pathways existed or outside any of them.
            $table->foreignId('workflow_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('open');
            $table->string('reason')->nullable();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'status']);
        });

        Schema::create('queue_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_point_id')->constrained()->cascadeOnDelete();
            // The pathway step this entry is, when the visit follows one.
            $table->foreignId('workflow_stage_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('waiting');
            $table->string('priority')->default('normal');
            $table->text('note')->nullable();
            $table->foreignId('routed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['service_point_id', 'status']);
            $table->index(['visit_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('queue_entries');
        Schema::dropIfExists('visits');
        Schema::dropIfExists('workflow_transitions');
        Schema::dropIfExists('workflow_stages');
        Schema::dropIfExists('workflows');
        Schema::dropIfExists('service_point_user');
        Schema::dropIfExists('service_points');
    }
};
