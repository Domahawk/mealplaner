<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import ProductForm from '../components/ProductForm.vue';
import DeleteProductDialog from '../components/DeleteProductDialog.vue';
import { ApiError } from '../lib/api';
import {
    deleteProduct,
    listProducts,
    saveProduct,
    type Product,
    type ProductInput,
} from '../lib/products';
import { useAuthStore } from '../stores/auth';

const router = useRouter();
const auth = useAuthStore();
const products = ref<Product[]>([]);
const page = ref(1);
const lastPage = ref(1);
const total = ref(0);
const loading = ref(false);
const pending = ref(false);
const loadError = ref('');
const success = ref('');
const formOpen = ref(false);
const editing = ref<Product | null>(null);
const formErrors = ref<Record<string, string[]>>({});
const formMessage = ref('');
const deleting = ref<Product | null>(null);
const deleteError = ref('');
const heading = ref<HTMLElement>();
let active = true;
let requestNumber = 0;

onMounted(() => {
    void loadPage();
});
onBeforeUnmount(() => {
    active = false;
    requestNumber++;
});
watch(
    () => auth.user?.id,
    (id, previous) => {
        if (id === previous) return;
        requestNumber++;
        products.value = [];
        total.value = 0;
        formOpen.value = false;
        deleting.value = null;
        void router.replace({ name: id ? 'account' : 'login' });
    },
);

async function errorMessage(error: unknown): Promise<string> {
    if (error instanceof ApiError) {
        if (error.status === 401) {
            products.value = [];
            formOpen.value = false;
            deleting.value = null;
            auth.user = null;
            auth.initialized = false;
            await router.replace({ name: 'login' });
        } else if (error.status === 419) {
            // Refresh credentials without automatically replaying a mutation.
            await auth.loadSession(true).catch(() => undefined);
        }
        return error.message;
    }
    return 'Unable to reach the server or read its response. Refresh products before retrying a change.';
}

async function loadPage(target = page.value): Promise<boolean> {
    const sequence = ++requestNumber;
    page.value = target;
    loading.value = true;
    loadError.value = '';
    products.value = [];
    try {
        const result = await listProducts(target);
        if (!active || sequence !== requestNumber) return false;
        if (target > result.lastPage) return await loadPage(result.lastPage);
        products.value = result.products;
        page.value = result.page;
        lastPage.value = result.lastPage;
        total.value = result.total;
        return true;
    } catch (error: unknown) {
        if (active && sequence === requestNumber) loadError.value = await errorMessage(error);
        return false;
    } finally {
        if (sequence === requestNumber) loading.value = false;
    }
}

function openForm(product: Product | null = null): void {
    editing.value = product;
    formErrors.value = {};
    formMessage.value = '';
    success.value = '';
    formOpen.value = true;
}

async function closeForm(): Promise<void> {
    formOpen.value = false;
    editing.value = null;
    await nextTick();
    heading.value?.focus();
}

async function submit(input: ProductInput): Promise<void> {
    if (pending.value) return;
    pending.value = true;
    formErrors.value = {};
    formMessage.value = '';
    const creating = editing.value === null;
    try {
        await saveProduct(input, editing.value?.id);
        if (!active) return;
        await closeForm();
        success.value = creating ? 'Product created.' : 'Product updated.';
        const refreshed = await loadPage(creating ? 1 : page.value);
        // New products sort last; open their page after getting current metadata.
        if (creating && refreshed && lastPage.value > 1) await loadPage(lastPage.value);
    } catch (error: unknown) {
        if (!active) return;
        formErrors.value = error instanceof ApiError ? error.errors : {};
        formMessage.value = await errorMessage(error);
    } finally {
        pending.value = false;
    }
}

async function confirmDelete(): Promise<void> {
    if (pending.value || !deleting.value) return;
    pending.value = true;
    deleteError.value = '';
    try {
        await deleteProduct(deleting.value.id);
        if (!active) return;
        deleting.value = null;
        success.value = 'Product deleted.';
        await loadPage();
        await nextTick();
        heading.value?.focus();
    } catch (error: unknown) {
        if (active) deleteError.value = await errorMessage(error);
    } finally {
        pending.value = false;
    }
}

