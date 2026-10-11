<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration {
    public function up(): void
    {
        $now=now();
        $accounts=[
            ['1160','Employee Salary Advances','asset','debit','1000'],
            ['1170','Employee Loans Receivable','asset','debit','1000'],
            ['1400','Prepaid Expenses','asset','debit','1000'],
            ['1500','Restaurant Fixed Assets','asset','debit','1000'],
            ['1510','Accumulated Depreciation','asset','credit','1000'],
            ['2160','Payroll Deduction Clearing','liability','credit','2000'],
            ['2170','Deferred Income','liability','credit','2000'],
            ['2300','Company Loans Payable','liability','credit','2000'],
            ['6110','Depreciation Expense','expense','debit','6000'],
            ['4400','Employee Loan Interest Income','income','credit','4000'],
            ['6550','Company Loan Interest Expense','expense','debit','6000'],
        ];
        foreach ($accounts as [$code,$name,$type,$normal,$parent]) {
            DB::table('chart_of_accounts')->insertOrIgnore([
                'code'=>$code,'name'=>$name,'type'=>$type,'normal_balance'=>$normal,
                'parent_id'=>DB::table('chart_of_accounts')->where('code',$parent)->value('id'),
                'is_header'=>false,'is_control'=>false,'is_active'=>true,
                'created_at'=>$now,'updated_at'=>$now,
            ]);
        }
        $mappings=[
            ['payroll_items','PAYROLL_ACCRUED','MISC',[
                ['Payroll gross cost','debit','6100','gross'],
                ['Advance recovery','credit','1160','advance_recovery'],
                ['Loan recovery','credit','1170','loan_recovery'],
                ['Other deductions pending classification','credit','2160','other_deductions'],
                ['Employee net salary payable','credit','2200','net'],
            ]],
            ['payroll_payments','PAYROLL_PAID','CASH',[
                ['Salary payable settled','debit','2200','salary_paid'],
                ['Cash disbursement','credit','1100','cash_paid'],
                ['Bank disbursement','credit','1120','bank_paid'],
                ['MFS disbursement','credit','1150','mfs_paid'],
            ]],
            ['salary_advances','ADVANCE_DISBURSED','CASH',[
                ['Employee advance asset','debit','1160','principal'],
                ['Cash disbursement','credit','1100','cash_paid'],
                ['Bank disbursement','credit','1120','bank_paid'],
                ['MFS disbursement','credit','1150','mfs_paid'],
            ]],
            ['employee_loans','LOAN_DISBURSED','CASH',[
                ['Employee loan asset','debit','1170','principal'],
                ['Cash disbursement','credit','1100','cash_paid'],
                ['Bank disbursement','credit','1120','bank_paid'],
                ['MFS disbursement','credit','1150','mfs_paid'],
            ]],
            ['salary_advance_repayments','ADVANCE_REPAID','CASH',[
                ['Cash received','debit','1100','cash_received'],
                ['Bank received','debit','1120','bank_received'],
                ['MFS received','debit','1140','mfs_received'],
                ['Advance asset cleared','credit','1160','principal'],
            ]],
            ['loan_repayments','LOAN_REPAID','CASH',[
                ['Cash received','debit','1100','cash_received'],
                ['Bank received','debit','1120','bank_received'],
                ['MFS received','debit','1140','mfs_received'],
                ['Interest income','credit','4400','interest'],
                ['Employee loan principal cleared','credit','1170','principal'],
            ]],
        ];
        foreach ($mappings as [$src,$type,$journalCode,$rules]) {
            $journal=DB::table('accounting_journals')->where('code',$journalCode)->value('id');
            DB::table('account_mappings')->insertOrIgnore(['source_type'=>$src,'event_type'=>$type,
                'name'=>str_replace('_',' ',$type),'journal_id'=>$journal,'is_active'=>false,
                'requires_approval'=>true,'created_at'=>$now,'updated_at'=>$now]);
            $mappingId=DB::table('account_mappings')->where('source_type',$src)->where('event_type',$type)->value('id');
            if (DB::table('account_mapping_rules')->where('mapping_id',$mappingId)->exists()) continue;
            foreach ($rules as $i=>[$role,$direction,$code,$key]) {
                DB::table('account_mapping_rules')->insert(['mapping_id'=>$mappingId,'role'=>$role,
                    'sort_order'=>$i+1,'direction'=>$direction,'account_id'=>DB::table('chart_of_accounts')->where('code',$code)->value('id'),
                    'amount_key'=>$key,'created_at'=>$now,'updated_at'=>$now]);
            }
        }
        foreach (['Immediate'=>0,'7 Days'=>7,'15 Days'=>15,'30 Days'=>30] as $name=>$days) {
            DB::table('payment_terms')->insertOrIgnore(['name'=>$name,'days_due'=>$days,'grace_days'=>0,
                'is_active'=>true,'created_at'=>$now,'updated_at'=>$now]);
        }
        foreach (['KITCHEN'=>'Kitchen','DINING'=>'Dining','DELIVERY'=>'Delivery','MARKETING'=>'Marketing',
            'OFFICE'=>'Office','EVENTS'=>'Events'] as $code=>$name) {
            DB::table('cost_centers')->insertOrIgnore(['code'=>$code,'name'=>$name,'is_active'=>true,
                'created_at'=>$now,'updated_at'=>$now]);
        }
        foreach (['Kitchen Equipment','Furniture & Fixtures','Refrigeration','POS Equipment','Other Equipment'] as $name) {
            DB::table('asset_categories')->insertOrIgnore(['name'=>$name,'is_active'=>true,
                'created_at'=>$now,'updated_at'=>$now]);
        }
        DB::table('accounting_asset_policies')->insertOrIgnore(['id'=>1,'is_approved'=>false,'created_at'=>$now,'updated_at'=>$now]);
        if (!DB::getSchemaBuilder()->hasTable('permissions')) return;
        $roleNames=['Super Admin','Admin','Owner','Senior Accountant'];
        foreach (['accounting-payroll','accounting-due-terms','accounting-bank-match',
            'accounting-cost-centers','accounting-deferrals','accounting-assets','accounting-company-loans'] as $name) {
            DB::table('permissions')->updateOrInsert(['name'=>$name,'guard_name'=>'web'],
                ['group_name'=>'Accounting','created_at'=>$now,'updated_at'=>$now]);
            $id=DB::table('permissions')->where('name',$name)->where('guard_name','web')->value('id');
            $roles=$roleNames;
            if (in_array($name,['accounting-due-terms','accounting-payroll'],true)) $roles[]='Accountant';
            foreach (DB::table('roles')->whereIn('name',$roles)->pluck('id') as $rid) {
                DB::table('role_has_permissions')->insertOrIgnore(['role_id'=>$rid,'permission_id'=>$id]);
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
    public function down(): void { /* Financial mappings, configurations and posted journals are never purged. */ }
};
