import { createApp, defineAsyncComponent } from 'vue';

/** Lazy-load header widgets so article HTML paints sooner (mobile LCP/FCP). */
const ThemeSwitcher = defineAsyncComponent(() => import('./components/ThemeSwitcher.vue'));
const LanguageSwitcher = defineAsyncComponent(() => import('./components/LanguageSwitcher.vue'));

const app = createApp({});

app.component('theme-switcher', ThemeSwitcher);
app.component('language-switcher', LanguageSwitcher);

app.mount('#app');
