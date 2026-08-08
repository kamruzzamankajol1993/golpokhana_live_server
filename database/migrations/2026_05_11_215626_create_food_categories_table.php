<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('parent_category_id')->nullable(); // সাব-ক্যাটাগরির জন্য
            $table->string('image')->nullable();
            $table->string('slug')->unique(); // URL এর জন্য ইউনিক স্লাগ
            $table->boolean('status')->default(true); // true = Active, false = Inactive
            $table->timestamps();

            // Self-referencing Foreign Key
            $table->foreign('parent_category_id')->references('id')->on('food_categories')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_categories');
    }
};
