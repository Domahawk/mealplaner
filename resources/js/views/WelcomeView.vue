<script setup lang="ts">
import ConnectionCard from '../components/ConnectionCard.vue';
</script>

<template>
    <div class="welcome-view">
        <section class="hero" aria-labelledby="welcome-heading">
            <p class="eyebrow">
                <span class="tiny-square" aria-hidden="true"></span> YOUR NEXT IDEA STARTS HERE
            </p>
            <h1 id="welcome-heading">A fresh start.<br /><span>Make it yours.</span></h1>
            <p class="hero-description">Laravel + Vue 3 PWA Starter</p>
            <p class="hero-detail">
                The essentials, connected. A considered foundation<br class="desktop-break" />
                for whatever you build next.
            </p>
        </section>
        <ConnectionCard />
        <section class="foundation" aria-labelledby="foundation-heading">
            <div class="section-heading">
                <h2 id="foundation-heading">Built on solid ground</h2>
                <span class="small-label">A SMALL, CAPABLE STACK</span>
            </div>
            <div class="stack-grid">
                <article class="stack-item">
                    <span class="stack-number">01 / BACKEND</span>
                    <h3>Laravel <span>↗</span></h3>
                    <p>A familiar home for your API,<br />routes, and database.</p>
                </article>
                <article class="stack-item">
                    <span class="stack-number">02 / INTERFACE</span>
                    <h3>Vue 3 + TypeScript <span>↗</span></h3>
                    <p>A reactive interface with clear<br />types and room to grow.</p>
                </article>
                <article class="stack-item">
                    <span class="stack-number">03 / EVERYWHERE</span>
                    <h3>Progressive web app <span>↗</span></h3>
                    <p>Installable, responsive, and<br />ready to open offline.</p>
                </article>
            </div>
        </section>
    </div>
</template>
