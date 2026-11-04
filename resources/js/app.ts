import '../css/app.css';
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import { router, installAuthenticationGuard } from './router';

const pinia = createPinia();
installAuthenticationGuard(router, pinia);
createApp(App).use(pinia).use(router).mount('#app');
