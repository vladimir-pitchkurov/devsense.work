import './bootstrap';
import { createApp } from 'vue';
import LiveTerminal from './components/LiveTerminal.vue';
import ThemeSwitcher from './components/ThemeSwitcher.vue';

const app = createApp({});

app.component('live-terminal', LiveTerminal);
app.component('theme-switcher', ThemeSwitcher);

app.mount('#app');
