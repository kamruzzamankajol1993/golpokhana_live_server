<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::table("restaurant_settings", function(Blueprint $table){ if(!Schema::hasColumn("restaurant_settings","pos_action_password")) $table->string("pos_action_password")->nullable(); }); }
 public function down(): void { }
};
