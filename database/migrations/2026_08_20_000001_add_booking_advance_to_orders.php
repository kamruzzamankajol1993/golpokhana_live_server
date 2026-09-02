<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { if (!Schema::hasColumn('orders','booking_advance')) Schema::table('orders', function(Blueprint $table){ $table->decimal('booking_advance',10,2)->default(0); }); }
 public function down(): void { if (Schema::hasColumn('orders','booking_advance')) Schema::table('orders', function(Blueprint $table){ $table->dropColumn('booking_advance'); }); }
};
