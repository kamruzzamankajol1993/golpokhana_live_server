<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\BranchModeSetting;
use Illuminate\Database\Seeder;

class MainBranchSeeder extends Seeder
{
    public function run(): void
    {
        Branch::query()->firstOrCreate(
            ['code' => 'MAIN'],
            [
                'name' => 'Main Branch',
                'slug' => 'main-branch',
                'is_main' => true,
                'status' => true,
            ]
        );

        BranchModeSetting::query()->firstOrCreate(
            ['id' => 1],
            ['mode' => BranchModeSetting::MODE_MULTIPLE, 'multi_branch_activated_at' => now()]
        );
    }
}
