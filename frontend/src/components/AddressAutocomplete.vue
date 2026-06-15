<script setup lang="ts">
import { ref } from 'vue'
import { useAddressSuggest, type AddressSuggestion } from '@/composables/useAddressSuggest'

const props = withDefaults(defineProps<{
  modelValue: string
  placeholder?: string
  inputClass?: string
  id?: string
  name?: string
  autocomplete?: string
}>(), {
  placeholder: '',
  inputClass: '',
  id: undefined,
  name: undefined,
  autocomplete: 'off',
})

const emit = defineEmits<{
  'update:modelValue': [value: string]
  select: [suggestion: AddressSuggestion]
}>()

const { suggestions, search, clear } = useAddressSuggest()

const isOpen = ref(false)

function onInput(event: Event): void {
  const value = (event.target as HTMLInputElement).value
  emit('update:modelValue', value)
  search(value)
  isOpen.value = true
}

function onFocus(): void {
  if (suggestions.value.length) {
    isOpen.value = true
  }
}

function onBlur(): void {
  isOpen.value = false
}

function choose(suggestion: AddressSuggestion): void {
  emit('update:modelValue', suggestion.value)
  emit('select', suggestion)
  clear()
  isOpen.value = false
}
</script>

<template>
  <div class="address-autocomplete">
    <input
      :id="props.id"
      :name="props.name"
      :class="['address-autocomplete__input', props.inputClass]"
      type="text"
      :value="props.modelValue"
      :placeholder="props.placeholder"
      :autocomplete="props.autocomplete"
      @input="onInput"
      @focus="onFocus"
      @blur="onBlur"
    />
    <div v-if="isOpen && suggestions.length" class="address-autocomplete__suggestions">
      <button
        v-for="(suggestion, index) in suggestions"
        :key="index"
        type="button"
        class="address-autocomplete__suggestion"
        @mousedown.prevent="choose(suggestion)"
      >
        {{ suggestion.value }}
      </button>
    </div>
  </div>
</template>

<style scoped>
.address-autocomplete {
  position: relative;
  width: 100%;
}

.address-autocomplete__input {
  width: 100%;
  box-sizing: border-box;
}

.address-autocomplete__suggestions {
  position: absolute;
  z-index: 48;
  top: calc(100% + 6px);
  left: 0;
  right: 0;
  max-height: 260px;
  overflow-y: auto;
  padding: 6px;
  border-radius: 12px;
  border: 1px solid var(--border);
  background: var(--popover);
  box-shadow: 0 16px 40px rgb(15 23 42 / 12%);
}

.address-autocomplete__suggestion {
  display: block;
  width: 100%;
  padding: 8px 10px;
  border: none;
  border-radius: 8px;
  background: transparent;
  color: var(--foreground);
  font-size: 14px;
  text-align: left;
  cursor: pointer;
}

.address-autocomplete__suggestion:hover {
  background: var(--accent);
}
</style>
