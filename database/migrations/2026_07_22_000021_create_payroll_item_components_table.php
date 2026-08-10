<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_item_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_item_id')->constrained('payroll_items')->cascadeOnDelete();
            $table->foreignId('salary_component_id')->nullable()->constrained('salary_components')->nullOnDelete();
            $table->string('component_name', 120);
            $table->string('component_code', 40)->nullable();
            $table->enum('component_type', ['earning', 'deduction']);
            $table->string('calculation_type', 40)->default('fixed');
            $table->decimal('rate', 14, 4)->default(0);
            $table->decimal('quantity', 12, 4)->default(0);
            $table->decimal('calculated_amount', 14, 2)->default(0);
            $table->decimal('amount', 14, 2)->default(0);
            $table->boolean('is_manual')->default(false);
            $table->boolean('is_overridden')->default(false);
            $table->string('override_reason', 500)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['payroll_item_id', 'component_type']);
            $table->index(['component_code', 'component_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_item_components');
    }
};
