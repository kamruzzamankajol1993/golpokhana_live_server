<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('code', 30)->nullable()->unique();
            $table->decimal('days_per_year', 6, 2)->default(0);
            $table->boolean('is_paid')->default(true);
            $table->boolean('allow_carry_forward')->default(false);
            $table->decimal('max_carry_forward_days', 6, 2)->default(0);
            $table->boolean('requires_document')->default(false);
            $table->string('color', 20)->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_system')->default(false);
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->index(['status', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_types');
    }
};
