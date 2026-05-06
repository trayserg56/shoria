<?php

namespace Tests\Unit;

use App\Support\ProductCharacteristics;
use PHPUnit\Framework\TestCase;

class ProductCharacteristicsTest extends TestCase
{
    public function test_split_slash_joined_list_splits_space_slash_space(): void
    {
        $this->assertSame(
            ['Текстиль', 'синтетика'],
            ProductCharacteristics::splitSlashJoinedList('Текстиль / синтетика'),
        );
    }

    public function test_split_slash_joined_list_preserves_single_and_sizes(): void
    {
        $this->assertSame(['Резина'], ProductCharacteristics::splitSlashJoinedList('Резина'));
        $this->assertSame(['42/43'], ProductCharacteristics::splitSlashJoinedList('42/43'));
    }

    public function test_flatten_splits_legacy_combined_value_into_tuples(): void
    {
        $raw = [
            [
                'group' => 'Общие характеристики',
                'name' => 'Материал верха',
                'value' => 'Текстиль / синтетика',
            ],
        ];

        $flat = ProductCharacteristics::flattenTuples($raw);
        $this->assertCount(2, $flat);
        $this->assertSame('Текстиль', $flat[0]['value']);
        $this->assertSame('синтетика', $flat[1]['value']);
    }

    public function test_grouped_for_api_merges_split_values(): void
    {
        $raw = [
            [
                'group' => 'Общие характеристики',
                'name' => 'Материал верха',
                'value' => 'Текстиль / синтетика',
            ],
            [
                'group' => 'Общие характеристики',
                'name' => 'Материал верха',
                'value' => 'Сетка',
            ],
        ];

        $grouped = ProductCharacteristics::groupedForApi($raw);
        $this->assertCount(1, $grouped);
        $this->assertSame(['Текстиль', 'синтетика', 'Сетка'], $grouped[0]['values']);
    }
}
