<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';

const themes: string[] = ['light', 'dark', 'nord'];
const currentTheme = ref<string>('light');

const themeTitlePrefix = computed((): string => {
    return document.body?.dataset?.a11yThemeSwitcher ?? 'Theme';
});

const buttonTitle = computed((): string => {
    return `${themeTitlePrefix.value}: ${currentTheme.value}`;
});

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
    <button class="theme-switcher" @click="cycleTheme" :title="buttonTitle">
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
    padding: 0 0.6rem;
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
