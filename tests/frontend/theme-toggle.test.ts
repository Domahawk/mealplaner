import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { mount, type VueWrapper } from '@vue/test-utils';
import { createPinia } from 'pinia';
import ThemeToggle from '../../resources/js/components/ThemeToggle.vue';

let wrapper: VueWrapper | undefined;

beforeEach(() => {
    localStorage.clear();
    document.documentElement.removeAttribute('data-theme');
});

afterEach(() => {
    wrapper?.unmount();
    vi.restoreAllMocks();
});

function mountToggle(): VueWrapper {
    wrapper = mount(ThemeToggle, { global: { plugins: [createPinia()] } });
    return wrapper;
}

describe('ThemeToggle', () => {
    it('exposes the selected theme through its accessible button state', async () => {
        const toggle = mountToggle();
        const button = toggle.get('button[aria-label="Switch to dark theme"]');
        expect(button.attributes('type')).toBe('button');
        expect(button.attributes('aria-pressed')).toBe('false');

        await button.trigger('click');
        expect(
            toggle.get('button[aria-label="Switch to light theme"]').attributes('aria-pressed'),
        ).toBe('true');
        await toggle.get('.system-theme').trigger('click');
        expect(
            toggle.get('button[aria-label="Switch to dark theme"]').attributes('aria-pressed'),
        ).toBe('false');
        expect(toggle.find('.system-theme').exists()).toBe(false);
    });

    it('keeps theme switching usable when browser storage is blocked', async () => {
        const blocked = () => {
            throw new DOMException('Storage unavailable', 'SecurityError');
        };
        vi.spyOn(Storage.prototype, 'getItem').mockImplementation(blocked);
        vi.spyOn(Storage.prototype, 'setItem').mockImplementation(blocked);
        const toggle = mountToggle();

        await toggle.get('button[aria-label="Switch to dark theme"]').trigger('click');
        expect(
            toggle.get('button[aria-label="Switch to light theme"]').attributes('aria-pressed'),
        ).toBe('true');
        expect(document.documentElement.dataset.theme).toBe('dark');
    });
});
