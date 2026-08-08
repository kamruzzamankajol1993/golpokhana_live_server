<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_items', function (Blueprint $table) {
            $table->id();

            // 01. Basic Information
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('short_description', 120)->nullable();
            $table->text('description')->nullable();

            // 02. Classification (Foreign Keys)
            $table->unsignedBigInteger('food_category_id')->nullable();
            $table->unsignedBigInteger('sub_category_id')->nullable();
            $table->unsignedBigInteger('cuisine_type_id')->nullable();
            $table->unsignedBigInteger('course_type_id')->nullable();
            $table->string('spice_level')->nullable(); // Not Spicy, Mild, Medium, etc.
            $table->string('serving_size')->nullable(); // e.g. 1 plate / 350g

            // 03. Pricing & Tax
            $table->decimal('base_price', 10, 2);
            $table->decimal('discount_price', 10, 2)->nullable();
            $table->string('tax_rate')->nullable();
            $table->integer('preparation_time')->nullable(); // In minutes
            $table->integer('calories')->nullable(); // kcal

            // 04. Allergen Information
            $table->json('allergens')->nullable(); // Array আকারে সেভ হবে
            $table->string('allergen_notes')->nullable();

            // 06. Item Photo (Main Image)
            $table->string('main_image')->nullable();

            // 07. Status & Visibility
            $table->boolean('is_available')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_chefs_special')->default(false);
            $table->boolean('is_dine_in')->default(true);
            $table->boolean('is_takeaway')->default(true);

            // 08. Availability Schedule
            $table->json('active_days')->nullable(); // Array আকারে সেভ হবে (Mon, Tue...)
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();

            $table->timestamps();

            // Constraints
            $table->foreign('food_category_id')->references('id')->on('food_categories')->onDelete('set null');
            $table->foreign('sub_category_id')->references('id')->on('food_categories')->onDelete('set null');
            $table->foreign('cuisine_type_id')->references('id')->on('cuisine_types')->onDelete('set null');
            $table->foreign('course_type_id')->references('id')->on('course_types')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_items');
    }
};
