<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  if (Schema::hasTable("offline_pos_devices") && !Schema::hasColumn("offline_pos_devices","device_key")) {
   Schema::table("offline_pos_devices", function(Blueprint $table){ $table->string("device_key",120)->unique()->nullable()->after("device_uuid"); });
  }
 }
 public function down(): void {
  if (Schema::hasTable("offline_pos_devices") && Schema::hasColumn("offline_pos_devices","device_key")) Schema::table("offline_pos_devices", function(Blueprint $table){$table->dropColumn("device_key");});
 }
};
