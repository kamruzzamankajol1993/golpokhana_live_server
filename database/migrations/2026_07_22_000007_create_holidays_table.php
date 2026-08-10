<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->date('holiday_date');
            $table->enum('holiday_type', ['public', 'company', 'special'])->default('company');
            $table->boolean('is_paid')->default(true);
            $table->text('description')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->unique(['holiday_date', 'name']);
            $table->index(['holiday_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
