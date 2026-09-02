<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
return new class extends Migration { public function up(){
if(!Schema::hasColumn("orders","delivery_partner_id")) Schema::table("orders",function(Blueprint $t){$t->unsignedBigInteger("delivery_partner_id")->nullable()->after("delivery_partner");});
if(Schema::hasTable("delivery_partners") && Schema::hasColumn("orders","delivery_partner")){
$map=["inhouse"=>"In-house Delivery","foodpanda"=>"Foodpanda","foodi"=>"Foodi","pathao_food"=>"Pathao Food"];
foreach($map as $key=>$name){$id=DB::table("delivery_partners")->where("name",$name)->value("id"); if(!$id){$id=DB::table("delivery_partners")->insertGetId(["name"=>$name,"status"=>1,"created_at"=>now(),"updated_at"=>now()]);} DB::table("orders")->where("delivery_partner",$key)->update(["delivery_partner_id"=>$id]);}
}}
public function down(){if(Schema::hasColumn("orders","delivery_partner_id")) Schema::table("orders",function(Blueprint $t){$t->dropColumn("delivery_partner_id");});}};
