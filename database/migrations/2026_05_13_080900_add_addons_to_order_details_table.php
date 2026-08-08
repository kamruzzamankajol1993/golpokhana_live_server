<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_details', function (Blueprint $table) {
            // JSON টাইপের কলাম তৈরি করা হচ্ছে যাতে একসাথে অনেকগুলো অ্যাড-অন সেভ করা যায়
            $table->json('addons')->nullable()->after('product_name');
        });
    }

    public function down(): void
    {
        Schema::table('order_details', function (Blueprint $table) {
            $table->dropColumn('addons');
        });
    }
};
