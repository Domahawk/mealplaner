<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { AuthenticationError } from '../lib/api';
import { useAuthStore } from '../stores/auth';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const registering = computed(() => route.name === 'register');
const name = ref('');
const email = ref('');
const password = ref('');
const confirmation = ref('');
const submitting = ref(false);
const message = ref('');
const errors = ref<Record<string, string[]>>({});
const errorSummary = ref<HTMLElement>();

watch(
    () => route.name,
    () => {
        password.value = '';
        confirmation.value = '';
        message.value = '';
        errors.value = {};
    },
);

async function submit(): Promise<void> {
    if (submitting.value) return;
    submitting.value = true;
    message.value = '';
    errors.value = {};
    try {
        await auth.submit(registering.value ? 'register' : 'login', {
            email: email.value,
            password: password.value,
            ...(registering.value
                ? { name: name.value, password_confirmation: confirmation.value }
                : {}),
        });
        await router.replace({ name: 'account' });
    } catch (error: unknown) {
        message.value =
            error instanceof AuthenticationError
                ? error.message
                : 'Unable to sign in. Check your connection and try again.';
        errors.value = error instanceof AuthenticationError ? error.errors : {};
        errorSummary.value?.focus();
    } finally {
        password.value = '';
        confirmation.value = '';
        submitting.value = false;
    }
}
</script>

<template>
    <section class="auth-view" aria-labelledby="auth-heading">
        <h1 id="auth-heading">{{ registering ? 'Create your account' : 'Log in' }}</h1>
        <p>{{ registering ? 'Start planning with Mealplaner.' : 'Welcome back to Mealplaner.' }}</p>
        <form class="auth-form" :aria-busy="submitting" @submit.prevent="submit">
            <div ref="errorSummary" tabindex="-1" role="alert" class="form-error">
                <p v-if="message">{{ message }}</p>
            </div>
            <template v-if="registering">
                <label for="auth-name">Name</label>
                <input
                    id="auth-name"
                    v-model="name"
                    name="name"
                    autocomplete="name"
                    required
                    maxlength="255"
                    :aria-invalid="Boolean(errors.name)"
                    aria-describedby="name-error"
                />
                <p id="name-error" class="field-error">{{ errors.name?.[0] }}</p>
            </template>
            <label for="auth-email">Email</label>
            <input
                id="auth-email"
                v-model="email"
                name="email"
                type="email"
                autocomplete="email"
                required
                maxlength="255"
                :aria-invalid="Boolean(errors.email)"
                aria-describedby="email-error"
            />
            <p id="email-error" class="field-error">{{ errors.email?.[0] }}</p>
            <label for="auth-password">Password</label>
            <input
                id="auth-password"
                v-model="password"
                name="password"
                type="password"
                :autocomplete="registering ? 'new-password' : 'current-password'"
                required
                :aria-invalid="Boolean(errors.password)"
                aria-describedby="password-help password-error"
            />
            <p id="password-help" class="field-help">
                {{
                    registering
                        ? 'Use at least 12 characters with letters and numbers, up to 72 bytes.'
                        : 'Enter your password.'
                }}
            </p>
            <p id="password-error" class="field-error">{{ errors.password?.[0] }}</p>
            <template v-if="registering">
                <label for="auth-confirmation">Confirm password</label>
                <input
                    id="auth-confirmation"
                    v-model="confirmation"
                    name="password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    required
                />
            </template>
            <button class="primary-button" type="submit" :disabled="submitting">
                {{ submitting ? 'Please wait…' : registering ? 'Create account' : 'Log in' }}
            </button>
        </form>
        <RouterLink class="inline-link" :to="registering ? '/login' : '/register'">
            {{
                registering ? 'Already registered? Log in' : 'New to Mealplaner? Create an account'
            }}
        </RouterLink>
    </section>
</template>
