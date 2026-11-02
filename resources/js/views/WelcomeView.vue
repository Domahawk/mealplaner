<script setup lang="ts">
import ConnectionCard from '../components/ConnectionCard.vue';
</script>

<template>
    <div class="welcome-view">
        <section class="hero" aria-labelledby="welcome-heading">
            <p class="eyebrow"><span class="tiny-square" aria-hidden="true"></span> WELCOME</p>
            <h1 id="welcome-heading">Mealplaner</h1>
            <p class="hero-description">Your food, recipes, and nutrition in one place.</p>
            <p class="hero-detail">
                Mealplaner is in its initial development stage. More features are on the way.
            </p>
        </section>
        <ConnectionCard />
    </div>
</template>
