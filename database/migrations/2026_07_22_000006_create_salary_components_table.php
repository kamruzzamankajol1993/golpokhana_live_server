<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_components', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('code', 30)->nullable()->unique();
            $table->enum('type', ['earning', 'deduction']);
            $table->enum('calculation_type', ['fixed', 'percentage', 'manual'])->default('fixed');
            $table->enum('percentage_of', ['basic_salary', 'gross_salary'])->nullable();
            $table->decimal('default_amount', 14, 2)->default(0);
            $table->decimal('default_percentage', 8, 4)->default(0);
            $table->boolean('is_taxable')->default(false);
            $table->boolean('is_required')->default(false);
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_system')->default(false);
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index(['status', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_components');
    }
};
