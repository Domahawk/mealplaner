import { ApiError, isRecord, jsonRequest, validationErrors } from './api';

export interface ProductInput {
    name: string;
    brand: string | null;
    package_weight_g: string | null;
    calories_per_100g: string;
    protein_per_100g: string;
    carbohydrates_per_100g: string;
    fat_per_100g: string;
}

export interface Product extends ProductInput {
    id: number;
    created_at: string | null;
    updated_at: string | null;
}

export interface ProductPage {
    products: Product[];
    page: number;
    lastPage: number;
    total: number;
}

export const productFilterKeys = [
    'search',
    'calories_min',
    'calories_max',
    'protein_min',
    'protein_max',
    'carbohydrates_min',
    'carbohydrates_max',
    'fat_min',
    'fat_max',
] as const;
export type ProductFilterKey = (typeof productFilterKeys)[number];
export type ProductFilters = Record<ProductFilterKey, string>;

export function emptyProductFilters(): ProductFilters {
    return {
        search: '',
        calories_min: '',
        calories_max: '',
        protein_min: '',
        protein_max: '',
        carbohydrates_min: '',
        carbohydrates_max: '',
        fat_min: '',
        fat_max: '',
    };
}

function isProduct(value: unknown): value is Product {
    return (
        isRecord(value) &&
        typeof value.id === 'number' &&
        Number.isSafeInteger(value.id) &&
        value.id > 0 &&
        typeof value.name === 'string' &&
        (value.brand === null || typeof value.brand === 'string') &&
        (value.package_weight_g === null ||
            (typeof value.package_weight_g === 'string' &&
                /^\d+\.\d{3}$/.test(value.package_weight_g))) &&
        ['calories_per_100g', 'protein_per_100g', 'carbohydrates_per_100g', 'fat_per_100g'].every(
            (key) => typeof value[key] === 'string' && /^\d+\.\d{2}$/.test(value[key]),
        ) &&
        ['created_at', 'updated_at'].every(
            (key) => value[key] === null || typeof value[key] === 'string',
        )
    );
}

async function request(
    path: string,
    method: 'GET' | 'POST' | 'PATCH' | 'DELETE',
    body?: ProductInput,
): Promise<unknown> {
    const response = await jsonRequest(path, method, body);
    const data: unknown = response.status === 204 ? null : await response.json().catch(() => null);
    if (!response.ok) {
        const message =
            response.status === 401
                ? 'Please log in to manage your products.'
                : response.status === 419
                  ? 'Your session changed. Review your changes and try again.'
                  : response.status === 422
                    ? 'Please check the highlighted fields.'
                    : response.status === 403
                      ? 'You do not have permission to change this product.'
                      : response.status === 404
                        ? 'This product is no longer available. Refresh your products.'
                        : response.status === 423
                          ? 'Another request is still running. Please try again shortly.'
                          : 'Unable to complete the request. Please try again.';
        throw new ApiError(
            message,
            response.status,
            response.status === 422 ? validationErrors(data) : {},
        );
    }
    return data;
}

export async function listProducts(
    page = 1,
    filters = emptyProductFilters(),
): Promise<ProductPage> {
    const query = new URLSearchParams({ page: String(page) });
    for (const key of productFilterKeys) {
        if (filters[key].trim()) query.set(key, filters[key].trim());
    }
    const body = await request(`/api/products?${query}`, 'GET');
    if (
        isRecord(body) &&
        Array.isArray(body.data) &&
        body.data.every(isProduct) &&
        isRecord(body.meta)
    ) {
        const { current_page: currentPage, last_page: lastPage, total } = body.meta;
        if (
            typeof currentPage === 'number' &&
            Number.isSafeInteger(currentPage) &&
            currentPage > 0 &&
            typeof lastPage === 'number' &&
            Number.isSafeInteger(lastPage) &&
            lastPage > 0 &&
            typeof total === 'number' &&
            Number.isSafeInteger(total) &&
            total >= 0
        ) {
            return { products: body.data, page: currentPage, lastPage, total };
        }
    }
    throw new Error('Unexpected product list response.');
}

export async function saveProduct(input: ProductInput, id?: number): Promise<Product> {
    const body = await request(
        id === undefined ? '/api/products' : `/api/products/${id}`,
        id === undefined ? 'POST' : 'PATCH',
        input,
    );
    if (isRecord(body) && isProduct(body.data)) return body.data;
    throw new Error('Unexpected product response.');
}

export async function deleteProduct(id: number): Promise<void> {
    await request(`/api/products/${id}`, 'DELETE');
}
