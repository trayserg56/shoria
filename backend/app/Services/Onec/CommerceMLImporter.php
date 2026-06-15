<?php

namespace App\Services\Onec;

use App\Models\Category;
use App\Models\OnecExchangeSession;
use App\Models\PriceType;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use App\Services\Stock\StockService;
use App\Support\ProductCharacteristics;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use SimpleXMLElement;

class CommerceMLImporter
{
    public function __construct(private StockService $stockService) {}

    private function parseXml(string $xmlContent): SimpleXMLElement
    {
        // 1С добавляет xmlns namespace, который ломает обращение через ->TagName
        // Убираем его для упрощения парсинга — структура при этом сохраняется
        $xmlContent = preg_replace('/\sxmlns(?::\w+)?="[^"]*"/', '', $xmlContent);
        return new SimpleXMLElement($xmlContent);
    }

    public function importCatalog(string $xmlContent): array
    {
        $session = OnecExchangeSession::create([
            'direction' => 'import',
            'type' => 'products',
            'status' => 'started',
            'started_at' => now(),
        ]);

        try {
            $xml = $this->parseXml($xmlContent);

            $counts = ['created' => 0, 'updated' => 0, 'skipped' => 0];

            // Строим карту свойств: id → ['name' => ..., 'group' => ...]
            $propertyMap = $this->buildPropertyMap($xml);

            // Импорт категорий
            foreach ($xml->Классификатор->Группы->Группа ?? [] as $group) {
                $this->importCategory($group, null, $counts);
            }

            // Импорт товаров
            foreach ($xml->Каталог->Товары->Товар ?? [] as $item) {
                $this->importProduct($item, $counts, $propertyMap);
            }

            $session->markSuccess([
                'records_processed' => $counts['created'] + $counts['updated'] + $counts['skipped'],
                'records_created' => $counts['created'],
                'records_updated' => $counts['updated'],
                'records_skipped' => $counts['skipped'],
            ]);

            return $counts;
        } catch (\Throwable $e) {
            $session->markError($e->getMessage());
            throw $e;
        }
    }

    public function importOffers(string $xmlContent): array
    {
        $session = OnecExchangeSession::create([
            'direction' => 'import',
            'type' => 'prices',
            'status' => 'started',
            'started_at' => now(),
        ]);

        try {
            $xml = $this->parseXml($xmlContent);
            $counts = ['created' => 0, 'updated' => 0, 'skipped' => 0];

            $this->importPricesAndStock($xml, $counts);

            $session->markSuccess([
                'records_processed' => $counts['created'] + $counts['updated'] + $counts['skipped'],
                'records_created' => $counts['created'],
                'records_updated' => $counts['updated'],
                'records_skipped' => $counts['skipped'],
            ]);

            return $counts;
        } catch (\Throwable $e) {
            $session->markError($e->getMessage());
            throw $e;
        }
    }

    private function importCategory(SimpleXMLElement $group, ?int $parentId, array &$counts): void
    {
        $externalId = (string) $group->Ид;
        $name = (string) $group->Наименование;

        if (! $externalId || ! $name) {
            $counts['skipped']++;
            return;
        }

        $category = Category::firstOrNew(['external_id' => $externalId]);
        $isNew = ! $category->exists;

        if (! $category->slug) {
            $base = Str::slug($name) ?: Str::slug($externalId);
            $slug = $base;
            $i = 2;
            while (Category::where('slug', $slug)->when($category->exists, fn ($q) => $q->where('id', '!=', $category->id))->exists()) {
                $slug = "{$base}-{$i}";
                $i++;
            }
            $category->slug = $slug;
        }

        $category->fill([
            'name' => $name,
            'parent_id' => $parentId,
            'is_active' => true,
        ]);
        $category->save();

        $isNew ? $counts['created']++ : $counts['updated']++;

        // Рекурсивно импортируем подгруппы
        foreach ($group->Группы->Группа ?? [] as $child) {
            $this->importCategory($child, $category->id, $counts);
        }
    }

    private function buildPropertyMap(SimpleXMLElement $xml): array
    {
        $map = [];
        foreach ($xml->Классификатор->Свойства->Свойство ?? [] as $prop) {
            $id = (string) $prop->Ид;
            $name = (string) $prop->Наименование;
            $group = (string) ($prop->Группа ?? 'Общие характеристики');
            if ($id && $name) {
                $map[$id] = ['name' => $name, 'group' => $group ?: 'Общие характеристики'];
            }
        }
        return $map;
    }

