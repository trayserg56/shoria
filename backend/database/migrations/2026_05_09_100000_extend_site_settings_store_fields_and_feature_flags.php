<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->string('support_email', 255)->nullable()->after('work_hours_short');
            $table->string('footer_legal_line', 500)->nullable()->after('support_email');
            $table->json('feature_flags')->nullable()->after('footer_legal_line');
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->dropColumn(['support_email', 'footer_legal_line', 'feature_flags']);
        });
    }
};
