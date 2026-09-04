<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Billing (one running bill per visit + its charges) and pharmacy
     * dispensing (a dispense and its priced line items).
     */
    public function up(): void
    {
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('open');        // App\Enums\BillStatus
            $table->timestamps();

            $table->index(['patient_id', 'status']);
            $table->index('visit_id');
        });

        Schema::create('bill_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_id')->constrained()->cascadeOnDelete();
            $table->string('source')->default('other');       // pharmacy | laboratory | consultation | other
            $table->string('description');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->nullableMorphs('reference');              // e.g. a dispense item, lab order
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('bill_id');
        });

        Schema::create('dispenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('encounter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('queue_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bill_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('dispensed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('dispensed');   // dispensed | cancelled
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'created_at']);
        });

        Schema::create('dispense_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispense_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('medication_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');                           // snapshot of item name
            $table->string('unit')->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2)->default(0); // snapshot of selling price
            $table->decimal('total', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dispense_items');
        Schema::dropIfExists('dispenses');
        Schema::dropIfExists('bill_charges');
        Schema::dropIfExists('bills');
    }
};
