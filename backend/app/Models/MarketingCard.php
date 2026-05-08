<?php

namespace App\Models;

use App\Models\Concerns\HasAuthorship;
use App\Models\Concerns\InvalidatesCatalogCache;
use App\Support\MediaUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class MarketingCard extends Model
{
    use HasAuthorship, InvalidatesCatalogCache;

    protected $fillable = [
        'label',
        'title',
        'image_url',
        'link_url',
        'lines',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'lines' => 'array',
    ];

    /**
     * @return array{id: int, label: string|null, title: string, image_url: string|null, link_url: string, lines: array<int, string>}
     */
    public function toApiArray(): array
    {
        $lines = collect($this->lines ?? [])
            ->filter(fn ($line): bool => is_string($line) && trim($line) !== '')
            ->map(fn (string $line): string => trim($line))
            ->values()
            ->all();

        $link = trim((string) ($this->link_url ?? ''));

        return [
            'id' => $this->id,
            'label' => $this->label,
            'title' => $this->title,
            'image_url' => $this->image_url,
            'link_url' => $link,
            'lines' => $lines,
        ];
    }

    /**
     * @param  Builder<MarketingCard>  $query
     * @return Builder<MarketingCard>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<MarketingCard>  $query
     * @return Builder<MarketingCard>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @param  Builder<MarketingCard>  $query
     * @return Builder<MarketingCard>
     */
    public function scopeWithValidLink(Builder $query): Builder
    {
        return $query->whereNotNull('link_url')->where('link_url', '!=', '');
    }

    protected function imageUrl(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => MediaUrl::resolve($value),
        );
    }
}
