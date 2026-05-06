<?php

namespace App\Support;

/**
 * Характеристики в БД хранятся как плоский массив { group, name, value } —
 * несколько объектов с одним name дают множественное значение для фильтра и API.
 */
final class ProductCharacteristics
{
    /**
     * Одна строка с перечислением через слэш (как в старой миграции «Текстиль / синтетика»)
     * превращается в несколько отдельных значений для API, фильтров и ссылок.
     *
     * Разбираем только « / » (слишком рискованно резать любой символ '/' — размеры, дроби).
     *
     * @return list<string>
     */
    public static function splitSlashJoinedList(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        if (! str_contains($value, ' / ')) {
            return [$value];
        }

        $parts = array_map('trim', explode(' / ', $value));
        $parts = array_values(array_filter($parts, static fn (string $p): bool => $p !== ''));

        return $parts !== [] ? $parts : [$value];
    }

    /**
     * Разбирает сырой JSON (плоские пары и/или объект с ключом {@see values}) в tuples.
     *
     * @return list<array{group: string|null, name: string, value: string}>
     */
    public static function flattenTuples(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $out = [];

        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }

            $name = trim((string) ($row['name'] ?? ''));
            $groupRaw = trim((string) ($row['group'] ?? ''));
            $group = $groupRaw !== '' ? $groupRaw : null;

            if ($name === '') {
                continue;
            }

            if (isset($row['values']) && is_array($row['values'])) {
                foreach ($row['values'] as $cell) {
                    if (is_array($cell)) {
                        $v = trim((string) ($cell['text'] ?? $cell['value'] ?? ''));
                    } else {
                        $v = trim((string) $cell);
                    }

                    if ($v === '') {
                        continue;
                    }

                    foreach (self::splitSlashJoinedList($v) as $piece) {
                        $out[] = [
                            'group' => $group,
                            'name' => $name,
                            'value' => $piece,
                        ];
                    }
                }

                continue;
            }

            $v = trim((string) ($row['value'] ?? ''));
            if ($v === '') {
                continue;
            }

            foreach (self::splitSlashJoinedList($v) as $piece) {
                $out[] = [
                    'group' => $group,
                    'name' => $name,
                    'value' => $piece,
                ];
            }
        }

        return $out;
    }

    /**
     * Ответ API: одна строка на пару (group, name) с массивом {@see values}.
     *
     * @return list<array{group: string|null, name: string, values: list<string>}>
     */
    public static function groupedForApi(mixed $raw): array
    {
        $flat = self::flattenTuples($raw);
        $merged = [];
        $orderKeys = [];

        foreach ($flat as $t) {
            $key = ($t['group'] ?? '') . "\0" . $t['name'];

            if (! isset($merged[$key])) {
                $merged[$key] = [
                    'group' => $t['group'],
                    'name' => $t['name'],
                    'values' => [],
                ];
                $orderKeys[] = $key;
            }

            $v = $t['value'];
            if (! in_array($v, $merged[$key]['values'], true)) {
                $merged[$key]['values'][] = $v;
            }
        }

        return array_map(static fn (string $k) => $merged[$k], $orderKeys);
    }

    /**
     * Данные для Filament: group, name, values[{ text }].
     *
     * @return list<array{group: string, name: string, values: list<array{text: string}>}>
     */
    public static function collapseForForm(mixed $raw): array
    {
        $grouped = self::groupedForApi($raw);

        return array_map(static function (array $row): array {
            $slots = [];

            foreach ($row['values'] as $v) {
                $slots[] = ['text' => $v];
            }

            return [
                'group' => $row['group'] ?? '',
                'name' => $row['name'],
                'values' => $slots,
            ];
        }, $grouped);
    }

    /**
     * Сохранение в БД из формы Repeater.
     *
     * @param  array<int, mixed>  $rows
     * @return list<array{group: string|null, name: string, value: string}>
     */
    public static function expandForStorage(array $rows): array
    {
        $out = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $name = trim((string) ($row['name'] ?? ''));
            $groupRaw = trim((string) ($row['group'] ?? ''));
            $group = $groupRaw !== '' ? $groupRaw : null;

            if ($name === '') {
                continue;
            }

            $vals = [];

            if (isset($row['values']) && is_array($row['values'])) {
                foreach ($row['values'] as $slot) {
                    if (! is_array($slot)) {
                        $v = trim((string) $slot);
                        if ($v !== '') {
                            $vals[] = $v;
                        }

                        continue;
                    }

                    $v = trim((string) ($slot['text'] ?? $slot['value'] ?? ''));

                    if ($v !== '') {
                        $vals[] = $v;
                    }
                }
            }

            if ($vals === [] && isset($row['value'])) {
                $v = trim((string) $row['value']);
                if ($v !== '') {
                    $vals[] = $v;
                }
            }

            foreach ($vals as $v) {
                foreach (self::splitSlashJoinedList($v) as $piece) {
                    $out[] = [
                        'group' => $group,
                        'name' => $name,
                        'value' => $piece,
                    ];
                }
            }
        }

        return $out;
    }
}
