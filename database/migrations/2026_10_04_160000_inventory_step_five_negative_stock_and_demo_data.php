<?php

use App\Models\StockMovement;
use App\Services\Inventory\StockMovementService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private array $demoFoodSlugs = [
        'demo-chicken-biryani','demo-beef-tehari','demo-chicken-fried-rice','demo-beef-fried-rice','demo-chicken-curry',
        'demo-beef-curry','demo-chicken-khichuri','demo-beef-khichuri','demo-egg-fried-rice','demo-chicken-noodles',
        'demo-beef-noodles','demo-french-fries','demo-chicken-burger','demo-beef-burger','demo-milk-tea',
    ];

    public function up(): void
    {
        $this->addSettingAndPermission();
        $userIds = $this->seedApprovers();
        $this->seedApprovalSettings($userIds);
        $ingredientIds = $this->seedIngredients();
        $foodIds = $this->seedFoodsAndRecipes($ingredientIds, $userIds[0] ?? null);
        $vendorIds = $this->seedVendors();
        $this->seedApprovedPurchaseAndStock($vendorIds, $ingredientIds, $userIds);
        $this->seedKitchenRequestAndAssignment($ingredientIds, $userIds);
        $this->seedWastage($ingredientIds, $userIds[0] ?? null);
        $this->forgetPermissionCache();
    }

    public function down(): void
    {
        if (Schema::hasTable('pos_settings') && Schema::hasColumn('pos_settings', 'allow_negative_kitchen_stock_on_order')) {
            Schema::table('pos_settings', fn (Blueprint $table) => $table->dropColumn('allow_negative_kitchen_stock_on_order'));
        }
        // Demo ledger data is intentionally retained on rollback so posted Inventory Audit history is never silently deleted.
    }

    private function addSettingAndPermission(): void
    {
        if (Schema::hasTable('pos_settings') && !Schema::hasColumn('pos_settings', 'allow_negative_kitchen_stock_on_order')) {
            Schema::table('pos_settings', function (Blueprint $table) {
                $table->boolean('allow_negative_kitchen_stock_on_order')->default(true);
            });
        }
        if (Schema::hasTable('pos_settings')) {
            DB::table('pos_settings')->update(['deduct_inventory_on_order_complete' => 1, 'allow_negative_kitchen_stock_on_order' => 1]);
        }

        if (!Schema::hasTable('permissions')) return;
        $now = now();
        $permission = DB::table('permissions')->where('name', 'inventory-negative-stock-adjust')->where('guard_name', 'web')->first();
        if (!$permission) {
            $row = ['name'=>'inventory-negative-stock-adjust','guard_name'=>'web','created_at'=>$now,'updated_at'=>$now];
            if (Schema::hasColumn('permissions','group_name')) $row['group_name']='Inventory';
            $id = DB::table('permissions')->insertGetId($row);
        } else $id = $permission->id;

        if (Schema::hasTable('roles') && Schema::hasTable('role_has_permissions')) {
            foreach (['Inventory Manager','Super Admin'] as $roleName) {
                $roleId = DB::table('roles')->where('name',$roleName)->where('guard_name','web')->value('id');
                if ($roleId) DB::table('role_has_permissions')->insertOrIgnore(['permission_id'=>$id,'role_id'=>$roleId]);
            }
        }
    }

    private function seedApprovers(): array
    {
        if (!Schema::hasTable('users')) return [];
        $now = now();
        $rows = [
            ['name'=>'Demo Approver A','email'=>'approver.a@demo.local','user_id'=>'DEMO-APR-A'],
            ['name'=>'Demo Approver B','email'=>'approver.b@demo.local','user_id'=>'DEMO-APR-B'],
            ['name'=>'Demo Approver C','email'=>'approver.c@demo.local','user_id'=>'DEMO-APR-C'],
        ];
        $ids=[];
        foreach ($rows as $row) {
            $existing = DB::table('users')->where('email',$row['email'])->first();
            $payload=['name'=>$row['name'],'password'=>Hash::make('12345678'),'updated_at'=>$now];
            if (Schema::hasColumn('users','user_id')) $payload['user_id']=$row['user_id'];
            if (!$existing) {
                $payload['email']=$row['email']; $payload['created_at']=$now;
                $ids[]=(int)DB::table('users')->insertGetId($payload);
            } else {
                DB::table('users')->where('id',$existing->id)->update($payload); $ids[]=(int)$existing->id;
            }
        }

        if (Schema::hasTable('permissions') && Schema::hasTable('model_has_permissions')) {
            $permissionIds=DB::table('permissions')->where('guard_name','web')->whereIn('name',['inventory-purchase-approve','dashboard-view'])->pluck('id');
            foreach ($ids as $uid) foreach ($permissionIds as $pid) DB::table('model_has_permissions')->insertOrIgnore(['permission_id'=>$pid,'model_type'=>'App\\Models\\User','model_id'=>$uid]);
        }
        return $ids;
    }

    private function seedApprovalSettings(array $userIds): void
    {
        if (Schema::hasTable('inventory_purchase_approval_settings')) {
            DB::table('inventory_purchase_approval_settings')->updateOrInsert(['id'=>1],[
                'is_enabled'=>1,'sequential_approval'=>0,'minimum_approvers'=>2,'created_at'=>now(),'updated_at'=>now(),
            ]);
        }
        if (Schema::hasTable('inventory_purchase_approvers')) {
            // Step 5 demo setup intentionally leaves exactly three selectable approvers.
            DB::table('inventory_purchase_approvers')->delete();
            foreach (array_values($userIds) as $i=>$uid) DB::table('inventory_purchase_approvers')->insert([
                'user_id'=>$uid,'approval_order'=>$i+1,'is_active'=>1,'created_at'=>now(),'updated_at'=>now(),
            ]);
        }
    }

    private function seedIngredients(): array
    {
        $units=DB::table('units')->pluck('id','symbol');
        $defs=[
            ['Rice','ING-RICE','WEIGHT','g',1500],['Chicken','ING-CHICKEN','WEIGHT','g',1500],['Beef','ING-BEEF','WEIGHT','g',1500],
            ['Flour','ING-FLOUR','WEIGHT','g',1000],['Cooking Oil','ING-OIL','VOLUME','ml',1000],['Onion','ING-ONION','WEIGHT','g',700],
            ['Tomato','ING-TOMATO','WEIGHT','g',500],['Egg','ING-EGG','COUNT','pcs',12],['Salt','ING-SALT','WEIGHT','g',300],
            ['Mixed Spice','ING-SPICE','WEIGHT','g',200],['Potato','ING-POTATO','WEIGHT','g',1000],['Milk','ING-MILK','VOLUME','ml',1000],
        ];
        $ids=[];
        foreach($defs as [$name,$code,$dim,$symbol,$low]){
            $unitId=$units[$symbol]??null; if(!$unitId) continue;
            $existing=DB::table('ingredients')->where('code',$code)->orWhere('name',$name)->first();
            $payload=['name'=>$name,'code'=>$code,'measurement_dimension'=>$dim,'base_unit_id'=>$unitId,'track_inventory'=>1,'low_stock_level_base'=>$low,'is_active'=>1,'updated_at'=>now()];
            if($existing){DB::table('ingredients')->where('id',$existing->id)->update($payload);$id=(int)$existing->id;}else{$payload['created_at']=now();$id=(int)DB::table('ingredients')->insertGetId($payload);}
            $ids[$code]=$id;
            $standardSymbol=$dim==='WEIGHT'?'kg':($dim==='VOLUME'?'L':null);
            if($standardSymbol && isset($units[$standardSymbol])) DB::table('ingredient_unit_conversions')->updateOrInsert(['ingredient_id'=>$id,'unit_id'=>$units[$standardSymbol]],[
                'factor_to_base'=>'1000.00000000','purchase_allowed'=>1,'recipe_allowed'=>1,'is_active'=>1,'created_at'=>now(),'updated_at'=>now(),
            ]);
        }
        return $ids;
    }

    private function seedFoodsAndRecipes(array $ing, ?int $createdBy): array
    {
        DB::table('food_categories')->updateOrInsert(['slug'=>'demo-inventory-menu'],['name'=>'Demo Inventory Menu','status'=>1,'created_at'=>now(),'updated_at'=>now()]);
        $categoryId=(int)DB::table('food_categories')->where('slug','demo-inventory-menu')->value('id');
        // Preserve historical FK/audit records: remove old foods from the active demo menu instead of destructive deletion.
        DB::table('food_items')->whereNotIn('slug',$this->demoFoodSlugs)->update(['is_available'=>0,'is_draft'=>1,'updated_at'=>now()]);

        $foods=[
            ['Chicken Biryani',280,[['ING-RICE',180],['ING-CHICKEN',150],['ING-OIL',20],['ING-ONION',30],['ING-SPICE',8]]],
            ['Beef Tehari',320,[['ING-RICE',180],['ING-BEEF',160],['ING-OIL',20],['ING-ONION',30],['ING-SPICE',8]]],
            ['Chicken Fried Rice',260,[['ING-RICE',170],['ING-CHICKEN',100],['ING-EGG',1],['ING-OIL',15],['ING-ONION',20]]],
            ['Beef Fried Rice',290,[['ING-RICE',170],['ING-BEEF',100],['ING-EGG',1],['ING-OIL',15],['ING-ONION',20]]],
            ['Chicken Curry',240,[['ING-CHICKEN',180],['ING-OIL',18],['ING-ONION',35],['ING-TOMATO',25],['ING-SPICE',8]]],
            ['Beef Curry',300,[['ING-BEEF',180],['ING-OIL',18],['ING-ONION',35],['ING-TOMATO',25],['ING-SPICE',8]]],
            ['Chicken Khichuri',250,[['ING-RICE',170],['ING-CHICKEN',120],['ING-OIL',18],['ING-ONION',25],['ING-SPICE',7]]],
            ['Beef Khichuri',290,[['ING-RICE',170],['ING-BEEF',120],['ING-OIL',18],['ING-ONION',25],['ING-SPICE',7]]],
            ['Egg Fried Rice',210,[['ING-RICE',180],['ING-EGG',2],['ING-OIL',15],['ING-ONION',20]]],
            ['Chicken Noodles',230,[['ING-FLOUR',140],['ING-CHICKEN',100],['ING-EGG',1],['ING-OIL',15],['ING-ONION',20]]],
            ['Beef Noodles',260,[['ING-FLOUR',140],['ING-BEEF',100],['ING-EGG',1],['ING-OIL',15],['ING-ONION',20]]],
            ['French Fries',160,[['ING-POTATO',220],['ING-OIL',35],['ING-SALT',4]]],
            ['Chicken Burger',220,[['ING-FLOUR',90],['ING-CHICKEN',120],['ING-OIL',12],['ING-ONION',15],['ING-TOMATO',15]]],
            ['Beef Burger',260,[['ING-FLOUR',90],['ING-BEEF',120],['ING-OIL',12],['ING-ONION',15],['ING-TOMATO',15]]],
            ['Milk Tea',80,[['ING-MILK',180],['ING-SPICE',2]]],
        ];
        $ids=[]; $baseUnits=DB::table('ingredients')->whereIn('id',array_values($ing))->pluck('base_unit_id','id');
        foreach($foods as $i=>$food){[$name,$price,$recipe]=$food; $slug=$this->demoFoodSlugs[$i];
            DB::table('food_items')->updateOrInsert(['slug'=>$slug],[
                'name'=>$name,'food_category_id'=>$categoryId,'base_price'=>$price,'is_available'=>1,'is_featured'=>($i<4),'is_dine_in'=>1,'is_takeaway'=>1,'is_draft'=>0,'inventory_tracking'=>1,'created_at'=>now(),'updated_at'=>now(),
            ]);
            $fid=(int)DB::table('food_items')->where('slug',$slug)->value('id'); $ids[]=$fid;
            DB::table('menu_item_recipes')->where('menu_item_id',$fid)->update(['is_active'=>0,'updated_at'=>now()]);
            $version=((int)DB::table('menu_item_recipes')->where('menu_item_id',$fid)->max('version_no'))+1;
            $rid=DB::table('menu_item_recipes')->insertGetId(['menu_item_id'=>$fid,'version_no'=>$version,'yield_quantity'=>1,'is_active'=>1,'effective_from'=>now(),'created_by'=>$createdBy,'created_at'=>now(),'updated_at'=>now()]);
            foreach($recipe as [$code,$qty]) if(isset($ing[$code])) DB::table('menu_item_recipe_items')->insert([
                'menu_item_recipe_id'=>$rid,'ingredient_id'=>$ing[$code],'input_quantity'=>$qty,'input_unit_id'=>$baseUnits[$ing[$code]],'base_quantity'=>$qty,'created_at'=>now(),'updated_at'=>now(),
            ]);
        }
        return $ids;
    }

    private function seedVendors(): array
    {
        $defs=[
            ['Fresh Foods Supply','01710000001','TIN-DEMO-001','BIN-DEMO-001','5%','7.5%','VAT-001'],
            ['Daily Grocery Traders','01710000002','TIN-DEMO-002','BIN-DEMO-002','5%','7.5%','VAT-002'],
            ['Premium Meat & Dairy','01710000003','TIN-DEMO-003','BIN-DEMO-003','5%','7.5%','VAT-003'],
        ]; $ids=[];
        foreach($defs as [$name,$phone,$tin,$bin,$tds,$vds,$tax]){
            $payload=['phone'=>$phone,'email'=>Str::slug($name,'.').'@demo.local','address'=>'Dhaka, Bangladesh','is_active'=>1,'updated_at'=>now()];
            foreach(['tin'=>$tin,'bin'=>$bin,'tds'=>$tds,'vds'=>$vds,'tax'=>$tax] as $col=>$val) if(Schema::hasColumn('vendors',$col)) $payload[$col]=$val;
            $existing=DB::table('vendors')->where('name',$name)->first();
            if($existing){DB::table('vendors')->where('id',$existing->id)->update($payload);$ids[]=(int)$existing->id;}else{$payload['name']=$name;$payload['created_at']=now();$ids[]=(int)DB::table('vendors')->insertGetId($payload);}
        } return $ids;
    }

    private function seedApprovedPurchaseAndStock(array $vendors,array $ing,array $users): void
    {
        if(!$vendors || !$ing) return;
        $vendorId=$vendors[0]; $creator=$users[0]??null;
        $voucherNo='DEMO-PV-001';
        $voucher=DB::table('purchase_vouchers')->where('voucher_no',$voucherNo)->first();
        if(!$voucher){
            $vid=DB::table('purchase_vouchers')->insertGetId(['vendor_id'=>$vendorId,'voucher_no'=>$voucherNo,'voucher_date'=>now()->toDateString(),'status'=>'COMPLETED','revision_no'=>1,'subtotal'=>18000,'discount'=>0,'tax'=>0,'total'=>18000,'notes'=>'Demo approved voucher: A approved, B rejected, then B approved after re-send.','created_by'=>$creator,'submitted_at'=>now()->subHours(4),'approved_at'=>now()->subHours(2),'sent_to_vendor_at'=>now()->subHour(),'sent_to_vendor_by'=>$creator,'created_at'=>now(),'updated_at'=>now()]);
            $items=$ing; $unitIds=DB::table('ingredients')->whereIn('id',array_values($items))->pluck('base_unit_id','id');
            foreach($items as $code=>$iid) DB::table('purchase_voucher_items')->insert(['purchase_voucher_id'=>$vid,'ingredient_id'=>$iid,'quantity'=>10000,'unit_id'=>$unitIds[$iid],'package_conversion_id'=>null,'conversion_factor_snapshot'=>1,'base_quantity'=>10000,'unit_price'=>0.15,'line_total'=>1500,'created_at'=>now(),'updated_at'=>now()]);
            if(count($users)>=2){
                DB::table('purchase_voucher_approvals')->insert([
                    ['purchase_voucher_id'=>$vid,'revision_no'=>1,'approver_user_id'=>$users[0],'approver_name'=>'Demo Approver A','approver_email'=>'approver.a@demo.local','approval_order'=>1,'batch_no'=>1,'assigned_by'=>$creator,'assigned_at'=>now()->subHours(4),'dispatch_note'=>'Initial approval request','status'=>'APPROVED','comment'=>'Approved for demo purchase','acted_at'=>now()->subHours(3),'created_at'=>now(),'updated_at'=>now()],
                    ['purchase_voucher_id'=>$vid,'revision_no'=>1,'approver_user_id'=>$users[1],'approver_name'=>'Demo Approver B','approver_email'=>'approver.b@demo.local','approval_order'=>2,'batch_no'=>1,'assigned_by'=>$creator,'assigned_at'=>now()->subHours(4),'dispatch_note'=>'Initial approval request','status'=>'REJECTED','comment'=>'Please confirm supplier price','acted_at'=>now()->subHours(3),'created_at'=>now(),'updated_at'=>now()],
                    ['purchase_voucher_id'=>$vid,'revision_no'=>1,'approver_user_id'=>$users[1],'approver_name'=>'Demo Approver B','approver_email'=>'approver.b@demo.local','approval_order'=>3,'batch_no'=>2,'assigned_by'=>$creator,'assigned_at'=>now()->subHours(2),'dispatch_note'=>'Price confirmed; re-sending to B','status'=>'APPROVED','comment'=>'Approved after price confirmation','acted_at'=>now()->subHours(2),'created_at'=>now(),'updated_at'=>now()],
                ]);
            }
            $purchaseNo='DEMO-PO-001';
            $pid=DB::table('purchases')->insertGetId(['purchase_voucher_id'=>$vid,'vendor_id'=>$vendorId,'purchase_no'=>$purchaseNo,'purchase_date'=>now()->toDateString(),'invoice_no'=>'DEMO-INV-001','reference_no'=>$voucherNo,'status'=>'DRAFT','subtotal'=>18000,'discount'=>0,'tax'=>0,'total'=>18000,'notes'=>'Demo received purchase','created_by'=>$creator,'grn'=>'Demo GRN confirmed: quantities checked and accepted.','grn_status'=>'CONFIRMED','grn_confirmed_at'=>now(),'grn_confirmed_by'=>$creator,'created_at'=>now(),'updated_at'=>now()]);
            $movementItems=[];
            foreach($items as $code=>$iid){$unitId=$unitIds[$iid];DB::table('purchase_items')->insert(['purchase_id'=>$pid,'ingredient_id'=>$iid,'quantity'=>10000,'unit_id'=>$unitId,'conversion_factor_snapshot'=>1,'base_quantity'=>10000,'unit_price'=>0.15,'line_total'=>1500,'created_at'=>now(),'updated_at'=>now()]);$movementItems[]=['ingredient_id'=>$iid,'quantity_base'=>'10000'];}
            $mainId=(int)DB::table('stock_locations')->where('type','MAIN')->value('id');
            $movement=app(StockMovementService::class)->post(StockMovement::PURCHASE_RECEIVE,$movementItems,null,$mainId,['reference_type'=>'App\\Models\\Purchase','reference_id'=>$pid,'performed_by'=>$creator,'reason'=>'Demo GRN purchase receive']);
            DB::table('purchases')->where('id',$pid)->update(['status'=>'RECEIVED','received_at'=>now(),'received_by'=>$creator,'received_stock_movement_id'=>$movement->id,'updated_at'=>now()]);
            DB::table('purchase_vouchers')->where('id',$vid)->update(['converted_purchase_id'=>$pid,'updated_at'=>now()]);
            if(Schema::hasTable('vendor_payments')) DB::table('vendor_payments')->insert(['vendor_id'=>$vendorId,'purchase_id'=>$pid,'payment_no'=>'DEMO-VPAY-001','payment_date'=>now()->toDateString(),'payment_type'=>'Split','amount'=>7000,'paid_in_cash'=>3000,'paid_in_card'=>2000,'paid_in_mfs'=>2000,'card_type'=>'Visa','mfs_provider'=>'bKash','card_reference'=>'DEMO-CARD-001','mfs_reference'=>'DEMO-MFS-001','note'=>'Demo partial vendor payment; remaining amount stays due.','created_by'=>$creator,'created_at'=>now(),'updated_at'=>now()]);
        }
    }

    private function seedKitchenRequestAndAssignment(array $ing,array $users): void
    {
        if(!$ing || DB::table('kitchen_requests')->where('request_no','DEMO-KR-001')->exists()) return;
        $creator=$users[0]??null; $mainId=(int)DB::table('stock_locations')->where('type','MAIN')->value('id'); $kitchenId=(int)DB::table('stock_locations')->where('type','KITCHEN')->value('id');
        $krid=DB::table('kitchen_requests')->insertGetId(['request_no'=>'DEMO-KR-001','request_type'=>'INGREDIENT','request_date'=>now()->toDateString(),'status'=>'FULLY_ISSUED','requested_by'=>$creator,'reviewed_by'=>$creator,'notes'=>'Demo Kitchen request assigned by Inventory Manager.','submitted_at'=>now()->subHour(),'closed_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
        $selected=$ing; $unitIds=DB::table('ingredients')->whereIn('id',array_values($selected))->pluck('base_unit_id','id'); $movementItems=[];
        foreach($selected as $code=>$iid){$qty=in_array($code,['ING-RICE','ING-CHICKEN','ING-BEEF'],true)?4000:1500;DB::table('kitchen_request_ingredient_items')->insert(['kitchen_request_id'=>$krid,'ingredient_id'=>$iid,'source_kind'=>'DIRECT','input_quantity'=>$qty,'conversion_factor_snapshot'=>1,'required_base_qty'=>$qty,'approved_base_qty'=>$qty,'issued_base_qty'=>$qty,'display_unit_id'=>$unitIds[$iid],'created_at'=>now(),'updated_at'=>now()]);$movementItems[]=['ingredient_id'=>$iid,'quantity_base'=>(string)$qty];}
        $movement=app(StockMovementService::class)->post(StockMovement::MAIN_TO_KITCHEN,$movementItems,$mainId,$kitchenId,['reference_type'=>'App\\Models\\KitchenRequest','reference_id'=>$krid,'performed_by'=>$creator,'reason'=>'Demo inventory assignment to Kitchen']);
        $tid=DB::table('stock_transfers')->insertGetId(['transfer_no'=>'DEMO-TR-001','direction'=>'MAIN_TO_KITCHEN','kitchen_request_id'=>$krid,'source_location_id'=>$mainId,'destination_location_id'=>$kitchenId,'status'=>'POSTED','idempotency_key'=>'DEMO-TR-001','posted_movement_id'=>$movement->id,'created_by'=>$creator,'posted_by'=>$creator,'posted_at'=>now(),'notes'=>'Demo assigned inventory to Kitchen','created_at'=>now(),'updated_at'=>now()]);
        foreach($selected as $code=>$iid){$qty=in_array($code,['ING-RICE','ING-CHICKEN','ING-BEEF'],true)?4000:1500;DB::table('stock_transfer_items')->insert(['stock_transfer_id'=>$tid,'ingredient_id'=>$iid,'quantity'=>$qty,'unit_id'=>$unitIds[$iid],'conversion_factor_snapshot'=>1,'base_quantity'=>$qty,'created_at'=>now(),'updated_at'=>now()]);}
    }

    private function seedWastage(array $ing,?int $userId): void
    {
        $iid=$ing['ING-ONION']??null; if(!$iid || DB::table('inventory_wastages')->where('wastage_no','DEMO-WST-001')->exists()) return;
        $kitchenId=(int)DB::table('stock_locations')->where('type','KITCHEN')->value('id');
        $balance=(float)(DB::table('inventory_balances')->where('stock_location_id',$kitchenId)->where('ingredient_id',$iid)->value('quantity_base')??0); if($balance<100) return;
        $wid=DB::table('inventory_wastages')->insertGetId(['location_id'=>$kitchenId,'wastage_no'=>'DEMO-WST-001','reason_code'=>'SPILLAGE','notes'=>'Demo kitchen wastage','status'=>'POSTED','created_by'=>$userId,'posted_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
        $unitId=(int)DB::table('ingredients')->where('id',$iid)->value('base_unit_id'); DB::table('inventory_wastage_items')->insert(['inventory_wastage_id'=>$wid,'ingredient_id'=>$iid,'quantity'=>100,'unit_id'=>$unitId,'conversion_factor_snapshot'=>1,'base_quantity'=>100,'created_at'=>now(),'updated_at'=>now()]);
        app(StockMovementService::class)->post(StockMovement::WASTAGE,[['ingredient_id'=>$iid,'quantity_base'=>'100']],$kitchenId,null,['reference_type'=>'App\\Models\\InventoryWastage','reference_id'=>$wid,'performed_by'=>$userId,'reason'=>'Demo kitchen wastage']);
    }

    private function forgetPermissionCache(): void
    {
        try { app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions(); } catch (\Throwable $e) {}
    }
};
