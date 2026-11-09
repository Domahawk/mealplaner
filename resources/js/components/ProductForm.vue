<script setup lang="ts">
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue';
import type { Product, ProductInput } from '../lib/products';

const props = defineProps<{
    product: Product | null;
    busy: boolean;
    errors: Record<string, string[]>;
    message: string;
}>();
const emit = defineEmits<{ save: [input: ProductInput]; cancel: [] }>();
const nutrition = [
    { key: 'calories_per_100g', label: 'Calories (kcal)', max: 999999.99 },
    { key: 'protein_per_100g', label: 'Protein (g)', max: 100 },
    { key: 'carbohydrates_per_100g', label: 'Carbohydrates (g)', max: 100 },
    { key: 'fat_per_100g', label: 'Fat (g)', max: 100 },
] as const;
const fields = reactive({
    name: props.product?.name ?? '',
    brand: props.product?.brand ?? '',
    package_weight_g: props.product?.package_weight_g ?? '',
    calories_per_100g: props.product?.calories_per_100g ?? '',
    protein_per_100g: props.product?.protein_per_100g ?? '',
    carbohydrates_per_100g: props.product?.carbohydrates_per_100g ?? '',
    fat_per_100g: props.product?.fat_per_100g ?? '',
});
const localErrors = ref<Record<string, string[]>>({});
const errors = computed(() => ({ ...props.errors, ...localErrors.value }));
const summary = ref<HTMLElement>();
const nameInput = ref<HTMLInputElement>();
onMounted(() => nameInput.value?.focus());
watch(
    () => props.message,
    async (value) => {
        if (value) {
            await nextTick();
            summary.value?.focus();
        }
    },
);

function decimal(value: string, scale: number, max: number, positive = false): boolean {
    return (
        new RegExp(`^\\d+(?:\\.\\d{1,${scale}})?$`).test(value) &&
        Number.isFinite(Number(value)) &&
        Number(value) <= max &&
        (positive ? Number(value) > 0 : Number(value) >= 0)
    );
}

async function submit(): Promise<void> {
    if (props.busy) return;
    localErrors.value = {};
    if (!fields.name.trim() || fields.name.trim().length > 255 || fields.name.includes('\0'))
        localErrors.value.name = ['Enter a product name of up to 255 characters.'];
    if (fields.brand.trim().length > 255 || fields.brand.includes('\0'))
        localErrors.value.brand = ['Use a brand of up to 255 characters.'];
    if (
        fields.package_weight_g.trim() &&
        !decimal(fields.package_weight_g.trim(), 3, 9999999.999, true)
    )
        localErrors.value.package_weight_g = [
            'Enter a positive weight with up to 3 decimal places.',
        ];
    for (const field of nutrition) {
        if (!decimal(fields[field.key].trim(), 2, field.max))
            localErrors.value[field.key] = [
                `Enter a number from 0 to ${field.max} with up to 2 decimal places.`,
            ];
    }
    if (Object.keys(localErrors.value).length) {
        await nextTick();
        summary.value?.focus();
        return;
    }
    emit('save', {
        name: fields.name.trim(),
        brand: fields.brand.trim() || null,
        package_weight_g: fields.package_weight_g.trim() || null,
        calories_per_100g: fields.calories_per_100g.trim(),
        protein_per_100g: fields.protein_per_100g.trim(),
        carbohydrates_per_100g: fields.carbohydrates_per_100g.trim(),
        fat_per_100g: fields.fat_per_100g.trim(),
    });
}
</script>

<template>
    <section class="product-editor" aria-labelledby="product-form-title">
        <h2 id="product-form-title">{{ product ? 'Edit product' : 'Add product' }}</h2>
        <p id="nutrition-help" class="field-help">
            Enter nutrition per 100 g. Calories are in kcal; macronutrients are in grams.
        </p>
        <form class="product-form" novalidate :aria-busy="busy" @submit.prevent="submit">
            <div ref="summary" tabindex="-1" role="alert" class="form-error">
                <p v-if="message || Object.keys(localErrors).length">
                    {{ message || 'Please check the highlighted fields.' }}
                </p>
            </div>
            <fieldset :disabled="busy">
                <legend class="visually-hidden">Product details and nutrition per 100 g</legend>
                <div class="form-field">
                    <label for="product-name">Name</label>
                    <input
                        id="product-name"
                        ref="nameInput"
                        v-model="fields.name"
                        name="name"
                        required
                        maxlength="255"
                        :aria-invalid="Boolean(errors.name)"
                        aria-describedby="product-name-error"
                    />
                    <p id="product-name-error" class="field-error">{{ errors.name?.[0] }}</p>
                </div>
                <div class="form-field">
                    <label for="product-brand">Brand (optional)</label>
                    <input
                        id="product-brand"
                        v-model="fields.brand"
                        name="brand"
                        maxlength="255"
                        :aria-invalid="Boolean(errors.brand)"
                        aria-describedby="product-brand-error"
                    />
                    <p id="product-brand-error" class="field-error">{{ errors.brand?.[0] }}</p>
                </div>
                <div class="form-field">
                    <label for="product-weight">Package weight (g, optional)</label>
                    <input
                        id="product-weight"
                        v-model="fields.package_weight_g"
                        name="package_weight_g"
                        inputmode="decimal"
                        :aria-invalid="Boolean(errors.package_weight_g)"
                        aria-describedby="weight-help product-weight-error"
                    />
                    <p id="weight-help" class="field-help">
                        Leave blank if unknown. Up to 3 decimal places.
                    </p>
                    <p id="product-weight-error" class="field-error">
                        {{ errors.package_weight_g?.[0] }}
                    </p>
                </div>
                <div class="nutrition-fields">
                    <div v-for="field in nutrition" :key="field.key" class="form-field">
                        <label :for="`product-${field.key}`">{{ field.label }} per 100 g</label>
                        <input
                            :id="`product-${field.key}`"
                            v-model="fields[field.key]"
                            :name="field.key"
                            inputmode="decimal"
                            required
                            :aria-invalid="Boolean(errors[field.key])"
                            :aria-describedby="`nutrition-help product-${field.key}-error`"
                        />
                        <p :id="`product-${field.key}-error`" class="field-error">
                            {{ errors[field.key]?.[0] }}
                        </p>
                    </div>
                </div>
            </fieldset>
            <div class="product-actions">
                <button class="primary-button" type="submit" :disabled="busy">
                    {{ busy ? 'Saving…' : product ? 'Save changes' : 'Create product' }}
                </button>
                <button
                    class="secondary-button"
                    type="button"
                    :disabled="busy"
                    @click="emit('cancel')"
                >
                    Cancel
                </button>
            </div>
        </form>
    </section>
</template>
