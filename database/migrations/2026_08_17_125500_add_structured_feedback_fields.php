<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('orders', 'feedback_token')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('feedback_token', 64)->nullable()->unique()->after('order_number');
            });
        }

        Schema::table('reviews', function (Blueprint $table) {
            if (!Schema::hasColumn('reviews', 'food_rating')) {
                $table->unsignedTinyInteger('food_rating')->nullable()->after('rating');
            }
            if (!Schema::hasColumn('reviews', 'service_rating')) {
                $table->unsignedTinyInteger('service_rating')->nullable()->after('food_rating');
            }
            if (!Schema::hasColumn('reviews', 'cleanliness_rating')) {
                $table->unsignedTinyInteger('cleanliness_rating')->nullable()->after('service_rating');
            }
            if (!Schema::hasColumn('reviews', 'atmosphere_rating')) {
                $table->unsignedTinyInteger('atmosphere_rating')->nullable()->after('cleanliness_rating');
            }
            if (!Schema::hasColumn('reviews', 'value_rating')) {
                $table->unsignedTinyInteger('value_rating')->nullable()->after('atmosphere_rating');
            }
            if (!Schema::hasColumn('reviews', 'overall_experience_rating')) {
                $table->unsignedTinyInteger('overall_experience_rating')->nullable()->after('value_rating');
            }
            if (!Schema::hasColumn('reviews', 'heard_from')) {
                $table->text('heard_from')->nullable()->after('review');
            }
            if (!Schema::hasColumn('reviews', 'guest_name')) {
                $table->string('guest_name')->nullable()->after('heard_from');
            }
            if (!Schema::hasColumn('reviews', 'guest_email')) {
                $table->string('guest_email')->nullable()->after('guest_name');
            }
            if (!Schema::hasColumn('reviews', 'guest_phone')) {
                $table->string('guest_phone', 30)->nullable()->after('guest_email');
            }
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $columns = [
                'food_rating',
                'service_rating',
                'cleanliness_rating',
                'atmosphere_rating',
                'value_rating',
                'overall_experience_rating',
                'heard_from',
                'guest_name',
                'guest_email',
                'guest_phone',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('reviews', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        if (Schema::hasColumn('orders', 'feedback_token')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropUnique('orders_feedback_token_unique');
                $table->dropColumn('feedback_token');
            });
        }
    }
};
