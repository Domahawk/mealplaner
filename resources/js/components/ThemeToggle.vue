<script setup lang="ts">
import { useThemeStore } from '../stores/theme';

const theme = useThemeStore();
</script>

<template>
    <div class="theme-controls">
        <button
            class="theme-toggle"
            type="button"
            :aria-label="theme.dark ? 'Switch to light theme' : 'Switch to dark theme'"
            :aria-pressed="theme.dark"
            @click="theme.toggle"
        >
            <span aria-hidden="true">{{ theme.dark ? '☀' : '☾' }}</span>
            <span>{{ theme.dark ? 'Light' : 'Dark' }}</span>
        </button>
        <button
            v-if="theme.preference !== 'system'"
            class="text-button system-theme"
            type="button"
            @click="theme.setPreference('system')"
        >
            Use system
        </button>
    </div>
</template>
