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
        Schema::create('tables', function (Blueprint $table) {
            $table->id();
            $table->string('table_number'); // রিকুইরিড
            $table->integer('seating_capacity'); // রিকুইরিড
            $table->unsignedBigInteger('zone_id'); // রিকুইরিড (zone টেবিল থেকে)
            $table->string('initial_status')->default('Available'); // স্ট্রিং (Available, Occupied ইত্যাদি)
            $table->text('notes')->nullable();

            // Foreign Key Constraint
            $table->foreign('zone_id')->references('id')->on('zones')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tables');
    }
};
