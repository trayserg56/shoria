<x-filament-panels::page>
    @php
        $appUrl = rtrim(config('app.url', ''), '/');
        $feedUrl = $appUrl . '/feed/yandex.xml';
        $cacheExists = \Illuminate\Support\Facades\Cache::has('shoria:feed:yandex:yml');
    @endphp

    <div class="space-y-6">
        {{-- Статус и ссылка --}}
        <x-filament::section>
            <x-slot name="heading">Статус фида</x-slot>

            <div class="flex flex-wrap items-center gap-4">
                @if ($cacheExists)
                    <x-filament::badge color="success">Кэш активен</x-filament::badge>
                @else
                    <x-filament::badge color="warning">Кэш пуст</x-filament::badge>
                @endif

                <code class="rounded bg-gray-100 px-3 py-1 text-xs text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                    {{ $feedUrl }}
                </code>
            </div>

            <p class="mt-2 text-xs text-gray-400">
                Фид кэшируется на 4 часа. При изменении любого товара кэш сбрасывается автоматически.
                Для принудительного сброса используйте кнопку «Сбросить кэш» вверху.
            </p>
        </x-filament::section>

        {{-- Форма настроек --}}
        <x-filament-panels::form wire:submit="save">
            {{ $this->form }}

            <div class="flex justify-end">
                <x-filament::button type="submit" size="lg">
                    Сохранить настройки
                </x-filament::button>
            </div>
        </x-filament-panels::form>

        {{-- Инструкция --}}
        <x-filament::section>
            <x-slot name="heading">Как подключить в Яндекс.Маркет</x-slot>

            <ol class="list-inside list-decimal space-y-1 text-sm text-gray-600 dark:text-gray-400">
                <li>Зайдите в <a href="https://partner.market.yandex.ru" target="_blank" class="underline">partner.market.yandex.ru</a></li>
                <li>Добавьте магазин → выберите «Прайс-лист (YML/CSV)»</li>
                <li>Вставьте URL фида: <code class="rounded bg-gray-100 px-1 text-xs dark:bg-gray-800">{{ $feedUrl }}</code></li>
                <li>Яндекс будет обновлять фид автоматически раз в несколько часов</li>
            </ol>
        </x-filament::section>
    </div>
</x-filament-panels::page>
