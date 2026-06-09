<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    @php
        $variants  = $getVariants();
        $statePath = $getStatePath();
    @endphp

    <div
        x-data="{ value: $wire.entangle('{{ $statePath }}') }"
        class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3"
    >
        @foreach ($variants as $key => $variant)
            <button
                type="button"
                @click="value = '{{ $key }}'"
                :class="value === '{{ $key }}'
                    ? 'ring-2 ring-primary-500 border-primary-500 bg-primary-50 dark:bg-primary-950/30'
                    : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'"
                class="relative flex flex-col overflow-hidden rounded-xl border-2 text-left transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
            >
                {{-- Превью --}}
                <div class="w-full bg-gray-100 dark:bg-gray-800 overflow-hidden">
                    @include('filament.forms.components.variant-previews.' . $variant['preview'])
                </div>

                {{-- Лейбл --}}
                <div class="flex items-start gap-2 px-3 py-2.5">
                    {{-- Радиокружок --}}
                    <span
                        :class="value === '{{ $key }}'
                            ? 'border-primary-500 bg-primary-500'
                            : 'border-gray-300 dark:border-gray-600'"
                        class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full border-2 transition-colors"
                    >
                        <span
                            x-show="value === '{{ $key }}'"
                            class="h-1.5 w-1.5 rounded-full bg-white"
                        ></span>
                    </span>

                    <div>
                        <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ $variant['label'] }}</p>
                        @if (!empty($variant['description']))
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $variant['description'] }}</p>
                        @endif
                    </div>
                </div>
            </button>
        @endforeach
    </div>
</x-dynamic-component>
