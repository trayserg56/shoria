import { createRouter, createWebHistory } from 'vue-router'
import { getAuthToken } from '@/lib/auth-token'
import { clearStructuredData, setSeoMeta } from '@/lib/seo'

const SCROLL_KEY = 'scroll_restore'

// Убираем ?preview из пути при сравнении — он управляется через history.replaceState
function stripPreview(p: string): string {
  return p.replace(/([?&])preview=[^&]*(&|$)/, '$1').replace(/[?&]$/, '')
}

if (typeof window !== 'undefined') {
  if ('scrollRestoration' in window.history) {
    window.history.scrollRestoration = 'manual'
  }

  // Сохраняем позицию при уходе со страницы
  window.addEventListener('beforeunload', () => {
    if (window.scrollY > 0) {
      sessionStorage.setItem(
        SCROLL_KEY,
        JSON.stringify({ path: location.pathname + location.search, y: Math.round(window.scrollY) }),
      )
    }
  })

  // Восстанавливаем позицию независимо от scrollBehavior роутера.
  // Запускаем сразу, но ждём пока страница наберёт нужную высоту.
  try {
    const raw = sessionStorage.getItem(SCROLL_KEY)
    if (raw) {
      const { path, y } = JSON.parse(raw) as { path: string; y: number }
      if (stripPreview(path) === stripPreview(location.pathname + location.search) && y > 0) {
        sessionStorage.removeItem(SCROLL_KEY)
        const deadline = Date.now() + 4000
        const tryScroll = () => {
          if (document.documentElement.scrollHeight >= y + window.innerHeight) {
            window.scrollTo({ top: y, behavior: 'instant' })
          } else if (Date.now() < deadline) {
            requestAnimationFrame(tryScroll)
          } else {
            // Контент так и не вырос — скроллим на всё что есть
            window.scrollTo({ top: y, behavior: 'instant' })
          }
        }
        // Ждём завершения первого рендера и роутерного скролла к нулю
        setTimeout(() => requestAnimationFrame(tryScroll), 500)
      }
    }
  } catch { /* ignore */ }
}

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  scrollBehavior(to, from, savedPosition) {
    const catalogRouteNames = new Set(['catalog', 'catalog-category', 'catalog-subcategory', 'catalog-deep'])
    const isCatalogRoute = catalogRouteNames.has(String(to.name ?? '')) && catalogRouteNames.has(String(from.name ?? ''))
    const isSameCatalogPath = isCatalogRoute && to.path === from.path
    const isCatalogPageChange = isSameCatalogPath && String(to.query.page ?? '1') !== String(from.query.page ?? '1')
    const isProductRoute =
      (to.name === 'product' || to.name === 'product-legacy')
      && (from.name === 'product' || from.name === 'product-legacy')
    const isSameProductSlug =
      String(to.params.slug ?? '') !== ''
      && String(to.params.slug ?? '') === String(from.params.slug ?? '')
    const isVariantSwitchWithinProduct = isProductRoute && isSameProductSlug

    if (savedPosition) {
      return savedPosition
    }

    if (isCatalogPageChange) {
      return { top: 0, left: 0 }
    }

    // Keep scroll position when catalog query params change (filters/sort).
    // Using route name is safer than path matching and avoids rare edge-cases with URL normalization.
    if (isSameCatalogPath) {
      return false
    }

    // Keep scroll position only when switching variants inside the same product card.
    if (isVariantSwitchWithinProduct) {
      return false
    }

    if (to.hash) {
      return { el: to.hash, top: 12 }
    }

    return { top: 0, left: 0 }
  },
  routes: [
    {
      path: '/',
      name: 'home',
      component: () => import('../views/HomeView.vue'),
      meta: {
        seoTitle: 'Shoria — интернет-магазин',
        seoDescription: 'Shoria: каталог товаров, рекомендации и удобный checkout.',
      },
    },
    {
      path: '/brands',
      name: 'brands',
      component: () => import('../views/BrandsView.vue'),
      meta: {
        seoTitle: 'Бренды — Shoria',
        seoDescription: 'Страница брендов Shoria с быстрым переходом в каталог по выбранному бренду.',
      },
    },
    {
      path: '/catalog',
      name: 'catalog',
      component: () => import('../views/CatalogView.vue'),
      meta: {
        seoTitle: 'Каталог — Shoria',
        seoDescription: 'Каталог товаров Shoria: фильтры, поиск, сортировка и быстрый выбор.',
      },
    },
    {
      path: '/catalog/:categorySlug',
      name: 'catalog-category',
      component: () => import('../views/CatalogView.vue'),
      meta: {
        seoTitle: 'Каталог — Shoria',
        seoDescription: 'Каталог товаров Shoria: фильтры, поиск, сортировка и быстрый выбор.',
      },
    },
    {
      path: '/catalog/:categorySlug/:subcategorySlug',
      name: 'catalog-subcategory',
      component: () => import('../views/CatalogView.vue'),
      meta: {
        seoTitle: 'Каталог — Shoria',
        seoDescription: 'Каталог товаров Shoria: фильтры, поиск, сортировка и быстрый выбор.',
      },
    },
    {
      path: '/catalog/:categorySlug/:subcategorySlug/:deepPath(.*)*',
      name: 'catalog-deep',
      component: () => import('../views/CatalogView.vue'),
      meta: {
        seoTitle: 'Каталог — Shoria',
        seoDescription: 'Каталог товаров Shoria: фильтры, поиск, сортировка и быстрый выбор.',
      },
    },
    {
      path: '/news/:slug',
      name: 'news-post',
      component: () => import('../views/NewsPostView.vue'),
      meta: {
        seoTitle: 'Новость — Shoria',
        seoDescription: 'Статья и новости Shoria о товарах, трендах и подборках.',
      },
    },
    {
      path: '/news',
      name: 'news',
      component: () => import('../views/NewsListView.vue'),
      meta: {
        seoTitle: 'Новости — Shoria',
        seoDescription: 'Новости и подборки Shoria: тренды, новинки и полезные гайды.',
      },
    },
    {
      path: '/pages/:slug',
      name: 'service-page',
      component: () => import('../views/ServicePageView.vue'),
      meta: {
        seoTitle: 'Информация — Shoria',
        seoDescription: 'Служебная информация магазина Shoria.',
      },
    },
    {
      path: '/loyalty-program',
      name: 'loyalty-program',
      component: () => import('../views/LoyaltyProgramView.vue'),
      meta: {
        seoTitle: 'Программа лояльности — Shoria',
        seoDescription: 'Условия программы лояльности: уровни, начисления и списание баллов.',
      },
    },
    {
      path: '/product/:categorySlug/:slug/:variantSlug?',
      name: 'product',
      component: () => import('../views/ProductView.vue'),
      meta: {
        seoTitle: 'Товар — Shoria',
        seoDescription: 'Карточка товара Shoria: фото, цены, характеристики и рекомендации.',
      },
    },
    {
      path: '/product/:slug/:variantSlug?',
      name: 'product-legacy',
      component: () => import('../views/ProductView.vue'),
      meta: {
        seoTitle: 'Товар — Shoria',
        seoDescription: 'Карточка товара Shoria: фото, цены, характеристики и рекомендации.',
      },
    },
    {
      path: '/cart',
      name: 'cart',
      component: () => import('../views/CartView.vue'),
      meta: {
        seoTitle: 'Корзина — Shoria',
        seoDescription: 'Корзина покупок Shoria.',
        seoRobots: 'noindex,nofollow',
      },
    },
    {
      path: '/checkout',
      name: 'checkout',
      component: () => import('../views/CheckoutView.vue'),
      meta: {
        seoTitle: 'Оформление заказа — Shoria',
        seoDescription: 'Оформление заказа Shoria: доставка и оплата.',
        seoRobots: 'noindex,nofollow',
      },
    },
    {
      path: '/wishlist',
      name: 'wishlist',
      component: () => import('../views/WishlistView.vue'),
      meta: {
        seoTitle: 'Избранное — Shoria',
        seoDescription: 'Список избранных товаров Shoria.',
        seoRobots: 'noindex,nofollow',
      },
    },
    {
      path: '/compare',
      name: 'compare',
      component: () => import('../views/CompareView.vue'),
      meta: {
        seoTitle: 'Сравнение — Shoria',
        seoDescription: 'Сравнение товаров по ключевым параметрам.',
        seoRobots: 'noindex,nofollow',
      },
    },
    {
      path: '/order-success/:orderNumber',
      name: 'order-success',
      component: () => import('../views/OrderSuccessView.vue'),
      meta: {
        seoTitle: 'Заказ оформлен — Shoria',
        seoDescription: 'Подтверждение оформления заказа.',
        seoRobots: 'noindex,nofollow',
      },
    },
    {
      path: '/auth/oauth-callback',
      name: 'auth-oauth-callback',
      component: () => import('../views/AuthOAuthCallbackView.vue'),
      meta: {
        seoTitle: 'Вход через ВКонтакте — Shoria',
        seoDescription: '',
        seoRobots: 'noindex,nofollow',
      },
    },
    {
      path: '/account',
      component: () => import('@/components/account/AccountLayout.vue'),
      meta: {
        requiresAuth: true,
        seoTitle: 'Профиль — Shoria',
        seoDescription: 'Личный кабинет покупателя Shoria.',
        seoRobots: 'noindex,nofollow',
      },
      children: [
        {
          path: '',
          name: 'account-overview',
          component: () => import('../views/AccountOverviewView.vue'),
          meta: {
            seoTitle: 'Кабинет — Shoria',
            seoDescription: 'Обзор личного кабинета покупателя Shoria.',
            seoRobots: 'noindex,nofollow',
          },
        },
        {
          path: 'settings',
          name: 'account-settings',
          component: () => import('../views/AccountSettingsView.vue'),
          meta: {
            seoTitle: 'Настройки профиля — Shoria',
            seoDescription: 'Редактирование имени, телефона, email и статуса подтверждения.',
            seoRobots: 'noindex,nofollow',
          },
        },
        {
          path: 'orders',
          name: 'account-orders',
          component: () => import('../views/AccountOrdersView.vue'),
          meta: {
            seoTitle: 'Заказы — Shoria',
            seoDescription: 'История заказов и статусы покупок в кабинете Shoria.',
            seoRobots: 'noindex,nofollow',
          },
        },
        {
          path: 'saved',
          name: 'account-saved',
          component: () => import('../views/AccountSavedView.vue'),
          meta: {
            seoTitle: 'Избранное и сравнение — Shoria',
            seoDescription: 'Сохранённые товары и сравнение внутри личного кабинета Shoria.',
            seoRobots: 'noindex,nofollow',
          },
        },
        {
          path: 'loyalty',
          name: 'account-loyalty',
          component: () => import('../views/AccountLoyaltyView.vue'),
          meta: {
            seoTitle: 'Программа лояльности — Shoria',
            seoDescription: 'Баллы, уровни и история операций по программе лояльности.',
            seoRobots: 'noindex,nofollow',
          },
        },
        {
          path: 'gift-certificates',
          name: 'account-gift-certificates',
          component: () => import('../views/AccountGiftCertificatesView.vue'),
          meta: {
            seoTitle: 'Подарочные сертификаты — Shoria',
            seoDescription: 'Покупка и список сертификатов в личном кабинете.',
            seoRobots: 'noindex,nofollow',
          },
        },
        {
          path: 'reviews',
          name: 'account-reviews',
          component: () => import('../views/AccountReviewsView.vue'),
          meta: {
            seoTitle: 'Ваши отзывы — Shoria',
            seoDescription: 'Оставленные отзывы и товары, по которым можно поделиться впечатлением.',
            seoRobots: 'noindex,nofollow',
          },
        },
        {
          path: 'addresses',
          name: 'account-addresses',
          component: () => import('../views/AccountAddressesView.vue'),
          meta: {
            seoTitle: 'Адреса доставки — Shoria',
            seoDescription: 'Сохранённые адреса доставки в личном кабинете Shoria.',
            seoRobots: 'noindex,nofollow',
          },
        },
      ],
    },
    {
      path: '/orders',
      redirect: '/account/orders',
    },
    {
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      component: () => import('../views/NotFoundView.vue'),
      meta: {
        seoTitle: 'Страница не найдена — Shoria',
        seoDescription: 'Запрошенная страница не найдена.',
        seoRobots: 'noindex,nofollow',
      },
    },
  ],
})

router.beforeEach((to) => {
  if (to.meta.requiresAuth && !getAuthToken()) {
    return {
      path: '/',
      query: {
        auth: '1',
      },
    }
  }

  return true
})

router.afterEach((to) => {
  const title = (to.meta.seoTitle as string | undefined) ?? 'Shoria — интернет-магазин'
  const description =
    (to.meta.seoDescription as string | undefined) ??
    'Shoria: каталог товаров, рекомендации и удобный checkout.'
  const robots = (to.meta.seoRobots as string | undefined) ?? 'index,follow'
  const canonical = `${window.location.origin}${to.path}`

  setSeoMeta({
    title,
    description,
    robots,
    canonical,
  })
  clearStructuredData()
})

export default router
