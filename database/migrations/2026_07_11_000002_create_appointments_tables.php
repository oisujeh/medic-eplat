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
        // Per-provider weekly availability templates. Bookable slots are
        // generated from these; `service_point_id` null means "any clinic".
        Schema::create('provider_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('service_point_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('weekday'); // 0 = Sunday … 6 = Saturday
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('slot_minutes')->default(15);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['provider_id', 'weekday']);
        });

        // Ad-hoc time off / breaks that remove availability for a window.
        Schema::create('schedule_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['provider_id', 'starts_at']);
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('service_point_id')->constrained()->cascadeOnDelete();

            $table->timestamp('scheduled_start');
            $table->timestamp('scheduled_end');
            $table->unsignedSmallInteger('duration_minutes');

            $table->string('status')->default('scheduled');
            $table->string('source')->default('booked'); // booked | walk_in | follow_up
            $table->string('priority')->default('normal');
            $table->string('reason')->nullable();
            $table->text('note')->nullable();

            // Set when the appointment is checked in / originates from a consultation.
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('queue_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('encounter_id')->nullable()->constrained()->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('checked_in_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();

            $table->timestamps();

            $table->index(['provider_id', 'scheduled_start']);
            $table->index(['service_point_id', 'scheduled_start']);
            $table->index(['status', 'scheduled_start']);
            $table->index(['patient_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('schedule_blocks');
        Schema::dropIfExists('provider_schedules');
    }
};
