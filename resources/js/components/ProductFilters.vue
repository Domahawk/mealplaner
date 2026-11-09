<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { ProductFilterKey, ProductFilters } from '../lib/products';

const props = defineProps<{
    modelValue: ProductFilters;
    errors: Record<string, string[]>;
    disabled: boolean;
}>();
const emit = defineEmits<{ 'update:modelValue': [filters: ProductFilters]; clear: [] }>();
const nutrients = [
    { key: 'calories', label: 'Calories', unit: 'kcal' },
    { key: 'protein', label: 'Protein', unit: 'g' },
    { key: 'carbohydrates', label: 'Carbohydrates', unit: 'g' },
    { key: 'fat', label: 'Fat', unit: 'g' },
] as const;
const hasNutrition = computed(() =>
    Object.entries(props.modelValue).some(([key, value]) => key !== 'search' && value !== ''),
);
const expanded = ref(hasNutrition.value);
watch(hasNutrition, (value) => {
    if (value) expanded.value = true;
});
function update(key: ProductFilterKey, event: Event): void {
    if (event.target instanceof HTMLInputElement)
        emit('update:modelValue', { ...props.modelValue, [key]: event.target.value });
}
function toggle(event: Event): void {
    if (event.target instanceof HTMLDetailsElement) expanded.value = event.target.open;
}
</script>

<template>
    <section class="product-filters" aria-label="Search and filter products">
        <label for="product-search">Search by name or brand</label>
        <input
            id="product-search"
            type="search"
            name="search"
            :value="modelValue.search"
            maxlength="255"
            :disabled="disabled"
            :aria-invalid="Boolean(errors.search)"
            aria-describedby="search-help search-error"
            @input="update('search', $event)"
        />
        <p id="search-help" class="field-help">Results update after you pause typing.</p>
        <p id="search-error" class="field-error">{{ errors.search?.[0] }}</p>
        <details :open="expanded" @toggle="toggle">
            <summary>Nutrition filters · per 100 g</summary>
            <p class="field-help">
                Set either or both bounds. Leave blank for no limit. Use up to 2 decimal places.
            </p>
            <div class="nutrition-filter-grid">
                <fieldset v-for="nutrient in nutrients" :key="nutrient.key" :disabled="disabled">
                    <legend>{{ nutrient.label }} ({{ nutrient.unit }} per 100 g)</legend>
                    <div v-for="bound in ['min', 'max'] as const" :key="bound" class="form-field">
                        <label :for="`filter-${nutrient.key}-${bound}`"
                            >{{ bound === 'min' ? 'Minimum' : 'Maximum' }}
                            {{ nutrient.label.toLowerCase() }} ({{ nutrient.unit }})</label
                        >
                        <input
                            :id="`filter-${nutrient.key}-${bound}`"
                            :name="`${nutrient.key}_${bound}`"
                            inputmode="decimal"
                            :value="modelValue[`${nutrient.key}_${bound}`]"
                            :aria-invalid="Boolean(errors[`${nutrient.key}_${bound}`])"
                            :aria-describedby="`filter-${nutrient.key}-${bound}-error`"
                            @input="update(`${nutrient.key}_${bound}`, $event)"
                        />
                        <p :id="`filter-${nutrient.key}-${bound}-error`" class="field-error">
                            {{ errors[`${nutrient.key}_${bound}`]?.[0] }}
                        </p>
                    </div>
                </fieldset>
            </div>
        </details>
        <button type="button" class="secondary-button" :disabled="disabled" @click="emit('clear')">
            Clear search and filters
        </button>
    </section>
</template>
