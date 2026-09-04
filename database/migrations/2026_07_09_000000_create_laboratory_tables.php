<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The laboratory module: an orderable test compendium (with panels), the
     * requisitions placed against it, and the evolution of `lab_results` into
     * per-analyte result lines that hang off a requisition.
     */
    public function up(): void
    {
        // The orderable test compendium.
        Schema::create('lab_tests', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();                 // local / LOINC code, e.g. FBC, NA
            $table->string('name');
            $table->string('department');                      // App\Enums\LabDepartment
            $table->string('specimen_type')->nullable();      // e.g. EDTA blood, Serum, Urine
            $table->string('unit')->nullable();               // e.g. g/dL
            $table->decimal('reference_low', 12, 3)->nullable();
            $table->decimal('reference_high', 12, 3)->nullable();
            $table->string('reference_text')->nullable();     // qualitative ref, e.g. "Negative"
            $table->decimal('price', 10, 2)->nullable();
            $table->unsignedSmallInteger('turnaround_hours')->nullable();
            $table->boolean('is_panel')->default(false);      // a bundle of component analytes
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['department', 'is_active']);
        });

        // Which analytes make up a panel (self-referential many-to-many on lab_tests).
        Schema::create('lab_panel_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('panel_id')->constrained('lab_tests')->cascadeOnDelete();
            $table->foreignId('test_id')->constrained('lab_tests')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);

            $table->unique(['panel_id', 'test_id']);
        });

        // A requisition — one or more tests ordered for a patient.
        Schema::create('lab_orders', function (Blueprint $table) {
            $table->id();
            $table->string('accession_number')->unique();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('encounter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('queue_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ordered_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('priority')->default('normal');    // App\Enums\Priority
            $table->string('status')->default('ordered');     // App\Enums\LabOrderStatus
            $table->text('clinical_details')->nullable();     // indication / provisional diagnosis

            // Specimen handling / chain of custody.
            $table->string('specimen_type')->nullable();
            $table->foreignId('collected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('collected_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();

            // Verification / release.
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();

            $table->string('cancelled_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'priority']);
            $table->index(['patient_id', 'created_at']);
        });

        // Evolve lab_results into per-analyte result lines belonging to an order.
        Schema::table('lab_results', function (Blueprint $table) {
            $table->foreignId('lab_order_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->foreignId('lab_test_id')->nullable()->after('lab_order_id')->constrained()->nullOnDelete();
            $table->string('department')->nullable()->after('code');
            $table->decimal('reference_low', 12, 3)->nullable()->after('reference_range');
            $table->decimal('reference_high', 12, 3)->nullable()->after('reference_low');

            $table->index('lab_order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lab_results', function (Blueprint $table) {
            $table->dropForeign(['lab_order_id']);
            $table->dropForeign(['lab_test_id']);
            $table->dropColumn(['lab_order_id', 'lab_test_id', 'department', 'reference_low', 'reference_high']);
        });

        Schema::dropIfExists('lab_orders');
        Schema::dropIfExists('lab_panel_items');
        Schema::dropIfExists('lab_tests');
    }
};
