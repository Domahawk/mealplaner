<script setup lang="ts">
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
</script>

<template>
    <section class="auth-view" aria-labelledby="account-heading">
        <h1 id="account-heading">Your account</h1>
        <p v-if="auth.user">You are signed in as {{ auth.user.name }} ({{ auth.user.email }}).</p>
        <p>Your food, recipes, and nutrition features are still in development.</p>
    </section>
</template>
