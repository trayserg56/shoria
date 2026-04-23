<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class LoadTestCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $categories = $this->seedCategories();
        $this->seedProducts($categories);
    }

    /**
     * @return array<int, \App\Models\Category>
     */
    private function seedCategories(): array
    {
        $categoryNames = [
            'Электроника',
            'Одежда',
            'Дом и кухня',
            'Красота',
            'Спорт и фитнес',
            'Автотовары',
            'Детские товары',
            'Книги и хобби',
            'Питомцы',
            'Туризм и отдых',
        ];

        $categories = [];

        foreach ($categoryNames as $index => $name) {
            $categoryNumber = $index + 1;

            $categories[] = Category::query()->updateOrCreate(
                ['slug' => sprintf('load-category-%02d', $categoryNumber)],
                [
                    'name' => $name,
                    'description' => 'Категория для нагрузочного теста каталога.',
                    'image_url' => sprintf('https://picsum.photos/seed/load-category-%02d/800/520', $categoryNumber),
                    'is_featured' => false,
                    'sort_order' => 1000 + $categoryNumber,
                ],
            );
        }

        return $categories;
    }

    /**
     * @param array<int, \App\Models\Category> $categories
     */
    private function seedProducts(array $categories): void
    {
        $prefixes = [
            'Ultra',
            'Smart',
            'Prime',
            'Active',
            'Max',
            'Pro',
            'Eco',
            'Lite',
            'Nova',
            'Flex',
        ];

        $suffixes = [
            'Edition',
            'Series',
            'Pack',
            'Set',
            'Line',
            'Kit',
            'Model',
            'Version',
            'Select',
            'Choice',
        ];

        $brands = [
            'Shoria',
            'Nike',
            'Adidas',
            'Puma',
            'ASICS',
            'New Balance',
            'Reebok',
            'Converse',
            'Under Armour',
            'Salomon',
        ];

        for ($i = 1; $i <= 200; $i++) {
            $category = $categories[($i - 1) % count($categories)];
            $prefix = $prefixes[$i % count($prefixes)];
            $suffix = $suffixes[$i % count($suffixes)];
            $brand = $brands[$i % count($brands)];
            $name = sprintf('%s Product %03d %s', $prefix, $i, $suffix);

            $product = Product::query()->updateOrCreate(
                ['slug' => sprintf('load-item-%03d', $i)],
                [
                    'category_id' => $category->id,
                    'name' => $name,
                    'brand' => $brand,
                    'sku' => sprintf('LDT-%03d', $i),
                    'description' => sprintf(
                        'Тестовый товар #%03d для проверки производительности витрины и пагинации каталога.',
                        $i,
                    ),
                    'price' => 490 + ($i * 130),
                    'old_price' => $i % 3 === 0 ? 690 + ($i * 130) : null,
                    'currency' => 'RUB',
                    'is_featured' => false,
                    'is_hit' => $i % 7 === 0,
                    'is_new' => $i % 5 === 0,
                    'is_customer_choice' => $i % 9 === 0,
                    'is_active' => true,
                    'stock' => 5 + ($i % 60),
                    'sort_order' => 1000 + $i,
                ],
            );

            ProductImage::query()->updateOrCreate(
                [
                    'product_id' => $product->id,
                    'sort_order' => 1,
                ],
                [
                    'url' => sprintf('https://picsum.photos/seed/load-item-%03d/1200/900', $i),
                    'alt' => Str::limit($name, 120),
                    'is_cover' => true,
                ],
            );
        }
    }
}
