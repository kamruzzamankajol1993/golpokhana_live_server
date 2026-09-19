<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(){ Schema::create('offline_pos_sync_logs', function(Blueprint $table){
  $table->id(); $table->foreignId('device_id')->nullable(); $table->string('type'); $table->string('status')->default('success'); $table->text('message')->nullable(); $table->timestamps();
 }); }
 public function down(){ Schema::dropIfExists('offline_pos_sync_logs'); }
};
