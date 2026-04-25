<script setup lang="ts">
import { RouterLink } from 'vue-router'
</script>

<template>
  <main class="not-found">
    <section class="not-found__card">
      <p class="not-found__code">404</p>
      <h1>Страница не найдена</h1>
      <p class="not-found__text">
        Похоже, ссылка устарела или страница была перемещена.
      </p>
      <div class="not-found__actions">
        <RouterLink to="/" class="not-found__btn not-found__btn--primary">На главную</RouterLink>
        <RouterLink to="/catalog" class="not-found__btn">В каталог</RouterLink>
      </div>
    </section>
  </main>
</template>

<style scoped>
.not-found {
  width: min(980px, 92vw);
  margin: 0 auto;
  padding: 36px 0 72px;
}

.not-found__card {
  border: 1px solid #e0ddd6;
  background: #fbfbfb;
  border-radius: 24px;
  padding: 36px 24px;
  text-align: center;
}

.not-found__code {
  margin: 0;
  color: #b95b09;
  font-family: var(--font-display);
  font-size: clamp(44px, 8vw, 88px);
  line-height: 1;
}

.not-found h1 {
  margin: 10px 0 8px;
}

.not-found__text {
  margin: 0 auto;
  color: #4d5b75;
  max-width: 560px;
}

.not-found__actions {
  margin-top: 22px;
  display: flex;
  justify-content: center;
  gap: 10px;
  flex-wrap: wrap;
}

.not-found__btn {
  text-decoration: none;
  color: #1f2233;
  border: 1px solid #d7d3cb;
  border-radius: 999px;
  padding: 10px 16px;
  font-weight: 700;
}

.not-found__btn--primary {
  background: #1f2233;
  color: #fff;
  border-color: #1f2233;
}
</style>

