import { createApp } from 'vue';
import ThemeSwitcher from './components/ThemeSwitcher.vue';
import LanguageSwitcher from "./components/LanguageSwitcher.vue";

const app = createApp({});

app.component('theme-switcher', ThemeSwitcher);
app.component('language-switcher', LanguageSwitcher);

app.mount('#app');
