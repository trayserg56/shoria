<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_pages', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 160);
            $table->string('slug', 180)->unique();
            $table->text('excerpt')->nullable();
            $table->longText('content')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('show_in_header')->default(false)->index();
            $table->boolean('show_in_footer')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $now = now();

        $pages = [
            [
                'title' => 'Доставка',
                'slug' => 'delivery',
                'excerpt' => 'Способы, сроки и стоимость доставки.',
                'content' => '<h2>Способы доставки</h2><p>Мы доставляем заказы курьером, в пункты выдачи и через почтовые сервисы. Точный список зависит от вашего региона и отображается при оформлении заказа.</p><h2>Сроки</h2><p>Обычно доставка занимает от 1 до 5 рабочих дней. Для удаленных регионов сроки могут быть больше.</p><h2>Стоимость</h2><p>Стоимость рассчитывается автоматически в корзине на основе адреса, веса и выбранного способа доставки.</p>',
                'seo_title' => 'Доставка — Shoria',
                'seo_description' => 'Информация о сроках, способах и стоимости доставки заказов Shoria.',
                'is_active' => true,
                'show_in_header' => true,
                'show_in_footer' => true,
                'sort_order' => 10,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Оплата',
                'slug' => 'payment',
                'excerpt' => 'Доступные способы оплаты и условия.',
                'content' => '<h2>Доступные способы оплаты</h2><p>Оплатить заказ можно банковской картой онлайн, при получении (если способ доступен) и другими методами, подключенными в магазине.</p><h2>Безопасность</h2><p>Платежи проходят через защищенные платежные шлюзы. Данные банковских карт не хранятся в открытом виде.</p>',
                'seo_title' => 'Оплата — Shoria',
                'seo_description' => 'Способы оплаты заказов в интернет-магазине Shoria.',
                'is_active' => true,
                'show_in_header' => false,
                'show_in_footer' => true,
                'sort_order' => 20,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Возврат и обмен',
                'slug' => 'returns',
                'excerpt' => 'Условия возврата и обмена товаров.',
                'content' => '<h2>Условия возврата</h2><p>Вы можете вернуть товар надлежащего качества в течение установленного законом срока, если сохранены товарный вид и потребительские свойства.</p><h2>Как оформить возврат</h2><p>Напишите в поддержку, укажите номер заказа и причину возврата. Мы подскажем дальнейшие шаги и сроки обработки.</p>',
                'seo_title' => 'Возврат и обмен — Shoria',
                'seo_description' => 'Правила возврата и обмена товаров в Shoria.',
                'is_active' => true,
                'show_in_header' => false,
                'show_in_footer' => true,
                'sort_order' => 30,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Пользовательское соглашение',
                'slug' => 'user-agreement',
                'excerpt' => 'Правила использования сайта и сервиса.',
                'content' => '<h2>Общие положения</h2><p>Настоящее соглашение регулирует порядок использования сайта и оформления заказов в магазине Shoria.</p><h2>Права и обязанности</h2><p>Пользователь обязуется предоставлять достоверные данные, а магазин — обеспечивать корректную обработку заказов и поддержку.</p>',
                'seo_title' => 'Пользовательское соглашение — Shoria',
                'seo_description' => 'Пользовательское соглашение интернет-магазина Shoria.',
                'is_active' => true,
                'show_in_header' => true,
                'show_in_footer' => true,
                'sort_order' => 40,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Политика конфиденциальности',
                'slug' => 'privacy-policy',
                'excerpt' => 'Как мы обрабатываем и защищаем персональные данные.',
                'content' => '<h2>Персональные данные</h2><p>Мы обрабатываем персональные данные только в объеме, необходимом для оформления заказов, доставки и поддержки.</p><h2>Хранение и защита</h2><p>Данные защищаются техническими и организационными мерами, предусмотренными действующим законодательством.</p>',
                'seo_title' => 'Политика конфиденциальности — Shoria',
                'seo_description' => 'Политика обработки персональных данных Shoria.',
                'is_active' => true,
                'show_in_header' => false,
                'show_in_footer' => true,
                'sort_order' => 50,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('service_pages')->insert($pages);

    }

    public function down(): void
    {
        Schema::dropIfExists('service_pages');
    }
};
