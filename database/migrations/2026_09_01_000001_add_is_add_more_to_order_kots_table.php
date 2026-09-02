<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('order_kots', 'is_add_more')) {
            Schema::table('order_kots', function (Blueprint $table) {
                $table->boolean('is_add_more')->default(false)->after('kitchen_status')->index();
            });
        }

        // Backfill existing orders: the first surviving KOT is the original order KOT;
        // every later KOT represents food appended through the Add More workflow.
        $kotsByOrder = DB::table('order_kots')
            ->select('id', 'order_id')
            ->orderBy('order_id')
            ->orderBy('id')
            ->get()
            ->groupBy('order_id');

        foreach ($kotsByOrder as $kots) {
            foreach ($kots->values() as $index => $kot) {
                DB::table('order_kots')
                    ->where('id', $kot->id)
                    ->update(['is_add_more' => $index > 0 ? 1 : 0]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('order_kots', 'is_add_more')) {
            Schema::table('order_kots', function (Blueprint $table) {
                $table->dropColumn('is_add_more');
            });
        }
    }
};
