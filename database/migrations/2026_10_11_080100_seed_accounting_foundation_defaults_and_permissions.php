<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration {
    public function up(): void
    {
        $now = now();
        DB::table('accounting_settings')->updateOrInsert(['id'=>1], [
            'capture_enabled'=>false,'posting_mode'=>'manual','valuation_method'=>'weighted_average',
            'created_at'=>$now,'updated_at'=>$now,
        ]);
        $accounts = [
            ['1000','Assets','asset','debit',1,null],
            ['1100','Cash on Hand','asset','debit',0,'1000'],
            ['1110','Petty Cash','asset','debit',0,'1000'],
            ['1120','Bank Account','asset','debit',0,'1000'],
            ['1130','Card Clearing','asset','debit',0,'1000'],
            ['1140','MFS Clearing','asset','debit',0,'1000'],
            ['1200','Accounts Receivable','asset','debit',0,'1000'],
            ['1300','Store Inventory','asset','debit',0,'1000'],
            ['1310','Kitchen Inventory','asset','debit',0,'1000'],
            ['2000','Liabilities','liability','credit',1,null],
            ['2100','Accounts Payable','liability','credit',0,'2000'],
            ['2110','Customer Advance','liability','credit',0,'2000'],
            ['2120','VAT Payable','liability','credit',0,'2000'],
            ['2130','Tips Payable','liability','credit',0,'2000'],
            ['2200','Salary Payable','liability','credit',0,'2000'],
            ['3000','Equity','equity','credit',1,null],
            ['3100','Owner Capital','equity','credit',0,'3000'],
            ['3200','Retained Earnings','equity','credit',0,'3000'],
            ['4000','Income','income','credit',1,null],
            ['4100','Food Sales','income','credit',0,'4000'],
            ['4200','Delivery Income','income','credit',0,'4000'],
            ['4300','Service Charge Income','income','credit',0,'4000'],
            ['4900','Sales Discounts','contra_revenue','debit',0,null],
            ['5000','Cost of Goods Sold','cogs','debit',0,null],
            ['6000','Expenses','expense','debit',1,null],
            ['6100','Salary Expense','expense','debit',0,'6000'],
            ['6200','Rent Expense','expense','debit',0,'6000'],
            ['6300','Utilities Expense','expense','debit',0,'6000'],
            ['6400','Wastage Expense','expense','debit',0,'6000'],
            ['6500','Bank & Card Fees','expense','debit',0,'6000'],
            ['6900','Cash Variance / Rounding','expense','debit',0,'6000'],
        ];
        $ids=[];
        foreach ($accounts as [$code,$name,$type,$normal,$header,$parent]) {
            $ids[$code] = DB::table('chart_of_accounts')->insertGetId([
                'code'=>$code,'name'=>$name,'type'=>$type,'normal_balance'=>$normal,
                'is_header'=>(bool)$header,'parent_id'=>$parent ? $ids[$parent] : null,
                'is_control'=>in_array($code,['1200','2100','2110'],true),
                'is_active'=>true,'created_at'=>$now,'updated_at'=>$now,
            ]);
        }
        foreach (['SALES'=>'Sales','PUR'=>'Purchase','CASH'=>'Cash','BANK'=>'Bank',
                 'MISC'=>'Miscellaneous','ADJ'=>'Adjustment','DEP'=>'Depreciation'] as $code=>$name) {
            DB::table('accounting_journals')->insert([
                'code'=>$code,'name'=>$name,'type'=>strtolower($name),'is_active'=>true,
                'created_at'=>$now,'updated_at'=>$now,
            ]);
        }
        if (!Schema::hasTable('permissions') || !Schema::hasTable('roles')) return;
        $permissionNames = ['accounting-view','accounting-settings','accounting-coa',
            'accounting-period','accounting-mapping','accounting-event-view','accounting-event-process',
            'accounting-journal-create','accounting-journal-approve','accounting-journal-post',
            'accounting-journal-reverse'];
        foreach ($permissionNames as $permission) {
            DB::table('permissions')->updateOrInsert(['name'=>$permission,'guard_name'=>'web'],
                ['group_name'=>'Accounting','updated_at'=>$now,'created_at'=>$now]);
        }
        foreach (['Accountant','Senior Accountant','Accounting Auditor'] as $name) {
            DB::table('roles')->updateOrInsert(['name'=>$name,'guard_name'=>'web'],
                ['updated_at'=>$now,'created_at'=>$now]);
        }
        $ids = DB::table('permissions')->whereIn('name',$permissionNames)->pluck('id','name');
        foreach (DB::table('roles')->where('guard_name','web')->get() as $role) {
            $names=match ($role->name) {
                'Super Admin','Owner','Admin' => $permissionNames,
                'Senior Accountant' => array_values(array_diff($permissionNames,['accounting-settings'])),
                'Accountant' => ['accounting-view','accounting-event-view','accounting-event-process',
                    'accounting-journal-create','accounting-coa'],
                'Accounting Auditor' => ['accounting-view','accounting-event-view'],
                default => [],
            };
            foreach ($names as $name) DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id'=>$ids[$name],'role_id'=>$role->id,
            ]);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
    public function down(): void
    {
        // Do not remove users' journal or accounting records via rollback of setup defaults.
        // Schema rollback is separately controlled by the preceding foundation migration.
    }
};
