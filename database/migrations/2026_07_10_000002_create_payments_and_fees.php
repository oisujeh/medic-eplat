<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Payments taken against a bill, and a per-service-point fee (so completing
     * a consultation can post a charge).
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('method')->default('cash');    // App\Enums\PaymentMethod
            $table->string('reference')->nullable();       // txn / receipt reference
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('bill_id');
        });

        Schema::table('service_points', function (Blueprint $table) {
            $table->decimal('fee', 12, 2)->nullable()->after('captures_vitals');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_points', function (Blueprint $table) {
            $table->dropColumn('fee');
        });

        Schema::dropIfExists('payments');
    }
};
