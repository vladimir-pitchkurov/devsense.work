<script setup lang="ts">
import { ref } from 'vue';

const lines = ref<string[]>([
    'php artisan tinker',
    'echo match(200) { 200 => "Успех", default => "Ошибка" };'
]);
const result = ref<string | null>(null);
const isRunning = ref<boolean>(false);

const runCode = (): void => {
    isRunning.value = true;
    result.value = null;
    setTimeout(() => {
        result.value = '"Успех"';
        isRunning.value = false;
    }, 800);
};
</script>

<template>
    <div class="terminal">
        <div class="terminal__header">
            <span class="terminal__button terminal__button--red"></span>
            <span class="terminal__button terminal__button--yellow"></span>
            <span class="terminal__button terminal__button--green"></span>
            <span class="terminal__title">PHP Interactive</span>
        </div>
        <div class="terminal__body">
            <p class="terminal__line" v-for="(line, index) in lines" :key="index">
                <span class="terminal__prompt">$</span> {{ line }}
            </p>
            <button class="terminal__action" @click="runCode" :disabled="isRunning">
                {{ isRunning ? 'Executing...' : 'Run Match Expression' }}
            </button>
            <p class="terminal__result" v-if="result !== null">
                > {{ result }}
            </p>
        </div>
    </div>
</template>

<style scoped>
.terminal {
    background-color: #1e1e1e;
    border-radius: 8px;
    overflow: hidden;
    color: #d4d4d4;
    font-family: monospace;
    margin: 1.5rem 0;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
}

.terminal__header {
    background-color: #323233;
    padding: 8px 12px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.terminal__button {
    width: 12px;
    height: 12px;
    border-radius: 50%;
}

.terminal__button--red {
    background-color: #ff5f56;
}

.terminal__button--yellow {
    background-color: #ffbd2e;
}

.terminal__button--green {
    background-color: #27c93f;
}

.terminal__title {
    margin-left: auto;
    margin-right: auto;
    font-size: 0.8rem;
    color: #8a8a8a;
}

.terminal__body {
    padding: 16px;
}

.terminal__prompt {
    color: #4af626;
    margin-right: 8px;
}

.terminal__action {
    margin-top: 12px;
    background-color: #4f46e5;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    font-family: inherit;
    font-weight: 500;
    transition: opacity 0.2s;
}

.terminal__action:hover {
    opacity: 0.9;
}

.terminal__action:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.terminal__result {
    margin-top: 16px;
    color: #ce9178;
}
</style>
