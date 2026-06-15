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
                    ? 'border-primary-500 shadow-md shadow-primary-500/20 dark:shadow-primary-500/10'
                    : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600 hover:shadow-sm'"
                class="relative flex flex-col overflow-hidden rounded-xl border-2 text-left transition-all duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
            >
                {{-- Цветная полоска сверху при выборе --}}
                <span
                    x-show="value === '{{ $key }}'"
                    style="position:absolute;inset-inline:0;top:0;height:4px;background:rgb(var(--primary-500));z-index:10;display:block"
                ></span>

                {{-- Превью --}}
                <div
                    :class="value === '{{ $key }}' ? 'opacity-100' : 'opacity-80 group-hover:opacity-100'"
                    class="w-full bg-gray-100 dark:bg-gray-800 overflow-hidden transition-opacity"
                >
                    @include('filament.forms.components.variant-previews.' . $variant['preview'])
                </div>

                {{-- Лейбл --}}
                <div
                    :class="value === '{{ $key }}'
                        ? 'bg-primary-50 dark:bg-primary-950/40'
                        : 'bg-white dark:bg-gray-900'"
                    class="flex items-center justify-between gap-2 px-3 py-2.5 transition-colors"
                >
                    <div>
                        <p
                            :class="value === '{{ $key }}'
                                ? 'text-primary-700 dark:text-primary-300'
                                : 'text-gray-800 dark:text-gray-100'"
                            class="text-sm font-semibold transition-colors"
                        >{{ $variant['label'] }}</p>
                        @if (!empty($variant['description']))
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $variant['description'] }}</p>
                        @endif
                    </div>

                    {{-- Галочка при выборе / кружок при не выборе --}}
                    <span style="flex-shrink:0;width:20px;height:20px;display:block">
                        <span x-show="value === '{{ $key }}'">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor" style="color:rgb(var(--primary-500))">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/>
                            </svg>
                        </span>
                        <span x-show="value !== '{{ $key }}'">
                            <span style="display:block;width:20px;height:20px;border-radius:50%;border:2px solid #9ca3af"></span>
                        </span>
                    </span>
                </div>
            </button>
        @endforeach
    </div>
</x-dynamic-component>
