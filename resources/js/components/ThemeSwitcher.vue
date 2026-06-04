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
    background: var(--bg-color);
    border: 1px solid var(--border-color);
    cursor: pointer;
    font-size: 1.1rem;
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 50%;
    color: var(--text-color);
    box-shadow: 0 2px 8px var(--shadow-color);
    transition: background-color 0.3s, border-color 0.3s, transform 0.2s, box-shadow 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    outline: none;
}
.theme-switcher:hover {
    background-color: var(--code-header-bg);
    border-color: var(--primary-color);
    box-shadow: 0 4px 12px var(--shadow-hover);
    transform: translateY(-1px);
}
.theme-switcher:active {
    transform: translateY(0);
}

</style>
