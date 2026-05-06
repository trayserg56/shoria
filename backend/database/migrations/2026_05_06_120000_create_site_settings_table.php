<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('logo_text', 120)->default('Shoria');
            $table->string('logo_image_path', 2048)->nullable();
            $table->string('phone_display', 64)->default('+7 (900) 000-00-00');
            $table->string('phone_tel', 32)->default('+79000000000');
            $table->string('work_hours_short', 255)->default('Пн–Вс: 10:00–20:00');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::table('site_settings')->insert([
            'logo_text' => 'Shoria',
            'logo_image_path' => null,
            'phone_display' => '+7 (900) 000-00-00',
            'phone_tel' => '+79000000000',
            'work_hours_short' => 'Пн–Вс: 10:00–20:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
