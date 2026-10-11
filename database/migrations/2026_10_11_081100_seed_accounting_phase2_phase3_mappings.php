<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $now=now();
        foreach ([
            ['1150','MFS Wallet / Outgoing Payments','asset','debit'],
            ['2140','Withheld VDS / TDS Payable','liability','credit'],
            ['2150','Accrued Expenses Payable','liability','credit'],
            ['4910','Sales Returns and Allowances','contra_revenue','debit'],
            ['2141','TDS Withheld Payable','liability','credit'],
            ['2142','VDS Withheld Payable','liability','credit'],
            ['6600','General Operating Expenses','expense','debit'],
            ['6700','Inventory Variance / Adjustments','expense','debit'],
        ] as [$code,$name,$type,$normal]) {
            // Contra-revenue is a debit-normal reduction of income, NEVER a child of assets.
            $parentCode=match($type) {'expense'=>'6000','liability'=>'2000','asset'=>'1000',default=>null};
            $parent=$parentCode ? DB::table('chart_of_accounts')->where('code',$parentCode)->value('id') : null;
            DB::table('chart_of_accounts')->updateOrInsert(['code'=>$code],[
                'name'=>$name,'type'=>$type,'normal_balance'=>$normal,'parent_id'=>$parent,
                'is_header'=>false,'is_control'=>false,'is_active'=>true,'created_at'=>$now,'updated_at'=>$now,
            ]);
        }
        // Intentional safeguard: ALL mappings start inactive and require independent review.
        // Authorize mappings, tax policy and fiscal periods before enabling auto posting.
        $items=[
            ['orders','SALES_COMPLETED','SALES',[
                ['Cash received','debit','1100','cash_paid'],['Card clearing','debit','1130','card_paid'],
                ['MFS clearing','debit','1140','mfs_paid'],['Customer receivable','debit','1200','ar_due'],
                ['Booking advance applied','debit','2110','advance_used'],['Discounts','debit','4900','discount_total'],
                ['Gross food sales','credit','4100','sales_gross'],['Delivery income','credit','4200','delivery'],
                ['Service charge','credit','4300','service'],['VAT payable','credit','2120','vat'],
                ['POS Rounding debit','debit','6900','rounding_dr'],
                ['POS Rounding credit','credit','6900','rounding_cr'],
            ]],
            ['order_due_payments','AR_SETTLEMENT','CASH',[
                ['Cash received','debit','1100','cash_paid'],['Card clearing','debit','1130','card_paid'],
                ['MFS clearing','debit','1140','mfs_paid'],['Accounts receivable cleared','credit','1200','ar_cleared'],
            ]],
            ['table_bookings','BOOKING_ADVANCE_RECEIVED','CASH',[
                ['Cash received','debit','1100','cash_paid'],['Card clearing','debit','1130','card_paid'],
                ['MFS clearing','debit','1140','mfs_paid'],['Customer deposit liability','credit','2110','advance_received'],
            ]],
            ['orders','TIPS_RECEIVED','CASH',[
                ['Cash tips','debit','1100','cash_tips'],['Tips payable','credit','2130','tips_payable'],
            ]],
            ['purchases','PURCHASE_RECEIVED','PUR',[
                ['Store inventory','debit','1300','inventory_received'],['Supplier payable','credit','2100','ap_created'],
            ]],
            ['vendor_payments','AP_SETTLEMENT','PUR',[
                ['Supplier payable settled','debit','2100','ap_cleared'],
                ['Cash outgoing','credit','1100','cash_paid'],['Bank outgoing','credit','1120','card_paid'],
                ['MFS wallet outgoing','credit','1150','mfs_paid'],
            ]],
            ['stock_movements','INTERNAL_TRANSFER','ADJ',[
                ['Kitchen inventory','debit','1310','verified_cost'],['Store inventory','credit','1300','verified_cost'],
            ]],
            ['stock_movements','INTERNAL_RETURN','ADJ',[
                ['Store inventory','debit','1300','verified_cost'],['Kitchen inventory','credit','1310','verified_cost'],
            ]],
            ['stock_movements','COGS_VERIFIED','ADJ',[
                ['Cost of sales','debit','5000','verified_cost'],['Kitchen inventory used','credit','1310','verified_cost'],
            ]],
            ['stock_movements','STORE_WASTAGE_VERIFIED','ADJ',[
                ['Wastage expense','debit','6400','verified_cost'],['Store inventory','credit','1300','verified_cost'],
            ]],
            ['stock_movements','WASTAGE_VERIFIED','ADJ',[
                ['Wastage expense','debit','6400','verified_cost'],['Store/kitchen inventory','credit','1310','verified_cost'],
            ]],
            ['negative_inventory_cost_pending','PENDING_COST_VERIFIED','ADJ',[
                ['Confirmed cost','debit','5000','verified_cost'],['Cost clearing (approved)','credit','1310','verified_cost'],
            ]],
        ];
        foreach ($items as [$src,$event,$journalCode,$rules]) {
            $journal=DB::table('accounting_journals')->where('code',$journalCode)->value('id');
            DB::table('account_mappings')->insertOrIgnore([
                'source_type'=>$src,'event_type'=>$event,'name'=>str_replace('_',' ',$event),
                'journal_id'=>$journal,'is_active'=>false,'requires_approval'=>true,
                'created_at'=>$now,'updated_at'=>$now,
            ]);
            $mapping=DB::table('account_mappings')->where('source_type',$src)->where('event_type',$event)->first();
            if (DB::table('account_mapping_rules')->where('mapping_id',$mapping->id)->exists()) continue;
            foreach ($rules as $i=>[$role,$direction,$accountCode,$amountKey]) {
                $account=DB::table('chart_of_accounts')->where('code',$accountCode)->value('id');
                DB::table('account_mapping_rules')->insert(['mapping_id'=>$mapping->id,'sort_order'=>$i+1,'role'=>$role,
                    'direction'=>$direction,'account_id'=>$account,'amount_key'=>$amountKey,
                    'created_at'=>$now,'updated_at'=>$now]);
            }
        }
        foreach ([['Rent','6200'],['Utilities','6300'],['General Operating','6600'],['Wastage','6400']] as [$name,$code]) {
            DB::table('expense_categories')->insertOrIgnore(['name'=>$name,'expense_account_id'=>DB::table('chart_of_accounts')->where('code',$code)->value('id'),
                'is_active'=>true,'created_at'=>$now,'updated_at'=>$now]);
        }
        foreach ([['Cash Drawer','Cash','1100'],['Bank','Bank','1120'],['Card Clearing','Card','1130'],['MFS Clearing','MFS','1140']] as [$name,$method,$code]) {
            DB::table('payment_accounts')->insert(['name'=>$name,'method'=>$method,
                'gl_account_id'=>DB::table('chart_of_accounts')->where('code',$code)->value('id'),
                'active'=>true,'created_at'=>$now,'updated_at'=>$now]);
        }
        if (DB::getSchemaBuilder()->hasTable('permissions')) {
            foreach (['accounting-voucher','accounting-expense','accounting-cost-review','accounting-reconcile'] as $name) {
                DB::table('permissions')->updateOrInsert(['name'=>$name,'guard_name'=>'web'],['group_name'=>'Accounting','created_at'=>$now,'updated_at'=>$now]);
                $id=DB::table('permissions')->where('name',$name)->where('guard_name','web')->value('id');
                $roles=['Super Admin','Admin','Owner','Senior Accountant'];
                if (in_array($name,['accounting-voucher','accounting-expense','accounting-cost-review'],true)) {
                    $roles[]='Accountant';
                }
                foreach (DB::table('roles')->whereIn('name',$roles)->pluck('id') as $roleId) {
                    DB::table('role_has_permissions')->insertOrIgnore(['permission_id'=>$id,'role_id'=>$roleId]);
                }
            }
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
    public function down(): void { /* Non-destructive: financial setup and mappings may already be in use. */ }
};
