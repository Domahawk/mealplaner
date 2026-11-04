import { createRouter, createWebHistory } from 'vue-router';
import type { RouteRecordRaw, Router } from 'vue-router';
import type { Pinia } from 'pinia';
import { useAuthStore } from '../stores/auth';
import WelcomeView from '../views/WelcomeView.vue';
import AboutView from '../views/AboutView.vue';
import NotFoundView from '../views/NotFoundView.vue';
import AuthenticationView from '../views/AuthenticationView.vue';
import AccountView from '../views/AccountView.vue';

declare module 'vue-router' {
    interface RouteMeta {
        title?: string;
        requiresAuth?: boolean;
        guestOnly?: boolean;
    }
}

export const routes: RouteRecordRaw[] = [
    { path: '/', name: 'welcome', component: WelcomeView, meta: { title: 'Mealplaner' } },
    { path: '/about', name: 'about', component: AboutView, meta: { title: 'About | Mealplaner' } },
    {
        path: '/login',
        name: 'login',
        component: AuthenticationView,
        meta: { title: 'Log in | Mealplaner', guestOnly: true },
    },
    {
        path: '/register',
        name: 'register',
        component: AuthenticationView,
        meta: { title: 'Create account | Mealplaner', guestOnly: true },
    },
    {
        path: '/account',
        name: 'account',
        component: AccountView,
        meta: { title: 'Your account | Mealplaner', requiresAuth: true },
    },
    {
        path: '/:pathMatch(.*)*',
        name: 'not-found',
        component: NotFoundView,
        meta: { title: 'Page not found | Mealplaner' },
    },
];

export const router = createRouter({ history: createWebHistory(), routes });

export function installAuthenticationGuard(target: Router, pinia: Pinia): void {
    target.beforeEach(async (to) => {
        const auth = useAuthStore(pinia);
        try {
            await auth.loadSession(Boolean(to.meta.requiresAuth || to.meta.guestOnly));
        } catch {
            if (to.meta.requiresAuth) return { name: 'login' };
            return true;
        }
        if (to.meta.requiresAuth && !auth.user) return { name: 'login' };
        if (to.meta.guestOnly && auth.user) return { name: 'account' };
        return true;
    });
}
