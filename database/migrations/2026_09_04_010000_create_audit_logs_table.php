<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The audit trail is an append-only ledger of who did what to which
     * record. Rows are never updated or deleted by the application, and each
     * row carries a SHA-256 hash of its own content chained to the previous
     * row's hash, so any edit or removal made behind the application's back
     * is detectable with `php artisan audit:verify`.
     *
     * No foreign keys on purpose: a deleted staff account or patient must not
     * cascade into (or block) the audit history that refers to them, and the
     * user's name is snapshotted so the entry stays readable regardless.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // Who. Null when the action came from the system (a console
            // command, a scheduled job) rather than a signed-in member of staff.
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('user_name')->nullable();

            // What: created, updated, deleted, viewed, exported, login, logout, login_failed.
            $table->string('action', 32)->index();

            // The record acted on, and a short human-readable label for it.
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->string('label')->nullable();

            // The patient whose chart this touches, resolved at write time so
            // a patient's full access history is a single indexed lookup.
            $table->unsignedBigInteger('patient_id')->nullable()->index();

            // Before/after snapshots. Created entries carry only new values,
            // deleted entries only old values, updates carry both for the
            // attributes that changed.
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            // Request context.
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('route')->nullable();

            $table->dateTime('occurred_at', 6)->index();

            // Hash chain.
            $table->char('previous_hash', 64)->nullable();
            $table->char('hash', 64);

            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
