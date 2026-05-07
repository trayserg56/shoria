<?php

namespace App\Models;

use App\Models\Concerns\HasAuthorship;
use App\Models\Concerns\InvalidatesCatalogCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;

class Product extends Model
{
    use HasAuthorship, HasFactory, InvalidatesCatalogCache, Searchable;

    protected $fillable = [
        'category_id',
        'brand_id',
        'name',
        'brand',
        'slug',
        'sku',
        'description',
        'characteristics',
        'seo_title',
        'seo_description',
        'price',
        'old_price',
        'currency',
        'is_featured',
        'is_hit',
        'is_new',
        'is_customer_choice',
        'is_active',
        'stock',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'old_price' => 'decimal:2',
        'is_featured' => 'boolean',
        'is_hit' => 'boolean',
        'is_new' => 'boolean',
        'is_customer_choice' => 'boolean',
        'is_active' => 'boolean',
        'characteristics' => 'array',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)
            ->withTimestamps();
    }

    public function brandEntity(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function newsPosts(): BelongsToMany
    {
        return $this->belongsToMany(NewsPost::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    /**
     * Метки витрины (хит / новинка / выбор покупателей) для JSON каталога и главной.
     *
     * @return array<int, array{code: string, label: string}>
     */
    public function tagsForApi(): array
    {
        $tags = [];

        if ($this->is_hit) {
            $tags[] = ['code' => 'hit', 'label' => 'Хит'];
        }

        if ($this->is_new) {
            $tags[] = ['code' => 'new', 'label' => 'Новинка'];
        }

        if ($this->is_customer_choice) {
            $tags[] = ['code' => 'customer_choice', 'label' => 'Выбор покупателей'];
        }

        return $tags;
    }

    public static function transliterateLatinToCyrillic(string $value): string
    {
        $map = [
            'a' => 'а', 'b' => 'б', 'v' => 'в', 'g' => 'г', 'd' => 'д', 'e' => 'е',
            'zh' => 'ж', 'z' => 'з', 'i' => 'и', 'y' => 'й', 'k' => 'к', 'l' => 'л',
            'm' => 'м', 'n' => 'н', 'o' => 'о', 'p' => 'п', 'r' => 'р', 's' => 'с',
            't' => 'т', 'u' => 'у', 'f' => 'ф', 'h' => 'х', 'ts' => 'ц', 'ch' => 'ч',
            'shch' => 'щ', 'sh' => 'ш', 'yu' => 'ю', 'ya' => 'я',
            'c' => 'к', 'q' => 'к', 'x' => 'кс', 'w' => 'в', 'j' => 'дж',
        ];

        // Sort keys by length descending to match longest first (e.g. 'shch' before 's')
        $keys = array_keys($map);
        usort($keys, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        $result = mb_strtolower($value);
        foreach ($keys as $key) {
            $result = str_replace($key, $map[$key], $result);
        }

        return $result;
    }

    public function toSearchableArray(): array
    {
        $array = $this->toArray();
        $array['category_slug'] = $this->category?->slug;
        $array['category_name'] = $this->category?->name;

        $nameCyrillic = self::transliterateLatinToCyrillic($array['name'] ?? '');
        $brandCyrillic = self::transliterateLatinToCyrillic($array['brand'] ?? '');

        $brand = mb_strtolower(trim((string) ($array['brand'] ?? '')));
        $customSynonyms = match ($brand) {
            'nike' => 'найк',
            'asics' => 'асикс',
            'puma' => 'пума',
            'reebok' => 'рибок',
            'adidas' => 'адидас',
            'new balance' => 'нью баланс',
            'salomon' => 'саломон',
            'under armour' => 'андер армор',
            'balenciaga' => 'баленсиага',
            'shoria' => 'шория',
            default => '',
        };

        // Мы не индексируем все поля, только то, что полезно для поиска или фильтров
        return [
            'id' => $array['id'],
            'name' => $array['name'],
            'brand' => $array['brand'],
            'slug' => $array['slug'],
            'sku' => $array['sku'],
            'description' => strip_tags((string) $array['description']),
            'price' => (float) $array['price'],
            'stock' => (int) $array['stock'],
            'is_active' => (bool) $array['is_active'],
            'category_id' => $array['category_id'],
            'category_name' => $array['category_name'],
            'category_slug' => $array['category_slug'],
            'is_hit' => (bool) $array['is_hit'],
            'is_new' => (bool) $array['is_new'],
            'is_customer_choice' => (bool) $array['is_customer_choice'],
            'tags' => array_column($this->tagsForApi(), 'code'),
            'sort_order' => (int) $array['sort_order'],
            'search_synonyms' => trim($nameCyrillic.' '.$brandCyrillic.' '.$customSynonyms),
        ];
    }

    public function shouldBeSearchable(): bool
    {
        return $this->is_active === true;
    }

    protected static function booted(): void
    {
        static::saving(function (self $product): void {
            if (! $product->brand_id) {
                return;
            }

            $brandName = $product->brandEntity?->name
                ?? Brand::query()->whereKey($product->brand_id)->value('name');

            if (is_string($brandName) && trim($brandName) !== '') {
                $product->brand = $brandName;
            }
        });
    }
}
