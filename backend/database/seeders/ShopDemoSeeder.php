<?php

namespace Database\Seeders;

use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\DeliveryProvider;
use App\Models\DeliveryMethod;
use App\Models\NewsPost;
use App\Models\PaymentProvider;
use App\Models\PromoCode;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\ProductVariantImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ShopDemoSeeder extends Seeder
{
    public function run(): void
    {
        DeliveryProvider::query()->updateOrCreate(
            ['code' => 'manual_local'],
            [
                'name' => 'Локальная доставка',
                'driver' => 'manual',
                'mode' => 'live',
                'is_active' => true,
                'is_default' => true,
                'supports_pickup_points' => false,
                'supports_tracking' => false,
                'sort_order' => 1,
                'config' => [
                    'note' => 'Локальные методы магазина без внешнего API.',
                ],
            ],
        );

        DeliveryProvider::query()->updateOrCreate(
            ['code' => 'cdek'],
            [
                'name' => 'CDEK',
                'driver' => 'cdek',
                'mode' => 'sandbox',
                'is_active' => false,
                'is_default' => false,
                'supports_pickup_points' => true,
                'supports_tracking' => true,
                'sort_order' => 2,
                'config' => [
                    'account' => 'demo-account',
                    'secure_password' => 'demo-password',
                ],
            ],
        );

        PaymentProvider::query()->updateOrCreate(
            ['code' => 'tbank_card'],
            [
                'name' => 'T-Bank',
                'checkout_label' => 'T-Bank (тест)',
                'driver' => 'tbank',
                'mode' => 'sandbox',
                'is_active' => true,
                'is_default' => true,
                'sort_order' => 1,
                'config' => [
                    'terminal_key' => 'demo_terminal',
                    'password' => 'demo_secret',
                ],
            ],
        );

        PaymentProvider::query()->updateOrCreate(
            ['code' => 'sber_card'],
            [
                'name' => 'Sber',
                'checkout_label' => 'Sber (тест)',
                'driver' => 'sber',
                'mode' => 'sandbox',
                'is_active' => false,
                'is_default' => false,
                'sort_order' => 2,
                'config' => [
                    'merchant_login' => 'demo-merchant',
                    'merchant_password' => 'demo-password',
                ],
            ],
        );

        PaymentProvider::query()->updateOrCreate(
            ['code' => 'cash'],
            [
                'name' => 'Оплата при получении',
                'checkout_label' => 'Наличными при получении',
                'driver' => 'manual_cash',
                'mode' => 'live',
                'is_active' => true,
                'is_default' => false,
                'sort_order' => 3,
                'config' => [
                    'instruction' => 'pay_on_delivery',
                ],
            ],
        );

        DeliveryMethod::query()->updateOrCreate(
            ['code' => 'courier'],
            [
                'name' => 'Курьер',
                'provider_code' => 'manual_local',
                'method_type' => 'courier',
                'fee' => 490,
                'calculation_mode' => 'flat',
                'is_active' => true,
                'sort_order' => 1,
            ],
        );

        DeliveryMethod::query()->updateOrCreate(
            ['code' => 'pickup'],
            [
                'name' => 'Самовывоз',
                'provider_code' => 'manual_local',
                'method_type' => 'pickup',
                'fee' => 0,
                'calculation_mode' => 'flat',
                'is_active' => true,
                'sort_order' => 2,
            ],
        );

        DeliveryMethod::query()->updateOrCreate(
            ['code' => 'cdek_courier'],
            [
                'name' => 'CDEK курьер',
                'provider_code' => 'cdek',
                'external_code' => 'courier',
                'method_type' => 'courier',
                'fee' => 390,
                'calculation_mode' => 'provider',
                'is_active' => true,
                'sort_order' => 3,
            ],
        );

        DeliveryMethod::query()->updateOrCreate(
            ['code' => 'cdek_pickup'],
            [
                'name' => 'CDEK ПВЗ',
                'provider_code' => 'cdek',
                'external_code' => 'pickup',
                'method_type' => 'pickup',
                'fee' => 250,
                'calculation_mode' => 'provider',
                'is_active' => true,
                'sort_order' => 4,
            ],
        );

        PromoCode::query()->firstOrCreate(
            ['code' => 'WELCOME10'],
            [
                'name' => 'Приветственная скидка 10%',
                'discount_type' => 'fixed_percent',
                'discount_value' => 10,
                'min_subtotal' => null,
                'usage_limit' => null,
                'starts_at' => null,
                'ends_at' => null,
                'is_active' => true,
            ],
        );

        $categoriesData = collect([
            [
                'name' => 'Lifestyle',
                'slug' => 'lifestyle',
                'description' => 'Повседневные модели для города и офиса.',
                'image_url' => 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=1200&q=80',
                'seo_title' => 'Lifestyle кроссовки — Shoria',
                'seo_description' => 'Повседневные lifestyle-кроссовки Shoria для города, офиса и ежедневного гардероба.',
                'is_featured' => true,
                'sort_order' => 1,
                'parent_slug' => null,
            ],
            [
                'name' => 'Running',
                'slug' => 'running',
                'description' => 'Лёгкие кроссовки для тренировок и пробежек.',
                'image_url' => 'https://images.unsplash.com/photo-1460353581641-37baddab0fa2?auto=format&fit=crop&w=1200&q=80',
                'seo_title' => 'Running кроссовки — Shoria',
                'seo_description' => 'Беговые кроссовки Shoria: тренировки, темповые сессии и комфортные ежедневные пробежки.',
                'is_featured' => true,
                'sort_order' => 2,
                'parent_slug' => null,
            ],
            [
                'name' => 'Street',
                'slug' => 'street',
                'description' => 'Яркие пары для уличного стиля.',
                'image_url' => 'https://images.unsplash.com/photo-1518002171953-a080ee817e1f?auto=format&fit=crop&w=1200&q=80',
                'seo_title' => 'Street кроссовки — Shoria',
                'seo_description' => 'Streetwear-модели Shoria с выразительным силуэтом и акцентом на городской стиль.',
                'is_featured' => false,
                'sort_order' => 3,
                'parent_slug' => null,
            ],
            [
                'name' => 'Premium',
                'slug' => 'premium',
                'description' => 'Лимитированные релизы и премиум-коллекции.',
                'image_url' => 'https://images.unsplash.com/photo-1525966222134-fcfa99b8ae77?auto=format&fit=crop&w=1200&q=80',
                'seo_title' => 'Premium кроссовки — Shoria',
                'seo_description' => 'Премиальные и лимитированные кроссовки Shoria для коллекций и особых релизов.',
                'is_featured' => true,
                'sort_order' => 4,
                'parent_slug' => null,
            ],
            [
                'name' => 'Classic Daily',
                'slug' => 'classic-daily',
                'description' => 'Повседневные базовые пары в нейтральных оттенках.',
                'image_url' => 'https://images.unsplash.com/photo-1525966222134-fcfa99b8ae77?auto=format&fit=crop&w=1200&q=80',
                'is_featured' => false,
                'sort_order' => 11,
                'parent_slug' => 'lifestyle',
            ],
            [
                'name' => 'Urban Comfort',
                'slug' => 'urban-comfort',
                'description' => 'Комфортные модели для долгих городских маршрутов.',
                'image_url' => 'https://images.unsplash.com/photo-1549298916-b41d501d3772?auto=format&fit=crop&w=1200&q=80',
                'is_featured' => false,
                'sort_order' => 12,
                'parent_slug' => 'lifestyle',
            ],
            [
                'name' => 'Road Running',
                'slug' => 'road-running',
                'description' => 'Линейка для асфальта и регулярных темповых тренировок.',
                'image_url' => 'https://images.unsplash.com/photo-1600185365483-26d7a4cc7519?auto=format&fit=crop&w=1200&q=80',
                'is_featured' => true,
                'sort_order' => 21,
                'parent_slug' => 'running',
            ],
            [
                'name' => 'Trail Running',
                'slug' => 'trail-running',
                'description' => 'Больше сцепления и защиты для маршрутов вне города.',
                'image_url' => 'https://images.unsplash.com/photo-1543508282-6319a3e2621f?auto=format&fit=crop&w=1200&q=80',
                'is_featured' => false,
                'sort_order' => 22,
                'parent_slug' => 'running',
            ],
            [
                'name' => 'Bold Street',
                'slug' => 'bold-street',
                'description' => 'Яркие силуэты и заметные цветовые акценты.',
                'image_url' => 'https://images.unsplash.com/photo-1515955656352-a1fa3ffcd111?auto=format&fit=crop&w=1200&q=80',
                'is_featured' => false,
                'sort_order' => 31,
                'parent_slug' => 'street',
            ],
            [
                'name' => 'Limited Edition',
                'slug' => 'limited-edition',
                'description' => 'Редкие релизы и коллекционные пары.',
                'image_url' => 'https://images.unsplash.com/photo-1607522370275-f14206abe5d3?auto=format&fit=crop&w=1200&q=80',
                'is_featured' => true,
                'sort_order' => 41,
                'parent_slug' => 'premium',
            ],
        ]);

        $categories = collect();

        foreach ($categoriesData->where('parent_slug', null) as $item) {
            $category = Category::query()->firstOrCreate(
                ['slug' => $item['slug']],
                collect($item)->except('parent_slug')->all(),
            );

            $category->fill([
                'seo_title' => $category->seo_title ?: ($item['seo_title'] ?? null),
                'seo_description' => $category->seo_description ?: ($item['seo_description'] ?? null),
            ]);
            $category->save();

            $categories->put($category->slug, $category);
        }

        foreach ($categoriesData->whereNotNull('parent_slug') as $item) {
            $parent = $categories->get($item['parent_slug']);

            $category = Category::query()->firstOrCreate(
                ['slug' => $item['slug']],
                collect($item)
                    ->except('parent_slug')
                    ->merge(['parent_id' => $parent?->id])
                    ->all(),
            );

            $category->fill([
                'parent_id' => $category->parent_id ?: $parent?->id,
                'seo_title' => $category->seo_title ?: ($item['seo_title'] ?? null),
                'seo_description' => $category->seo_description ?: ($item['seo_description'] ?? null),
            ]);
            $category->save();

            $categories->put($category->slug, $category);
        }

        Banner::query()->firstOrCreate([
            'title' => 'Drop Spring 2026',
        ], [
            'title' => 'Drop Spring 2026',
            'subtitle' => 'Новая коллекция уже в Shoria. Пары, которые сложно поймать позже.',
            'cta_label' => 'Смотреть каталог',
            'cta_url' => '/catalog',
            'image_url' => 'https://images.unsplash.com/photo-1491553895911-0055eca6402d?auto=format&fit=crop&w=1600&q=80',
            'bg_color' => '#F35B04',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $products = [
            [
                'name' => 'Aero Pulse 300',
                'brand' => 'Nike',
                'slug' => 'aero-pulse-300',
                'sku' => 'SH-AERO-300',
                'category_slug' => 'running',
                'description' => 'Легкая сетка, амортизация для ежедневных пробежек.',
                'price' => 12990,
                'old_price' => 14990,
                'currency' => 'RUB',
                'stock' => 13,
                'is_featured' => true,
                'is_hit' => true,
                'is_new' => false,
                'is_customer_choice' => true,
                'sort_order' => 1,
                'image_url' => 'https://images.unsplash.com/photo-1543508282-6319a3e2621f?auto=format&fit=crop&w=1200&q=80',
                'additional_category_slugs' => ['road-running'],
            ],
            [
                'name' => 'City Frame One',
                'brand' => 'Nike',
                'slug' => 'city-frame-one',
                'sku' => 'SH-CITY-001',
                'category_slug' => 'lifestyle',
                'description' => 'Минималистичная пара на каждый день.',
                'seo_title' => 'City Frame One — повседневные кроссовки Shoria',
                'seo_description' => 'City Frame One: минималистичные кроссовки на каждый день с чистым силуэтом и комфортной посадкой.',
                'price' => 9990,
                'old_price' => null,
                'currency' => 'RUB',
                'stock' => 24,
                'is_featured' => true,
                'is_hit' => false,
                'is_new' => true,
                'is_customer_choice' => false,
                'sort_order' => 2,
                'image_url' => 'https://images.unsplash.com/photo-1549298916-b41d501d3772?auto=format&fit=crop&w=1200&q=80',
            ],
            [
                'name' => 'Neon Track X',
                'brand' => 'Puma',
                'slug' => 'neon-track-x',
                'sku' => 'SH-NEON-X',
                'category_slug' => 'street',
                'description' => 'Контрастный силуэт и яркая подошва.',
                'price' => 11990,
                'old_price' => 13990,
                'currency' => 'RUB',
                'stock' => 7,
                'is_featured' => true,
                'is_hit' => true,
                'is_new' => false,
                'is_customer_choice' => true,
                'sort_order' => 3,
                'image_url' => 'https://images.unsplash.com/photo-1515955656352-a1fa3ffcd111?auto=format&fit=crop&w=1200&q=80',
            ],
            [
                'name' => 'Vault Signature',
                'brand' => 'Balenciaga',
                'slug' => 'vault-signature',
                'sku' => 'SH-VAULT-S',
                'category_slug' => 'premium',
                'description' => 'Премиальная кожа и ограниченный тираж.',
                'seo_title' => 'Vault Signature — премиальная пара Shoria',
                'seo_description' => 'Vault Signature: премиальные материалы, ограниченный тираж и выразительная подача для коллекции.',
                'price' => 19990,
                'old_price' => null,
                'currency' => 'RUB',
                'stock' => 5,
                'is_featured' => true,
                'is_hit' => true,
                'is_new' => true,
                'is_customer_choice' => false,
                'sort_order' => 4,
                'image_url' => 'https://images.unsplash.com/photo-1607522370275-f14206abe5d3?auto=format&fit=crop&w=1200&q=80',
            ],
            [
                'name' => 'Cloud Step V2',
                'brand' => 'ASICS',
                'slug' => 'cloud-step-v2',
                'sku' => 'SH-CLOUD-V2',
                'category_slug' => 'lifestyle',
                'description' => 'Комфортная подошва и мягкая посадка.',
                'price' => 10990,
                'old_price' => null,
                'currency' => 'RUB',
                'stock' => 21,
                'is_featured' => false,
                'is_hit' => false,
                'is_new' => true,
                'is_customer_choice' => true,
                'sort_order' => 5,
                'image_url' => 'https://images.unsplash.com/photo-1605348532760-6753d2c43329?auto=format&fit=crop&w=1200&q=80',
            ],
            [
                'name' => 'Sprint Form Pro',
                'brand' => 'Nike',
                'slug' => 'sprint-form-pro',
                'sku' => 'SH-SPRINT-P',
                'category_slug' => 'running',
                'description' => 'Стабилизация стопы и улучшенный отклик.',
                'price' => 13990,
                'old_price' => 15990,
                'currency' => 'RUB',
                'stock' => 11,
                'is_featured' => false,
                'is_hit' => false,
                'is_new' => false,
                'is_customer_choice' => true,
                'sort_order' => 6,
                'image_url' => 'https://images.unsplash.com/photo-1600185365483-26d7a4cc7519?auto=format&fit=crop&w=1200&q=80',
            ],
            [
                'name' => 'Metro Glide',
                'brand' => 'New Balance',
                'slug' => 'metro-glide',
                'sku' => 'SH-METRO-GLIDE',
                'category_slug' => 'urban-comfort',
                'description' => 'Мягкая городская пара для долгих прогулок и поездок.',
                'price' => 10490,
                'old_price' => null,
                'currency' => 'RUB',
                'stock' => 18,
                'is_featured' => false,
                'is_hit' => false,
                'is_new' => true,
                'is_customer_choice' => false,
                'sort_order' => 7,
                'image_url' => 'https://images.unsplash.com/photo-1525966222134-fcfa99b8ae77?auto=format&fit=crop&w=1200&q=80',
            ],
            [
                'name' => 'Daily Ease',
                'brand' => 'Adidas',
                'slug' => 'daily-ease',
                'sku' => 'SH-DAILY-EASE',
                'category_slug' => 'classic-daily',
                'description' => 'Чистый силуэт без лишних деталей для повседневного гардероба.',
                'price' => 9490,
                'old_price' => 10990,
                'currency' => 'RUB',
                'stock' => 16,
                'is_featured' => false,
                'is_hit' => false,
                'is_new' => false,
                'is_customer_choice' => true,
                'sort_order' => 8,
                'image_url' => 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=1200&q=80',
            ],
            [
                'name' => 'Road Tempo Elite',
                'brand' => 'Nike',
                'slug' => 'road-tempo-elite',
                'sku' => 'SH-ROAD-TEMPO',
                'category_slug' => 'road-running',
                'description' => 'Упругая платформа для быстрых темповых сессий.',
                'seo_title' => 'Road Tempo Elite — беговые кроссовки для темпа',
                'seo_description' => 'Road Tempo Elite: беговая модель для асфальта, быстрых тренировок и высокой отзывчивости.',
                'price' => 15490,
                'old_price' => 16990,
                'currency' => 'RUB',
                'stock' => 9,
                'is_featured' => true,
                'is_hit' => true,
                'is_new' => true,
                'is_customer_choice' => true,
                'sort_order' => 9,
                'image_url' => 'https://images.unsplash.com/photo-1460353581641-37baddab0fa2?auto=format&fit=crop&w=1200&q=80',
            ],
            [
                'name' => 'Trail Ridge GTX',
                'brand' => 'Salomon',
                'slug' => 'trail-ridge-gtx',
                'sku' => 'SH-TRAIL-RIDGE',
                'category_slug' => 'trail-running',
                'description' => 'Защищенный верх и агрессивный протектор для пересеченной местности.',
                'price' => 16490,
                'old_price' => null,
                'currency' => 'RUB',
                'stock' => 6,
                'is_featured' => false,
                'is_hit' => true,
                'is_new' => false,
                'is_customer_choice' => false,
                'sort_order' => 10,
                'image_url' => 'https://images.unsplash.com/photo-1543508282-6319a3e2621f?auto=format&fit=crop&w=1200&q=80',
            ],
            [
                'name' => 'Block Tone High',
                'brand' => 'Converse',
                'slug' => 'block-tone-high',
                'sku' => 'SH-BLOCK-TONE',
                'category_slug' => 'bold-street',
                'description' => 'Высокий силуэт и контрастные панели для street-образов.',
                'price' => 12490,
                'old_price' => null,
                'currency' => 'RUB',
                'stock' => 12,
                'is_featured' => false,
                'is_hit' => false,
                'is_new' => true,
                'is_customer_choice' => true,
                'sort_order' => 11,
                'image_url' => 'https://images.unsplash.com/photo-1515955656352-a1fa3ffcd111?auto=format&fit=crop&w=1200&q=80',
            ],
            [
                'name' => 'Archive Reserve',
                'brand' => 'Converse',
                'slug' => 'archive-reserve',
                'sku' => 'SH-ARCHIVE-RESERVE',
                'category_slug' => 'limited-edition',
                'description' => 'Коллекционный релиз с ограниченным тиражом и премиальной отделкой.',
                'price' => 22990,
                'old_price' => 24990,
                'currency' => 'RUB',
                'stock' => 4,
                'is_featured' => true,
                'is_hit' => true,
                'is_new' => true,
                'is_customer_choice' => false,
                'sort_order' => 12,
                'image_url' => 'https://images.unsplash.com/photo-1607522370275-f14206abe5d3?auto=format&fit=crop&w=1200&q=80',
                'additional_category_slugs' => ['premium'],
            ],
        ];

        $brands = collect();

        foreach (collect($products)->pluck('brand')->unique()->values() as $brandName) {
            $brand = Brand::query()->firstOrCreate(
                ['name' => $brandName],
                [
                    'slug' => \Illuminate\Support\Str::slug($brandName),
                    'is_active' => true,
                    'sort_order' => 0,
                ],
            );

            $brands->put($brand->name, $brand);
        }

        foreach ($products as $item) {
            $brandName = trim((string) ($item['brand'] ?? 'Shoria'));
            $brand = $brands->get($brandName);

            $product = Product::query()->firstOrCreate(
                ['slug' => $item['slug']],
                [
                    'category_id' => $categories[$item['category_slug']]->id,
                    'brand_id' => $brand?->id,
                    'name' => $item['name'],
                    'brand' => $item['brand'],
                    'sku' => $item['sku'],
                    'description' => $item['description'],
                    'price' => $item['price'],
                    'old_price' => $item['old_price'],
                    'currency' => $item['currency'],
                    'stock' => $item['stock'],
                    'is_featured' => $item['is_featured'],
                    'is_hit' => $item['is_hit'],
                    'is_new' => $item['is_new'],
                    'is_customer_choice' => $item['is_customer_choice'],
                    'is_active' => true,
                    'sort_order' => $item['sort_order'],
                ],
            );

            $product->fill([
                'brand_id' => $product->brand_id ?: $brand?->id,
                'brand' => $product->brand ?: ($item['brand'] ?? 'Shoria'),
                'seo_title' => $product->seo_title ?: ($item['seo_title'] ?? null),
                'seo_description' => $product->seo_description ?: ($item['seo_description'] ?? null),
            ]);
            $product->save();

            $categorySlugs = collect([
                $item['category_slug'] ?? null,
                ...($item['additional_category_slugs'] ?? []),
            ])
                ->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
                ->map(fn (string $value): string => trim($value))
                ->unique()
                ->values();

            $categoryIds = $categories
                ->only($categorySlugs->all())
                ->map(fn (Category $category): int => $category->id)
                ->values()
                ->all();

            $product->categories()->sync($categoryIds);

            ProductImage::query()->firstOrCreate(
                [
                    'product_id' => $product->id,
                    'is_cover' => true,
                ],
                [
                    'url' => $item['image_url'],
                    'alt' => $item['name'],
                    'sort_order' => 1,
                ],
            );

            $variantBlueprints = match ($item['slug']) {
                'cloud-step-v2' => [
                    [
                        'slug' => 'graphite-eu-40',
                        'color_label' => 'Graphite',
                        'size_label' => 'EU 40',
                        'stock' => 2,
                        'sort_order' => 1,
                        'image_url' => 'https://images.unsplash.com/photo-1515955656352-a1fa3ffcd111?auto=format&fit=crop&w=1200&q=80',
                    ],
                    [
                        'slug' => 'graphite-eu-41',
                        'color_label' => 'Graphite',
                        'size_label' => 'EU 41',
                        'stock' => 4,
                        'sort_order' => 2,
                    ],
                    [
                        'slug' => 'sky-eu-42',
                        'color_label' => 'Sky Blue',
                        'size_label' => 'EU 42',
                        'stock' => 5,
                        'sort_order' => 3,
                        'image_url' => 'https://images.unsplash.com/photo-1605348532760-6753d2c43329?auto=format&fit=crop&w=1200&q=80',
                    ],
                    [
                        'slug' => 'sky-eu-43',
                        'color_label' => 'Sky Blue',
                        'size_label' => 'EU 43',
                        'stock' => 3,
                        'sort_order' => 4,
                    ],
                ],
                'sprint-form-pro' => [
                    [
                        'slug' => 'sunset-eu-40',
                        'color_label' => 'Sunset',
                        'size_label' => 'EU 40',
                        'stock' => 2,
                        'sort_order' => 1,
                        'image_url' => 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=1200&q=80',
                    ],
                    [
                        'slug' => 'sunset-eu-41',
                        'color_label' => 'Sunset',
                        'size_label' => 'EU 41',
                        'stock' => 4,
                        'sort_order' => 2,
                    ],
                    [
                        'slug' => 'bone-eu-42',
                        'color_label' => 'Bone',
                        'size_label' => 'EU 42',
                        'stock' => 5,
                        'sort_order' => 3,
                        'image_url' => 'https://images.unsplash.com/photo-1460353581641-37baddab0fa2?auto=format&fit=crop&w=1200&q=80',
                    ],
                    [
                        'slug' => 'bone-eu-43',
                        'color_label' => 'Bone',
                        'size_label' => 'EU 43',
                        'stock' => 3,
                        'sort_order' => 4,
                    ],
                ],
                default => [],
            };

            if ($variantBlueprints !== []) {
                $product->variants()
                    ->whereNull('color_label')
                    ->whereIn('size_label', ['EU 40', 'EU 41', 'EU 42', 'EU 43'])
                    ->delete();
            }

            foreach ($variantBlueprints as $variantBlueprint) {
                $variant = ProductVariant::query()->firstOrCreate(
                    [
                        'product_id' => $product->id,
                        'slug' => $variantBlueprint['slug'],
                    ],
                    [
                        'size_label' => $variantBlueprint['size_label'],
                        'color_label' => $variantBlueprint['color_label'],
                        'sku' => $item['sku'] . '-' . str_replace(' ', '', $variantBlueprint['size_label']),
                        'price' => null,
                        'stock' => $variantBlueprint['stock'],
                        'is_active' => true,
                        'sort_order' => $variantBlueprint['sort_order'],
                    ],
                );

                if (! isset($variantBlueprint['image_url'])) {
                    continue;
                }

                ProductVariantImage::query()->firstOrCreate(
                    [
                        'product_variant_id' => $variant->id,
                        'is_cover' => true,
                    ],
                    [
                        'url' => $variantBlueprint['image_url'],
                        'alt' => "{$item['name']} {$variantBlueprint['color_label']}",
                        'sort_order' => 1,
                    ],
                );
            }
        }

        NewsPost::query()->firstOrCreate([
            'slug' => 'how-to-choose-running-shoes',
        ], [
            'title' => 'Как выбрать первую беговую пару',
            'slug' => 'how-to-choose-running-shoes',
            'content_type' => 'guide',
            'excerpt' => 'Короткий гайд: амортизация, перепад и под ваш темп.',
            'content' => 'Полный материал для блога будет расширен в следующем спринте.',
            'cover_url' => 'https://images.unsplash.com/photo-1483721310020-03333e577078?auto=format&fit=crop&w=1200&q=80',
            'seo_title' => 'Как выбрать первую беговую пару — гайд Shoria',
            'seo_description' => 'Разбираем, как выбрать первые беговые кроссовки: амортизация, перепад подошвы и сценарий использования.',
            'published_at' => Carbon::now()->subDays(2),
            'is_published' => true,
        ]);
        NewsPost::query()->where('slug', 'how-to-choose-running-shoes')->update([
            'content_type' => 'guide',
            'seo_title' => \DB::raw("COALESCE(seo_title, 'Как выбрать первую беговую пару — гайд Shoria')"),
            'seo_description' => \DB::raw("COALESCE(seo_description, 'Разбираем, как выбрать первые беговые кроссовки: амортизация, перепад подошвы и сценарий использования.')"),
        ]);

        NewsPost::query()->firstOrCreate([
            'slug' => 'streetwear-color-trend-2026',
        ], [
            'title' => 'Тренд 2026: спокойные оттенки в streetwear',
            'slug' => 'streetwear-color-trend-2026',
            'content_type' => 'collection',
            'excerpt' => 'Почему бежевые и графитовые тона снова в топе.',
            'content' => 'Полный материал для блога будет расширен в следующем спринте.',
            'cover_url' => 'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?auto=format&fit=crop&w=1200&q=80',
            'seo_title' => 'Тренд 2026: спокойные оттенки в streetwear',
            'seo_description' => 'Почему спокойные и нейтральные оттенки снова стали важной частью streetwear-гардероба.',
            'published_at' => Carbon::now()->subDay(),
            'is_published' => true,
        ]);
        NewsPost::query()->where('slug', 'streetwear-color-trend-2026')->update([
            'content_type' => 'collection',
            'seo_title' => \DB::raw("COALESCE(seo_title, 'Тренд 2026: спокойные оттенки в streetwear')"),
            'seo_description' => \DB::raw("COALESCE(seo_description, 'Почему спокойные и нейтральные оттенки снова стали важной частью streetwear-гардероба.')"),
        ]);

        NewsPost::query()->firstOrCreate([
            'slug' => 'white-sneakers-care',
        ], [
            'title' => 'Как ухаживать за белыми кроссовками',
            'slug' => 'white-sneakers-care',
            'content_type' => 'news',
            'excerpt' => 'Простые привычки, чтобы пара дольше выглядела новой.',
            'content' => 'Полный материал для блога будет расширен в следующем спринте.',
            'cover_url' => 'https://images.unsplash.com/photo-1517841905240-472988babdf9?auto=format&fit=crop&w=1200&q=80',
            'seo_title' => 'Как ухаживать за белыми кроссовками — советы Shoria',
            'seo_description' => 'Практические советы по уходу за белыми кроссовками, чтобы пара дольше выглядела аккуратно и свежо.',
            'published_at' => Carbon::now(),
            'is_published' => true,
        ]);
        NewsPost::query()->where('slug', 'white-sneakers-care')->update([
            'content_type' => 'news',
            'seo_title' => \DB::raw("COALESCE(seo_title, 'Как ухаживать за белыми кроссовками — советы Shoria')"),
            'seo_description' => \DB::raw("COALESCE(seo_description, 'Практические советы по уходу за белыми кроссовками, чтобы пара дольше выглядела аккуратно и свежо.')"),
        ]);

        $newsProductLinks = [
            'how-to-choose-running-shoes' => ['road-tempo-elite', 'sprint-form-pro', 'aero-pulse-300'],
            'streetwear-color-trend-2026' => ['city-frame-one', 'block-tone-high', 'archive-reserve'],
            'white-sneakers-care' => ['city-frame-one', 'cloud-step-v2', 'daily-ease'],
        ];

        foreach ($newsProductLinks as $newsSlug => $productSlugs) {
            $post = NewsPost::query()->where('slug', $newsSlug)->first();

            if (! $post) {
                continue;
            }

            $productIds = Product::query()
                ->whereIn('slug', $productSlugs)
                ->pluck('id')
                ->all();

            if ($productIds !== []) {
                $post->products()->syncWithoutDetaching($productIds);
            }
        }
    }
}
