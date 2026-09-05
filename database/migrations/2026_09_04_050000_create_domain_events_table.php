<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The domain event journal. Every event the application publishes
     * (App\Events\DomainEvent) is written here before its listeners run, so
     * the journal is the complete, ordered history of what happened and the
     * feed that outbound integrations will read from later.
     */
    public function up(): void
    {
        Schema::create('domain_events', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            // The journaled event name, e.g. diagnosis_recorded.
            $table->string('name', 60)->index();
            // The record the event is about. No foreign key: the journal
            // outlives the records it describes.
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->unsignedBigInteger('patient_id')->nullable()->index();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->json('payload');
            $table->dateTime('occurred_at', 6)->index();

            $table->index(['subject_type', 'subject_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('domain_events');
    }
};
