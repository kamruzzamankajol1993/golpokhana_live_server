<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kitchen_request_zero_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('kitchen_request_id')->constrained('kitchen_requests')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('idempotency_key', 80)->unique();
            $table->json('items');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kitchen_request_zero_assignments');
    }
};
