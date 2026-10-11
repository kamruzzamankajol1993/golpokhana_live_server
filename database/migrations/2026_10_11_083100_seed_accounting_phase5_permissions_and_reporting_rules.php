<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration {
    public function up(): void
    {
        $now=now();
        foreach (['1100','1110','1120'] as $code) {
            $id=DB::table('chart_of_accounts')->where('code',$code)->value('id');
            if ($id) DB::table('accounting_cash_flow_rules')->insertOrIgnore([
                'account_id'=>$id,'activity'=>'CASH','created_at'=>$now,'updated_at'=>$now]);
        }
        // Practical default activities; finance can change every classification in Report Rules.
        // Card/MFS clearing is not CASH: settlement receipts are operating cash movements.
        $activities=[
            'OPERATING'=>['1130','1140','1150','1200','1300','1310','2100','2110','2120','2130',
                '2200','2160','2170','1400','4100','4200','4300','4400','4900','5000',
                '6100','6110','6200','6300','6400','6500','6550','6900'],
            'INVESTING'=>['1160','1170','1500','1510'],
            'FINANCING'=>['2300','3100','3200'],
        ];
        foreach($activities as $activity=>$codes) foreach($codes as $code) {
            $id=DB::table('chart_of_accounts')->where('code',$code)->where('is_header',false)->value('id');
            if ($id) DB::table('accounting_cash_flow_rules')->insertOrIgnore([
                'account_id'=>$id,'activity'=>$activity,'created_at'=>$now,'updated_at'=>$now]);
        }
        $vat=DB::table('chart_of_accounts')->where('code','2120')->value('id');
        if ($vat) DB::table('accounting_tax_report_accounts')->insertOrIgnore([
            'account_id'=>$vat,'tax_type'=>'VAT','created_at'=>$now,'updated_at'=>$now]);
        if (!Schema::hasTable('permissions') || !Schema::hasTable('roles')) return;
        $assign=[
            'accounting-financial-reports'=>['Super Admin','Admin','Owner','Senior Accountant','Accountant','Accounting Auditor'],
            'accounting-budget'=>['Super Admin','Admin','Owner','Senior Accountant','Accountant'],
            'accounting-budget-approve'=>['Super Admin','Admin','Owner','Senior Accountant'],
            'accounting-cutover'=>['Super Admin','Admin','Owner','Senior Accountant'],
            'accounting-cutover-approve'=>['Super Admin','Admin','Owner','Senior Accountant'],
            'accounting-close'=>['Super Admin','Admin','Owner','Senior Accountant'],
            'accounting-reopen'=>['Super Admin','Owner','Senior Accountant'],
            'accounting-year-end'=>['Super Admin','Owner','Senior Accountant'],
            'accounting-report-mapping'=>['Super Admin','Admin','Owner','Senior Accountant'],
        ];
        foreach ($assign as $permission=>$roles) {
            DB::table('permissions')->updateOrInsert(['name'=>$permission,'guard_name'=>'web'],
                ['group_name'=>'Accounting','updated_at'=>$now,'created_at'=>$now]);
            $pid=DB::table('permissions')->where('name',$permission)->where('guard_name','web')->value('id');
            foreach (DB::table('roles')->whereIn('name',$roles)->where('guard_name','web')->pluck('id') as $rid) {
                DB::table('role_has_permissions')->insertOrIgnore(['role_id'=>$rid,'permission_id'=>$pid]);
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
    public function down(): void { /* Never purge approved reporting policies or finance sign-offs. */ }
};
