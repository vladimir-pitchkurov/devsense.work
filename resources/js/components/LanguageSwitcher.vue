<script setup lang="ts">
import { ref, onMounted } from 'vue';

interface Locale {
    code: string;
    label: string;
}

const locales: Locale[] = [
    { code: 'ru', label: 'RU' },
    { code: 'en', label: 'EN' },
    { code: 'ua', label: 'UA' },
    { code: 'bg', label: 'BG' }
];

const currentLocale = ref<string>('ru');

onMounted((): void => {
    const htmlLang = document.documentElement.getAttribute('lang');
    if (htmlLang) {
        currentLocale.value = htmlLang;
    }
});

const changeLanguage = (event: Event): void => {
    const target = event.target as HTMLSelectElement;
    const newLocale = target.value;

    if (newLocale === currentLocale.value) return;

    const pathParts = window.location.pathname.split('/');

    if (pathParts.length > 1 && locales.map(l => l.code).includes(pathParts[1])) {
        pathParts[1] = newLocale;
    } else {
        pathParts.splice(1, 0, newLocale);
    }

    window.location.href = pathParts.join('/') + window.location.search;
};
</script>

<template>
    <div class="lang-switcher">
        <select
            class="lang-switcher__select"
            :value="currentLocale"
            @change="changeLanguage"
            title="Выберите язык"
        >
            <option v-for="locale in locales" :key="locale.code" :value="locale.code">
                {{ locale.label }}
            </option>
        </select>
    </div>
</template>

<style scoped>
.lang-switcher {
    position: relative;
    display: inline-flex;
    align-items: center;
}

.lang-switcher__select {
    appearance: none;
    background-color: transparent;
    border: 1px solid var(--border-color);
    color: var(--text-color);
    font-family: inherit;
    font-size: 0.9rem;
    font-weight: 500;
    padding: 0.3rem 1.8rem 0.3rem 0.8rem;
    border-radius: 6px;
    cursor: pointer;
    transition: background-color 0.2s, border-color 0.2s;
    outline: none;
}

.lang-switcher::after {
    content: "▼";
    font-size: 0.6rem;
    color: var(--text-muted);
    position: absolute;
    right: 0.6rem;
    pointer-events: none;
}

.lang-switcher__select:hover {
    background-color: var(--page-bg);
}

.lang-switcher__select:focus {
    border-color: var(--primary-color);
}

.lang-switcher__select option {
    background-color: var(--bg-color);
    color: var(--text-color);
}
</style>
