<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            ['name'=>'report-kot-view','group_name'=>'Reports'],
            ['name'=>'report-pos-session-view','group_name'=>'Reports'],
            ['name'=>'report-branch-summary-view','group_name'=>'Reports'],
            ['name'=>'report-audit-log-view','group_name'=>'Reports'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name'=>$permission['name'],
                'guard_name'=>'web',
            ],[
                'group_name'=>$permission['group_name'],
            ]);
        }
    }

    public function down(): void
    {
        Permission::whereIn('name', [
            'report-kot-view',
            'report-pos-session-view',
            'report-branch-summary-view',
            'report-audit-log-view',
        ])->delete();
    }
};
