<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cuisine_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('origin_country')->nullable();
            $table->text('description')->nullable();
            $table->string('slug')->unique(); // URL এর জন্য ইউনিক স্লাগ
            $table->boolean('status')->default(true); // true = Active, false = Inactive
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cuisine_types');
    }
};
