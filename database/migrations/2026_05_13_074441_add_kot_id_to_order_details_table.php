<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_details', function (Blueprint $table) {
            // order_id এর পরে kot_id কলাম যোগ করা হচ্ছে
            $table->unsignedBigInteger('order_kot_id')->nullable()->after('order_id');

            $table->foreign('order_kot_id')->references('id')->on('order_kots')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('order_details', function (Blueprint $table) {
            $table->dropForeign(['order_kot_id']);
            $table->dropColumn('order_kot_id');
        });
    }
};
