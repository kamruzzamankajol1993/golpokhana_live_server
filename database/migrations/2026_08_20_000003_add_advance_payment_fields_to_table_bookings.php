<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('table_bookings', function (Blueprint $table) {
            $table->decimal('advance_amount', 10, 2)->default(0)->after('special_request');
            $table->string('advance_payment_method')->nullable()->after('advance_amount');
            $table->string('advance_payment_reference')->nullable()->after('advance_payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('table_bookings', function (Blueprint $table) {
            $table->dropColumn(['advance_amount','advance_payment_method','advance_payment_reference']);
        });
    }
};