function openDelete(product: Product): void {
    success.value = '';
    deleteError.value = '';
    deleting.value = product;
}
</script>

<template>
    <section class="products-view" aria-labelledby="products-heading">
        <div class="products-header">
            <div>
                <h1 id="products-heading" ref="heading" tabindex="-1">Products</h1>
                <p>Keep your food and nutrition in one place. All nutrition is per 100 g.</p>
            </div>
            <button
                class="primary-button"
                type="button"
                :disabled="loading || pending || formOpen"
                @click="openForm()"
            >
                Add product
            </button>
        </div>
        <p v-if="success" class="notice" role="status">{{ success }}</p>
        <ProductForm
            v-if="formOpen"
            :key="editing?.id ?? 'new'"
            :product="editing"
            :busy="pending"
            :errors="formErrors"
            :message="formMessage"
            @save="submit"
            @cancel="closeForm"
        />
        <div :aria-busy="loading">
            <p v-if="loading" class="notice" role="status">Loading products…</p>
            <div v-else-if="loadError" class="notice" role="alert">
                <p>{{ loadError }}</p>
                <button
                    type="button"
                    class="secondary-button"
                    :disabled="pending"
                    @click="loadPage()"
                >
                    Retry loading products
                </button>
            </div>
            <p v-else-if="!products.length" class="empty-products">
                No products yet. Add your first food product to get started.
            </p>
            <ul v-else class="product-list" aria-label="Your products">
                <li v-for="product in products" :key="product.id" class="product-card">
                    <h2>{{ product.name }}</h2>
                    <p class="product-brand">{{ product.brand ?? 'No brand specified' }}</p>
                    <p v-if="product.package_weight_g" class="field-help">
                        Package: {{ product.package_weight_g }} g
                    </p>
                    <p class="nutrition-label">Nutrition per 100 g</p>
                    <dl class="product-nutrition">
                        <div>
                            <dt>Calories</dt>
                            <dd>{{ product.calories_per_100g }} kcal</dd>
                        </div>
                        <div>
                            <dt>Protein</dt>
                            <dd>{{ product.protein_per_100g }} g</dd>
                        </div>
                        <div>
                            <dt>Carbohydrates</dt>
                            <dd>{{ product.carbohydrates_per_100g }} g</dd>
                        </div>
                        <div>
                            <dt>Fat</dt>
                            <dd>{{ product.fat_per_100g }} g</dd>
                        </div>
                    </dl>
                    <div class="product-actions">
                        <button
                            class="secondary-button"
                            type="button"
                            :aria-label="`Edit ${product.name}`"
                            :disabled="pending || formOpen"
                            @click="openForm(product)"
                        >
                            Edit
                        </button>
                        <button
                            class="secondary-button"
                            type="button"
                            :aria-label="`Delete ${product.name}`"
                            :disabled="pending || formOpen"
                            @click="openDelete(product)"
                        >
                            Delete
                        </button>
                    </div>
                </li>
            </ul>
        </div>
        <nav
            v-if="!loadError && total > 0"
            class="product-pagination"
            aria-label="Product pagination"
        >
            <button
                type="button"
                class="secondary-button"
                :disabled="page <= 1 || loading || pending || formOpen"
                @click="loadPage(page - 1)"
            >
                Previous
            </button>
            <p role="status">Page {{ page }} of {{ lastPage }} · {{ total }} products</p>
            <button
                type="button"
                class="secondary-button"
                :disabled="page >= lastPage || loading || pending || formOpen"
                @click="loadPage(page + 1)"
            >
                Next
            </button>
        </nav>
        <DeleteProductDialog
            v-if="deleting"
            :product="deleting"
            :busy="pending"
            :error="deleteError"
            @confirm="confirmDelete"
            @cancel="deleting = null"
        />
    </section>
</template>
