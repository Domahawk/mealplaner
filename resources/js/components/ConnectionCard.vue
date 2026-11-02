<script setup lang="ts">
import { useConnectionStore } from '../stores/connection';

const connection = useConnectionStore();
</script>

<template>
    <section class="connection-card" aria-labelledby="connection-heading">
        <div class="connection-header">
            <div>
                <p class="eyebrow">SYSTEM CHECK</p>
                <h2 id="connection-heading">A good connection.</h2>
            </div>
            <span class="status-badge" :class="connection.status">
                <span class="status-dot" aria-hidden="true"></span>
                {{
                    {
                        idle: 'Not checked',
                        loading: 'Checking',
                        success: 'Connected',
                        error: 'Unavailable',
                    }[connection.status]
                }}
            </span>
        </div>
        <p class="connection-message" role="status" aria-live="polite">{{ connection.message }}</p>
        <div class="connection-footer">
            <span class="endpoint"><span aria-hidden="true">↗</span> GET /api/health</span>
            <button
                class="primary-button"
                type="button"
                :disabled="connection.status === 'loading'"
                @click="connection.check"
            >
                {{ connection.status === 'loading' ? 'Checking…' : 'Check server connection' }}
                <span aria-hidden="true">→</span>
            </button>
        </div>
    </section>
</template>
