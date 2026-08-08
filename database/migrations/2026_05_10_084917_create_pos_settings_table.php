<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pos_settings', function (Blueprint $table) {
            $table->id();
            $table->string('default_view')->nullable();
$table->integer('items_per_page')->default(12);
$table->boolean('auto_print_kitchen')->default(true);
$table->boolean('auto_print_invoice')->default(true);
$table->boolean('require_table_selection')->default(true);
$table->boolean('show_out_of_stock')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_settings');
    }
};
