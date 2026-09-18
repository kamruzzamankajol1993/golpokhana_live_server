<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('offline_pos_devices');
    }

    public function down(): void
    {
        // Intentionally not recreated. The current Offline POS uses the global sync-key/settings flow.
    }
};