    private function importProduct(SimpleXMLElement $item, array &$counts, array $propertyMap = []): void
    {
        $externalId = (string) $item->Ид;
        $name = (string) $item->Наименование;

        if (! $externalId || ! $name) {
            $counts['skipped']++;
            return;
        }

        $product = Product::firstOrNew(['external_id' => $externalId]);
        $isNew = ! $product->exists;

        if (! $product->slug) {
            $base = Str::slug($name) ?: Str::slug($externalId);
            $slug = $base;
            $i = 2;
            while (Product::where('slug', $slug)->when($product->exists, fn ($q) => $q->where('id', '!=', $product->id))->exists()) {
                $slug = "{$base}-{$i}";
                $i++;
            }
            $product->slug = $slug;
        }

        // Категория
        $categoryExternalId = (string) ($item->Группы->Ид ?? '');
        $categoryId = $categoryExternalId
            ? Category::where('external_id', $categoryExternalId)->value('id')
            : $product->category_id;

        $barcode = (string) ($item->Штрихкод ?? '');
        $sku = (string) ($item->Артикул ?? $externalId);
        $description = (string) ($item->Описание ?? '');

        // Не обновляем SKU если он занят другим товаром
        $skuConflict = Product::where('sku', $sku)
            ->when($product->exists, fn ($q) => $q->where('id', '!=', $product->id))
            ->exists();

        $product->fill([
            'name' => $name,
            'external_id' => $externalId,
            'sku' => $skuConflict ? ($product->sku ?: $externalId) : $sku,
            'barcode' => $barcode ?: null,
            'description' => $description,
            'category_id' => $categoryId ?? $product->category_id,
            'is_active' => true,
        ]);

        if ($isNew) {
            $product->price = 0;
            $product->stock = 0;
            $product->brand = '';
            $product->is_hit = false;
            $product->is_new = false;
            $product->is_customer_choice = false;
            $product->is_featured = false;
            $product->sort_order = 0;
        }

        $product->save();

        // Изображения
        $this->importProductImages($product, $item);

        // Свойства товара → characteristics JSON
        $this->importProductProperties($product, $item, $propertyMap);

        // Варианты (размеры / цвета)
        foreach ($item->ХарактеристикиТовара->ХарактеристикаТовара ?? [] as $char) {
            $this->importVariant($product, $char, $counts);
        }

        $isNew ? $counts['created']++ : $counts['updated']++;
    }

    private function importProductProperties(Product $product, SimpleXMLElement $item, array $propertyMap): void
    {
        $rows = [];

        foreach ($item->ЗначенияСвойств->ЗначениеСвойства ?? [] as $propValue) {
            $propId = (string) $propValue->Ид;
            $value = trim((string) $propValue->Значение);

            if (! $propId || $value === '') {
                continue;
            }

            $meta = $propertyMap[$propId] ?? ['name' => $propId, 'group' => 'Характеристики'];

            // Разбиваем значения через " / " — так же как expandForStorage,
            // чтобы whereJsonContains в фильтре каталога работал корректно
            foreach (ProductCharacteristics::splitSlashJoinedList($value) as $piece) {
                $rows[] = [
                    'group' => $meta['group'],
                    'name' => $meta['name'],
                    'value' => $piece,
                ];
            }
        }

        if (! empty($rows)) {
            $product->characteristics = $rows;
            $product->saveQuietly();
        }
    }

    private function importProductImages(Product $product, SimpleXMLElement $item): void
    {
        // Собираем все пути из <Картинка> и <Картинки><Картинка>
        $paths = [];

        if (isset($item->Картинка) && (string) $item->Картинка !== '') {
            $paths[] = (string) $item->Картинка;
        }

        foreach ($item->Картинки->Картинка ?? [] as $img) {
            $path = (string) $img;
            if ($path !== '') {
                $paths[] = $path;
            }
        }

        if (empty($paths)) {
            return;
        }

        $existingUrls = $product->images()->pluck('url')->map(fn ($u) => basename($u))->flip();
        $sortOrder = $product->images()->max('sort_order') ?? 0;
        $hasCover = $product->images()->where('is_cover', true)->exists();

        foreach ($paths as $srcPath) {
            // srcPath: "import_files/product-001.jpg" или просто "product-001.jpg"
            $filename = basename($srcPath);

            if ($existingUrls->has($filename)) {
                continue; // уже загружено
            }

            // Ищем файл на диске (1С загрузил его раньше через mode=file)
            $storageSrc = '1c-import/' . ltrim($srcPath, '/');
            if (! Storage::disk('public')->exists($storageSrc)) {
                continue; // файл ещё не пришёл — пропускаем
            }

            // Копируем в постоянную папку
            $destPath = 'products/1c/' . $filename;
            Storage::disk('public')->copy($storageSrc, $destPath);

            $sortOrder++;
            ProductImage::create([
                'product_id' => $product->id,
                'url' => $destPath,
                'alt' => $product->name,
                'is_cover' => ! $hasCover,
                'sort_order' => $sortOrder,
            ]);

            $hasCover = true;
        }
    }

