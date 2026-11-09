<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import type { Product } from '../lib/products';

const props = defineProps<{ product: Product; busy: boolean; error: string }>();
const emit = defineEmits<{ confirm: []; cancel: [] }>();
const dialog = ref<HTMLDialogElement>();
const cancelButton = ref<HTMLButtonElement>();
const previousFocus = document.activeElement;
onMounted(() => {
    dialog.value?.showModal();
    cancelButton.value?.focus();
});
onBeforeUnmount(() => {
    dialog.value?.close();
    if (previousFocus instanceof HTMLElement && previousFocus.isConnected) previousFocus.focus();
});
</script>

<template>
    <dialog
        ref="dialog"
        class="product-dialog"
        aria-labelledby="delete-title"
        aria-describedby="delete-description"
        :aria-busy="busy"
        @cancel.prevent="!props.busy && emit('cancel')"
    >
        <h2 id="delete-title">Delete product?</h2>
        <p id="delete-description">Delete “{{ product.name }}”? This cannot be undone.</p>
        <p v-if="error" role="alert" class="form-error">{{ error }}</p>
        <p v-if="busy" role="status">Deleting product… Please wait.</p>
        <div class="product-actions">
            <button
                ref="cancelButton"
                class="secondary-button"
                type="button"
                :disabled="busy"
                @click="emit('cancel')"
            >
                Keep product
            </button>
            <button class="primary-button" type="button" :disabled="busy" @click="emit('confirm')">
                {{ busy ? 'Deleting…' : 'Delete product' }}
            </button>
        </div>
    </dialog>
</template>
