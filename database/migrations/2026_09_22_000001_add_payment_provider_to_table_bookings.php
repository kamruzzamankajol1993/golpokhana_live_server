<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::table('table_bookings', function(Blueprint $table){
   $table->string('advance_card_provider')->nullable()->after('advance_payment_method');
   $table->string('advance_mfs_provider')->nullable()->after('advance_card_provider');
  });
 }
 public function down(): void {
  Schema::table('table_bookings', function(Blueprint $table){
   $table->dropColumn(['advance_card_provider','advance_mfs_provider']);
  });
 }
};
