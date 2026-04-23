<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['tracking_events', 'newsletter_subscriptions', 'orders'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('first_touch_source', 120)->nullable()->index();
                $table->string('first_touch_medium', 120)->nullable()->index();
                $table->string('first_touch_campaign', 180)->nullable();
                $table->string('first_touch_content', 180)->nullable();
                $table->string('first_touch_term', 180)->nullable();
                $table->string('first_touch_referrer_host', 255)->nullable();
                $table->string('first_touch_landing_path', 2048)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['tracking_events', 'newsletter_subscriptions', 'orders'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn([
                    'first_touch_source',
                    'first_touch_medium',
                    'first_touch_campaign',
                    'first_touch_content',
                    'first_touch_term',
                    'first_touch_referrer_host',
                    'first_touch_landing_path',
                ]);
            });
        }
    }
};
