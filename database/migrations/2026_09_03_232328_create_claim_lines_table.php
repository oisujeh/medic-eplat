<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One line per bill charge on a claim: the facility price, the tariff
     * claimed, and how it splits between the enrollee and the payer.
     */
    public function up(): void
    {
        Schema::create('claim_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claim_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bill_charge_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('source', 30);
            $table->string('description');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('gross_amount', 12, 2);
            $table->decimal('amount', 12, 2);
            $table->decimal('copay_amount', 12, 2)->default(0);
            $table->decimal('payer_amount', 12, 2)->default(0);
            $table->boolean('is_covered')->default(true);
            $table->decimal('approved_amount', 12, 2)->nullable();
            $table->string('remark')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('claim_lines');
    }
};
