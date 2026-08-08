<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // user_id এর পরে waiter_id কলামটি যুক্ত করা হচ্ছে
            $table->unsignedBigInteger('waiter_id')->nullable()->after('user_id');

            // যদি আপনার waiters নামে আলাদা কোনো টেবিল থাকে, তবে নিচের লাইনটি আনকমেন্ট করতে পারেন
            // $table->foreign('waiter_id')->references('id')->on('waiters')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // $table->dropForeign(['waiter_id']); // যদি উপরের ফরেন কি ব্যবহার করেন
            $table->dropColumn('waiter_id');
        });
    }
};