    private function importVariant(Product $product, SimpleXMLElement $char, array &$counts): void
    {
        $externalId = (string) $char->Ид;
        $name = (string) $char->Наименование;

        if (! $externalId) {
            return;
        }

        $variant = ProductVariant::firstOrNew(['external_id' => $externalId]);
        $isNew = ! $variant->exists;

        $variant->fill([
            'product_id' => $product->id,
            'external_id' => $externalId,
            'size_label' => $name,
            'sku' => (string) ($char->Артикул ?? $externalId),
            'barcode' => (string) ($char->Штрихкод ?? '') ?: null,
            'is_active' => true,
        ]);

        if ($isNew) {
            $variant->price = $product->price;
            $variant->stock = 0;
        }

        $variant->save();
    }

    private function importPricesAndStock(SimpleXMLElement $xml, array &$counts): void
    {
        $defaultWarehouse = Warehouse::default();
        $retailType = PriceType::retail() ?? PriceType::default();

        // Типы цен из XML
        $priceTypeMap = [];
        $isFirstPriceType = true;
        foreach ($xml->ПакетПредложений->ТипыЦен->ТипЦены ?? [] as $priceTypeXml) {
            $extId = (string) $priceTypeXml->Ид;
            $code = Str::slug((string) $priceTypeXml->Наименование, '_') ?: $extId;
            $name = (string) $priceTypeXml->Наименование;

            // Первый тип цены из 1С привязываем к существующему розничному (если ещё не привязан)
            if ($isFirstPriceType && $retailType && ! $retailType->external_id) {
                PriceType::where('external_id', $extId)->where('id', '!=', $retailType->id)->update(['external_id' => null]);
                $retailType->update(['external_id' => $extId]);
                $priceTypeMap[$extId] = $retailType->id;
                $isFirstPriceType = false;
                continue;
            } elseif ($isFirstPriceType && $retailType && $retailType->external_id === $extId) {
                $priceTypeMap[$extId] = $retailType->id;
                $isFirstPriceType = false;
                continue;
            }

            $pt = PriceType::firstOrCreate(
                ['external_id' => $extId],
                ['code' => $code, 'name' => $name, 'is_default' => false]
            );
            $priceTypeMap[$extId] = $pt->id;
            $isFirstPriceType = false;
        }

        // Склады из XML
        $warehouseMap = [];
        foreach ($xml->ПакетПредложений->Склады->Склад ?? [] as $warehouseXml) {
            $extId = (string) $warehouseXml->Ид;
            $wName = (string) $warehouseXml->Наименование;

            $wh = Warehouse::firstOrCreate(
                ['external_id' => $extId],
                ['name' => $wName, 'code' => Str::slug($wName, '_') ?: $extId, 'is_active' => true, 'is_default' => false]
            );
            $warehouseMap[$extId] = $wh->id;
        }

        foreach ($xml->ПакетПредложений->Предложения->Предложение ?? [] as $offer) {
            $externalId = (string) $offer->Ид;
            // Ид может быть "ProductUUID#VariantUUID"
            [$productExtId, $variantExtId] = array_pad(explode('#', $externalId, 2), 2, null);

            $product = Product::where('external_id', $productExtId)->first();
            if (! $product) {
                $counts['skipped']++;
                continue;
            }

            // Вариант ищем сначала по полному external_id (PROD#VAR), потом по короткому (VAR)
            $variant = null;
            if ($variantExtId) {
                $variant = ProductVariant::where('external_id', $externalId)->first()
                    ?? ProductVariant::where('external_id', $variantExtId)->first();
            }

            // Цены
            foreach ($offer->Цены->Цена ?? [] as $priceXml) {
                $ptExtId = (string) $priceXml->ИдТипаЦены;
                $priceVal = (float) str_replace(',', '.', (string) $priceXml->ЦенаЗаЕдиницу);
                $currency = (string) ($priceXml->Валюта ?? 'RUB');

                $priceTypeId = $priceTypeMap[$ptExtId] ?? $retailType?->id;
                if (! $priceTypeId) {
                    continue;
                }

                \App\Models\ProductPrice::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'product_variant_id' => $variant?->id,
                        'price_type_id' => $priceTypeId,
                    ],
                    ['price' => $priceVal, 'currency' => $currency]
                );

                // Синхронизируем основную цену товара/варианта из розничного типа
                if ($priceTypeId === $retailType?->id) {
                    if ($variant) {
                        $variant->update(['price' => $priceVal]);
                    } else {
                        $product->update(['price' => $priceVal]);
                    }
                }
            }

            // Остатки по складам
            foreach ($offer->Склады->СкладОстаток ?? [] as $stockXml) {
                $whExtId = (string) $stockXml->ИдСклада;
                $qty = (int) $stockXml->Количество;
                $whId = $warehouseMap[$whExtId] ?? $defaultWarehouse?->id;

                if (! $whId) {
                    continue;
                }

                $this->stockService->adjust(
                    $whId,
                    $product->id,
                    $variant?->id,
                    $qty,
                    'Синхронизация 1С',
                    $externalId
                );
            }

            $counts['updated']++;
        }
    }
}
