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
        Schema::table('orders', function (Blueprint $table) {
            // due কলামের পর নতুন ৪টি কলাম যুক্ত করা হচ্ছে
            $table->decimal('total_paid_amount', 10, 2)->default(0)->after('due');
            $table->decimal('paid_in_cash', 10, 2)->default(0)->after('total_paid_amount');
            $table->decimal('paid_in_card', 10, 2)->default(0)->after('paid_in_cash');
            $table->decimal('paid_in_mfc', 10, 2)->default(0)->after('paid_in_card');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // রোলব্যাক করার সময় কলামগুলো রিমুভ করার জন্য
            $table->dropColumn([
                'total_paid_amount',
                'paid_in_cash',
                'paid_in_card',
                'paid_in_mfc'
            ]);
        });
    }
};
