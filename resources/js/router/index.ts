import { createRouter, createWebHistory } from 'vue-router';
import type { RouteRecordRaw } from 'vue-router';
import WelcomeView from '../views/WelcomeView.vue';
import AboutView from '../views/AboutView.vue';
import NotFoundView from '../views/NotFoundView.vue';

export const routes: RouteRecordRaw[] = [
    { path: '/', name: 'welcome', component: WelcomeView, meta: { title: 'Mealplaner' } },
    { path: '/about', name: 'about', component: AboutView, meta: { title: 'About | Mealplaner' } },
    {
        path: '/:pathMatch(.*)*',
        name: 'not-found',
        component: NotFoundView,
        meta: { title: 'Page not found | Mealplaner' },
    },
];

export const router = createRouter({ history: createWebHistory(), routes });
