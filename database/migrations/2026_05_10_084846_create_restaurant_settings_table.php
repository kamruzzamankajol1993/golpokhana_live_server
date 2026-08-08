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
        Schema::create('restaurant_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
$table->string('phone')->nullable();
$table->string('email')->nullable();
$table->string('website')->nullable();
$table->text('address')->nullable();
$table->time('opening_time')->nullable();
$table->time('closing_time')->nullable();
$table->string('currency')->nullable();
$table->string('icon_name')->nullable(); // নতুন কলাম
$table->string('logo')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('restaurant_settings');
    }
};
