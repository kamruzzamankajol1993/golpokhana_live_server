<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('food_items', function (Blueprint $table) {
            $table->boolean('is_draft')->default(false)->after('is_takeaway'); // ড্রাফট স্ট্যাটাস
            $table->integer('point')->nullable()->after('is_draft'); // পয়েন্ট সিস্টেমের জন্য
        });
    }

    public function down(): void
    {
        Schema::table('food_items', function (Blueprint $table) {
            $table->dropColumn(['is_draft', 'point']);
        });
    }
};
