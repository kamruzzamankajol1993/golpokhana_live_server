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
        Schema::create('pos_sessions', function (Blueprint $table) {
           $table->id();
            $table->unsignedBigInteger('user_id'); // যিনি সেশন শুরু করেছেন

            $table->string('weekday', 20); // যেমন: Monday, Tuesday
            $table->dateTime('start_time'); // কাজ শুরুর সময়
            $table->dateTime('end_time')->nullable(); // কাজ শেষের সময়
            $table->string('duration')->nullable(); // কাজের মোট সময় (যেমন: 10hr 49min)

            $table->enum('status', ['Open', 'Closed'])->default('Open');

            // সামারি ডেটা (End Session করার সময় এগুলো আপডেট হবে)
            $table->decimal('sales_total', 10, 2)->default(0);
            $table->decimal('service_charge', 10, 2)->default(0);
            $table->decimal('vat_total', 10, 2)->default(0);
            $table->decimal('grand_total', 10, 2)->default(0);

            // পেমেন্ট মেথড অনুযায়ী ইনকাম (JSON ফরম্যাট)
            $table->json('incomes_summary')->nullable();

            $table->timestamps();

            // Foreign Key Constraint
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_sessions');
    }
};
