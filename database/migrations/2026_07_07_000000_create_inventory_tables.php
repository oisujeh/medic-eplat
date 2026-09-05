<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The store: the places stock is kept, a catalogue of stock items, the
     * batches they are held in at each place (for expiry / FEFO), an
     * append-only movement ledger, and requisitions that move stock from one
     * place to another.
     */
    public function up(): void
    {
        // Where stock is held: the main store, the dispensary, later a ward
        // store. A location tied to a service point supplies that point.
        Schema::create('stock_locations', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('kind', 20);                       // App\Enums\StockLocationKind
            $table->foreignId('service_point_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('receives_deliveries')->default(false); // where supplier stock lands
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();                 // SKU
            $table->string('name');
            $table->foreignId('concept_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category')->default('drug');      // App\Enums\InventoryCategory
            $table->string('form')->nullable();               // Tablet, Capsule, Injection…
            $table->string('strength')->nullable();           // 500mg, 5mg/5ml…
            $table->string('unit')->default('each');          // dispensing unit
            $table->decimal('cost_price', 12, 2)->nullable();
            $table->decimal('selling_price', 12, 2)->default(0);
            $table->unsignedInteger('reorder_level')->default(0);
            $table->integer('quantity_on_hand')->default(0);  // cached sum of batch quantities, all locations
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['category', 'is_active']);
            $table->index('concept_id');
        });

        Schema::create('stock_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_location_id')->constrained()->restrictOnDelete();
            $table->string('batch_number')->nullable();
            $table->date('expiry_date')->nullable();
            $table->integer('quantity')->default(0);
            $table->decimal('cost_price', 12, 2)->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->index(['inventory_item_id', 'expiry_date']);
            $table->index(['stock_location_id', 'inventory_item_id']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('stock_location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');                           // App\Enums\StockMovementType
            $table->integer('quantity_change');               // signed: + in, - out
            $table->string('reason')->nullable();
            $table->nullableMorphs('reference');              // e.g. a dispense, a requisition
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['inventory_item_id', 'created_at']);
            $table->index(['type', 'created_at']);
        });

        // A request for stock from one location to another, e.g. the
        // dispensary asking the main store for a week's supply.
        Schema::create('stock_requisitions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('number')->unique();               // REQ/2026/000001
            $table->foreignId('from_location_id')->constrained('stock_locations')->restrictOnDelete();
            $table->foreignId('to_location_id')->constrained('stock_locations')->restrictOnDelete();
            $table->string('status', 20)->default('requested'); // App\Enums\RequisitionStatus
            $table->string('note')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'requested_at']);
            $table->index('to_location_id');
        });

        Schema::create('stock_requisition_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_requisition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity_requested');
            $table->unsignedInteger('quantity_approved')->nullable();
            $table->unsignedInteger('quantity_issued')->default(0);
            $table->unsignedInteger('quantity_received')->default(0);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['stock_requisition_id', 'inventory_item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_requisition_lines');
        Schema::dropIfExists('stock_requisitions');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_batches');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('stock_locations');
    }
};
