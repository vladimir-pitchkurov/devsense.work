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

const currentLocale = ref<string>('en');

const selectTitle = ref<string>('Choose language');

onMounted((): void => {
    let htmlLang = document.documentElement.getAttribute('lang');
    if (htmlLang) {
        if (htmlLang === 'uk') {
            htmlLang = 'ua';
        }
        currentLocale.value = htmlLang;
    }
    const fromBody = document.body?.dataset?.a11yLanguageSelect;
    if (fromBody) {
        selectTitle.value = fromBody;
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
            :title="selectTitle"
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
    background-color: var(--bg-color);
    border: 1px solid var(--border-color);
    color: var(--text-color);
    font-family: 'Outfit', sans-serif;
    font-size: 0.85rem;
    font-weight: 600;
    padding: 0.4rem 1.8rem 0.4rem 0.85rem;
    border-radius: 9999px;
    cursor: pointer;
    box-shadow: 0 2px 8px var(--shadow-color);
    transition: background-color 0.3s, border-color 0.3s, transform 0.2s, box-shadow 0.3s;
    outline: none;
}

.lang-switcher::after {
    content: "▼";
    font-size: 0.5rem;
    color: var(--text-muted);
    position: absolute;
    right: 0.8rem;
    pointer-events: none;
    transition: color 0.2s;
}

.lang-switcher__select:hover {
    background-color: var(--code-header-bg);
    border-color: var(--primary-color);
    box-shadow: 0 4px 12px var(--shadow-hover);
    transform: translateY(-1px);
}

.lang-switcher__select:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px var(--primary-glow);
}

.lang-switcher__select option {
    background-color: var(--bg-color);
    color: var(--text-color);
    font-weight: normal;
}

</style>
