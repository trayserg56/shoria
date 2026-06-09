<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    @php
        $tree = $getTree();
        $statePath = $getStatePath();
        $fieldId = $getId();
    @endphp

    <div
        x-data="{
            state: $wire.entangle('{{ $statePath }}'),

            toggle(id) {
                const strId = String(id);
                const idx = this.state.indexOf(strId);
                if (idx === -1) {
                    this.state.push(strId);
                } else {
                    this.state.splice(idx, 1);
                }
            },

            isChecked(id) {
                return this.state.includes(String(id));
            },

            toggleGroup(parentId, childIds) {
                const allChecked = childIds.every(id => this.state.includes(String(id)));
                if (allChecked) {
                    // снимаем родителя и всех детей
                    [parentId, ...childIds].forEach(id => {
                        const idx = this.state.indexOf(String(id));
                        if (idx !== -1) this.state.splice(idx, 1);
                    });
                } else {
                    // ставим галочку родителю и всем детям
                    [parentId, ...childIds].forEach(id => {
                        if (!this.state.includes(String(id))) this.state.push(String(id));
                    });
                }
            },

            groupState(parentId, childIds) {
                const checkedCount = childIds.filter(id => this.state.includes(String(id))).length;
                if (checkedCount === 0 && !this.state.includes(String(parentId))) return 'none';
                if (checkedCount === childIds.length && this.state.includes(String(parentId))) return 'all';
                return 'partial';
            }
        }"
        class="space-y-1"
    >
        @foreach ($tree as $parent)
            @php $childIds = array_column($parent['children'], 'id'); @endphp

            @if (count($parent['children']) > 0)
                {{-- Родительская категория с детьми --}}
                <div x-data="{ open: false }" class="rounded-lg border border-gray-200 dark:border-gray-700">
                    <div class="flex items-center gap-2 px-3 py-2">
                        {{-- Чекбокс родителя --}}
                        <input
                            type="checkbox"
                            id="{{ $fieldId }}_{{ $parent['id'] }}"
                            :checked="groupState({{ $parent['id'] }}, {{ json_encode($childIds) }}) === 'all'"
                            :indeterminate="groupState({{ $parent['id'] }}, {{ json_encode($childIds) }}) === 'partial'"
                            @change="toggleGroup({{ $parent['id'] }}, {{ json_encode($childIds) }})"
                            class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                        >
                        <label
                            for="{{ $fieldId }}_{{ $parent['id'] }}"
                            class="flex-1 cursor-pointer select-none text-sm font-medium text-gray-800 dark:text-gray-200"
                        >
                            {{ $parent['name'] }}
                        </label>
                        {{-- Счётчик + стрелка --}}
                        <span class="text-xs text-gray-400" x-text="
                            groupState({{ $parent['id'] }}, {{ json_encode($childIds) }}) === 'all'
                                ? 'все ({{ count($childIds) }})'
                                : (groupState({{ $parent['id'] }}, {{ json_encode($childIds) }}) === 'none'
                                    ? 'нет ({{ count($childIds) }})'
                                    : [{{ implode(',', $childIds) }}].filter(id => state.includes(String(id))).length + '/{{ count($childIds) }}')
                        "></span>
                        <button type="button" @click="open = !open" class="ml-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                            <svg class="h-4 w-4 transition-transform duration-150" :class="open ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                            </svg>
                        </button>
                    </div>

                    {{-- Подкатегории --}}
                    <div x-show="open" x-collapse class="border-t border-gray-100 dark:border-gray-700">
                        <div class="grid grid-cols-2 gap-x-4 gap-y-1 px-4 py-2 sm:grid-cols-3">
                            @foreach ($parent['children'] as $child)
                                <label class="flex cursor-pointer items-center gap-2 rounded px-1 py-1 hover:bg-gray-50 dark:hover:bg-gray-800">
                                    <input
                                        type="checkbox"
                                        id="{{ $fieldId }}_{{ $child['id'] }}"
                                        :checked="isChecked({{ $child['id'] }})"
                                        @change="toggle({{ $child['id'] }})"
                                        class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                    >
                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ $child['name'] }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

            @else
                {{-- Одиночная категория без детей --}}
                <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">
                    <input
                        type="checkbox"
                        id="{{ $fieldId }}_{{ $parent['id'] }}"
                        :checked="isChecked({{ $parent['id'] }})"
                        @change="toggle({{ $parent['id'] }})"
                        class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                    >
                    <span class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ $parent['name'] }}</span>
                </label>
            @endif
        @endforeach
    </div>
</x-dynamic-component>
