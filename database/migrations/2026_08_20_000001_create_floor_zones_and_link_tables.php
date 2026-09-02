<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('floor_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::table('tables', function (Blueprint $table) {
            $table->foreignId('floor_zone_id')->nullable()->after('zone_id')->constrained('floor_zones')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tables', function (Blueprint $table) {
            $table->dropForeign(['floor_zone_id']);
            $table->dropColumn('floor_zone_id');
        });
        Schema::dropIfExists('floor_zones');
    }
};
