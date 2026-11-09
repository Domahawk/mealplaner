import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter, type LocationQuery, type LocationQueryRaw } from 'vue-router';
import { emptyProductFilters, productFilterKeys, type ProductFilters } from '../lib/products';

function readFilters(query: LocationQuery): ProductFilters {
    const filters = emptyProductFilters();
    for (const key of productFilterKeys) {
        const value = query[key];
        filters[key] =
            typeof value === 'string' ? value : Array.isArray(value) ? (value.at(-1) ?? '') : '';
    }
    return filters;
}

function signature(filters: ProductFilters, trim = true): string {
    return JSON.stringify(
        productFilterKeys.map((key) => (trim ? filters[key].trim() : filters[key])),
    );
}

export function useProductSearch(load: (page: number) => Promise<boolean>, invalidate: () => void) {
    const route = useRoute();
    const router = useRouter();
    const applied = computed(() => readFilters(route.query));
    const draft = ref<ProductFilters>(readFilters(route.query));
    const active = computed(() =>
        productFilterKeys.some((key) => applied.value[key].trim() !== ''),
    );
    let timer: ReturnType<typeof setTimeout> | undefined;
    let waiting = false;
    let task: Promise<boolean> = Promise.resolve(false);
    const pendingWrites = new Set<{ path: string }>();
    const currentPage = () => Number(route.query.page ?? 1);

    function cancelDebounce(): void {
        clearTimeout(timer);
        timer = undefined;
    }
    function query(filters: ProductFilters, page: number): LocationQueryRaw {
        const result: LocationQueryRaw = { ...route.query };
        for (const key of productFilterKeys) {
            delete result[key];
            if (filters[key].trim()) result[key] = filters[key];
        }
        delete result.page;
        if (page !== 1) result.page = String(page);
        return result;
    }

    onMounted(() => {
        task = load(currentPage());
    });
    onBeforeUnmount(cancelDebounce);
    watch(
        () => route.query,
        () => {
            if (route.name !== 'products') return;
            // A delayed session guard must not restore older input over newer typing.
            if (
                [...pendingWrites].some((write) => write.path === route.fullPath) &&
                signature(applied.value, false) !== signature(draft.value, false)
            ) {
                if (timer === undefined) void commit();
                return;
            }
            cancelDebounce();
            waiting = false;
            draft.value = readFilters(route.query);
            task = load(currentPage());
        },
    );
    watch(
        draft,
        () => {
            cancelDebounce();
            if (signature(draft.value) === signature(applied.value)) {
                if (waiting) {
                    waiting = false;
                    task = load(currentPage());
                }
                return;
            }
            waiting = true;
            invalidate();
            timer = setTimeout(() => {
                void commit();
            }, 300);
        },
        { deep: true },
    );

    async function commit(): Promise<void> {
        cancelDebounce();
        const target = { query: query(draft.value, 1) };
        const write = { path: router.resolve(target).fullPath };
        pendingWrites.add(write);
        try {
            await router.replace(target);
        } finally {
            await nextTick();
            pendingWrites.delete(write);
        }
    }

    async function setPage(page: number, replace = false): Promise<boolean> {
        cancelDebounce();
        const before = route.fullPath;
        await router[replace ? 'replace' : 'push']({ query: query(applied.value, page) });
        if (route.fullPath === before) task = load(currentPage());
        return task;
    }

    function clear(): void {
        draft.value = emptyProductFilters();
        void commit();
    }

    return { applied, draft, active, setPage, clear };
}
