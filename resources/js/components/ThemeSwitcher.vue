<script setup lang="ts">
import { ref, onMounted } from 'vue';

const themes: string[] = ['light', 'dark', 'nord'];
const currentTheme = ref<string>('light');

onMounted((): void => {
    const savedTheme = document.documentElement.getAttribute('data-theme');
    if (savedTheme && themes.includes(savedTheme)) {
        currentTheme.value = savedTheme;
    }
});

const cycleTheme = (): void => {
    const currentIndex = themes.indexOf(currentTheme.value);
    const nextIndex = (currentIndex + 1) % themes.length;
    const newTheme = themes[nextIndex];

    currentTheme.value = newTheme;
    document.documentElement.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
};
</script>

<template>
    <button class="theme-switcher" @click="cycleTheme" :title="'Текущая тема: ' + currentTheme">
        <span v-if="currentTheme === 'light'">☀️</span>
        <span v-else-if="currentTheme === 'dark'">🌙</span>
        <span v-else>❄️</span>
    </button>
</template>

<style scoped>
.theme-switcher {
    background: none;
    border: 1px solid var(--border-color);
    cursor: pointer;
    font-size: 1.2rem;
    padding: 0.3rem 0.6rem;
    border-radius: 6px;
    color: var(--text-color);
    transition: background-color 0.2s, border-color 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}
.theme-switcher:hover {
    background-color: var(--page-bg);
}
</style>
