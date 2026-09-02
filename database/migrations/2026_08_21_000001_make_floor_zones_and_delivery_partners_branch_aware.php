<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('branches')) return;
        $mainBranchId = (int) (DB::table('branches')->where('is_main',1)->value('id') ?: DB::table('branches')->value('id'));
        if ($mainBranchId < 1) return;

        $this->repairBranchOwnedLookup('floor_zones', 'tables', 'floor_zone_id', $mainBranchId);
        $this->repairBranchOwnedLookup('delivery_partners', 'orders', 'delivery_partner_id', $mainBranchId);
        $this->ensureLegacyDeliveryPartnersPerBranch();
    }

    private function repairBranchOwnedLookup(string $lookupTable, string $ownerTable, string $foreignKey, int $mainBranchId): void
    {
        if (!Schema::hasTable($lookupTable)) return;

        if (!Schema::hasColumn($lookupTable, 'branch_id')) {
            Schema::table($lookupTable, function (Blueprint $table) use ($lookupTable) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('id');
                $table->index('branch_id', $lookupTable . '_branch_idx');
                $table->foreign('branch_id', $lookupTable . '_branch_id_foreign')->references('id')->on('branches')->restrictOnDelete();
            });
        }

        if (Schema::hasTable($ownerTable) && Schema::hasColumn($ownerTable, $foreignKey) && Schema::hasColumn($ownerTable, 'branch_id')) {
            foreach (DB::table($lookupTable)->orderBy('id')->get() as $lookup) {
                $branchIds = DB::table($ownerTable)->where($foreignKey, $lookup->id)->whereNotNull('branch_id')->distinct()->pluck('branch_id')->values();
                if ($branchIds->isEmpty()) {
                    DB::table($lookupTable)->where('id',$lookup->id)->update(['branch_id' => $lookup->branch_id ?: $mainBranchId]);
                    continue;
                }

                $first = (int) $branchIds->first();
                DB::table($lookupTable)->where('id',$lookup->id)->update(['branch_id'=>$first]);
                foreach ($branchIds->slice(1) as $branchId) {
                    $copy = (array) $lookup;
                    unset($copy['id']);
                    $copy['branch_id'] = (int) $branchId;
                    $copy['created_at'] = $copy['created_at'] ?? now();
                    $copy['updated_at'] = now();
                    $newId = DB::table($lookupTable)->insertGetId($copy);
                    DB::table($ownerTable)->where($foreignKey,$lookup->id)->where('branch_id',$branchId)->update([$foreignKey=>$newId]);
                }
            }
        } else {
            DB::table($lookupTable)->whereNull('branch_id')->update(['branch_id'=>$mainBranchId]);
        }

        DB::table($lookupTable)->whereNull('branch_id')->update(['branch_id'=>$mainBranchId]);
    }

    private function ensureLegacyDeliveryPartnersPerBranch(): void
    {
        if (!Schema::hasTable('delivery_partners') || !Schema::hasTable('orders') || !Schema::hasTable('branches')) return;
        if (!Schema::hasColumn('delivery_partners', 'branch_id') || !Schema::hasColumn('orders', 'delivery_partner_id')) return;

        $map = [
            'inhouse' => 'In-house Delivery',
            'foodpanda' => 'Foodpanda',
            'foodi' => 'Foodi',
            'pathao_food' => 'Pathao Food',
        ];

        foreach (DB::table('branches')->pluck('id') as $branchId) {
            foreach ($map as $legacyKey => $name) {
                $partnerId = DB::table('delivery_partners')
                    ->where('branch_id', $branchId)
                    ->where('name', $name)
                    ->value('id');

                if (!$partnerId) {
                    $partnerId = DB::table('delivery_partners')->insertGetId([
                        'branch_id' => $branchId,
                        'name' => $name,
                        'status' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                if (Schema::hasColumn('orders', 'delivery_partner')) {
                    DB::table('orders')
                        ->where('branch_id', $branchId)
                        ->whereNull('delivery_partner_id')
                        ->whereIn('delivery_partner', [$legacyKey, $name])
                        ->update(['delivery_partner_id' => $partnerId]);
                }
            }
        }
    }

    public function down(): void {}
};
