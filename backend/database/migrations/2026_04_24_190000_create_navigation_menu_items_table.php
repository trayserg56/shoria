<?php

use App\Models\NavigationMenuItem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('navigation_menu_items', function (Blueprint $table): void {
            $table->id();
            $table->string('location', 40)->index();
            $table->string('label', 120);
            $table->string('path', 255);
            $table->boolean('is_external')->default(false);
            $table->boolean('open_in_new_tab')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        $now = now();

        DB::table('navigation_menu_items')->insert([
            [
                'location' => NavigationMenuItem::LOCATION_HEADER,
                'label' => 'Главная',
                'path' => '/',
                'sort_order' => 10,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'location' => NavigationMenuItem::LOCATION_HEADER,
                'label' => 'Каталог',
                'path' => '/catalog',
                'sort_order' => 20,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'location' => NavigationMenuItem::LOCATION_HEADER,
                'label' => 'Новости',
                'path' => '/news',
                'sort_order' => 30,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'location' => NavigationMenuItem::LOCATION_FOOTER_CUSTOMERS,
                'label' => 'Каталог',
                'path' => '/catalog',
                'sort_order' => 10,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'location' => NavigationMenuItem::LOCATION_FOOTER_CUSTOMERS,
                'label' => 'Новости',
                'path' => '/news',
                'sort_order' => 20,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'location' => NavigationMenuItem::LOCATION_FOOTER_CUSTOMERS,
                'label' => 'Избранное',
                'path' => '/wishlist',
                'sort_order' => 30,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'location' => NavigationMenuItem::LOCATION_FOOTER_CUSTOMERS,
                'label' => 'Корзина',
                'path' => '/cart',
                'sort_order' => 40,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'location' => NavigationMenuItem::LOCATION_FOOTER_ACCOUNT,
                'label' => 'Профиль',
                'path' => '/account',
                'sort_order' => 10,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'location' => NavigationMenuItem::LOCATION_FOOTER_ACCOUNT,
                'label' => 'Сравнение',
                'path' => '/compare',
                'sort_order' => 20,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('navigation_menu_items');
    }
};

