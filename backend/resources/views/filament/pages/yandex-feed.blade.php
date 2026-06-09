<x-filament-panels::page>
    @php
        $appUrl = rtrim(config('app.url', ''), '/');
        $feedUrl = $appUrl . '/feed/yandex.xml';
        $cacheExists = \Illuminate\Support\Facades\Cache::has('shoria:feed:yandex:yml');
    @endphp

    <div class="space-y-6">
        {{-- Статус --}}
        <x-filament::section>
            <x-slot name="heading">Статус фида</x-slot>

            <div class="flex items-center gap-3">
                @if ($cacheExists)
                    <x-filament::badge color="success">Кэш активен</x-filament::badge>
                    <span class="text-sm text-gray-500">Фид закэширован и готов к отдаче</span>
                @else
                    <x-filament::badge color="warning">Кэш пуст</x-filament::badge>
                    <span class="text-sm text-gray-500">Фид будет построен при первом запросе (это займёт несколько секунд)</span>
                @endif
            </div>
        </x-filament::section>

        {{-- Как подключить --}}
        <x-filament::section>
            <x-slot name="heading">Подключение в Яндекс.Маркет</x-slot>

            <div class="space-y-4 text-sm text-gray-700 dark:text-gray-300">
                <p>Скопируйте ссылку на фид и вставьте в кабинете Яндекс.Маркет при добавлении магазина.</p>

                <div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-900">
                    <code class="flex-1 break-all font-mono text-xs text-gray-800 dark:text-gray-200">{{ $feedUrl }}</code>
                    <a href="{{ $feedUrl }}" target="_blank"
                       class="shrink-0 text-primary-600 hover:text-primary-500 dark:text-primary-400">
                        Открыть ↗
                    </a>
                </div>

                <ol class="list-inside list-decimal space-y-1 text-gray-600 dark:text-gray-400">
                    <li>Зайдите в <a href="https://partner.market.yandex.ru" target="_blank" class="underline">partner.market.yandex.ru</a></li>
                    <li>Добавьте магазин → выберите «Прайс-лист (YML/CSV)»</li>
                    <li>Вставьте ссылку выше и нажмите «Проверить»</li>
                    <li>Яндекс будет обновлять фид автоматически раз в несколько часов</li>
                </ol>

                <p class="text-xs text-gray-400">
                    Фид кэшируется на 4 часа. При изменении товаров кэш сбрасывается автоматически.
                    Для принудительного обновления используйте кнопку «Обновить фид» вверху страницы.
                </p>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
