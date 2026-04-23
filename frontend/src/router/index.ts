import { createRouter, createWebHistory } from 'vue-router'
import { getAuthToken } from '@/lib/auth-token'
import AccountLayout from '@/components/account/AccountLayout.vue'
import { clearStructuredData, setSeoMeta } from '@/lib/seo'
import AccountOverviewView from '../views/AccountOverviewView.vue'
import AccountOrdersView from '../views/AccountOrdersView.vue'
import AccountSavedView from '../views/AccountSavedView.vue'
import AccountSettingsView from '../views/AccountSettingsView.vue'
import CatalogView from '../views/CatalogView.vue'
import CartView from '../views/CartView.vue'
import CompareView from '../views/CompareView.vue'
import HomeView from '../views/HomeView.vue'
import NewsListView from '../views/NewsListView.vue'
import NewsPostView from '../views/NewsPostView.vue'
import OrderSuccessView from '../views/OrderSuccessView.vue'
import ProductView from '../views/ProductView.vue'
import WishlistView from '../views/WishlistView.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  scrollBehavior(to, from, savedPosition) {
    if (savedPosition) {
      return savedPosition
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
      component: HomeView,
      meta: {
        seoTitle: 'Shoria — интернет-магазин',
        seoDescription: 'Shoria: каталог товаров, рекомендации и удобный checkout.',
      },
    },
    {
      path: '/catalog',
      name: 'catalog',
      component: CatalogView,
      meta: {
        seoTitle: 'Каталог — Shoria',
        seoDescription: 'Каталог товаров Shoria: фильтры, поиск, сортировка и быстрый выбор.',
      },
    },
    {
      path: '/news/:slug',
      name: 'news-post',
      component: NewsPostView,
      meta: {
        seoTitle: 'Новость — Shoria',
        seoDescription: 'Статья и новости Shoria о товарах, трендах и подборках.',
      },
    },
    {
      path: '/news',
      name: 'news',
      component: NewsListView,
      meta: {
        seoTitle: 'Новости — Shoria',
        seoDescription: 'Новости и подборки Shoria: тренды, новинки и полезные гайды.',
      },
    },
    {
      path: '/product/:categorySlug/:slug/:variantSlug?',
      name: 'product',
      component: ProductView,
      meta: {
        seoTitle: 'Товар — Shoria',
        seoDescription: 'Карточка товара Shoria: фото, цены, характеристики и рекомендации.',
      },
    },
    {
      path: '/product/:slug/:variantSlug?',
      name: 'product-legacy',
      component: ProductView,
      meta: {
        seoTitle: 'Товар — Shoria',
        seoDescription: 'Карточка товара Shoria: фото, цены, характеристики и рекомендации.',
      },
    },
    {
      path: '/cart',
      name: 'cart',
      component: CartView,
      meta: {
        seoTitle: 'Корзина — Shoria',
        seoDescription: 'Корзина покупок Shoria.',
        seoRobots: 'noindex,nofollow',
      },
    },
    {
      path: '/wishlist',
      name: 'wishlist',
      component: WishlistView,
      meta: {
        seoTitle: 'Избранное — Shoria',
        seoDescription: 'Список избранных товаров Shoria.',
        seoRobots: 'noindex,nofollow',
      },
    },
    {
      path: '/compare',
      name: 'compare',
      component: CompareView,
      meta: {
        seoTitle: 'Сравнение — Shoria',
        seoDescription: 'Сравнение товаров по ключевым параметрам.',
        seoRobots: 'noindex,nofollow',
      },
    },
    {
      path: '/order-success/:orderNumber',
      name: 'order-success',
      component: OrderSuccessView,
      meta: {
        seoTitle: 'Заказ оформлен — Shoria',
        seoDescription: 'Подтверждение оформления заказа.',
        seoRobots: 'noindex,nofollow',
      },
    },
    {
      path: '/account',
      component: AccountLayout,
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
          component: AccountOverviewView,
          meta: {
            seoTitle: 'Кабинет — Shoria',
            seoDescription: 'Обзор личного кабинета покупателя Shoria.',
            seoRobots: 'noindex,nofollow',
          },
        },
        {
          path: 'settings',
          name: 'account-settings',
          component: AccountSettingsView,
          meta: {
            seoTitle: 'Настройки профиля — Shoria',
            seoDescription: 'Редактирование имени, телефона, email и статуса подтверждения.',
            seoRobots: 'noindex,nofollow',
          },
        },
        {
          path: 'orders',
          name: 'account-orders',
          component: AccountOrdersView,
          meta: {
            seoTitle: 'Заказы — Shoria',
            seoDescription: 'История заказов и статусы покупок в кабинете Shoria.',
            seoRobots: 'noindex,nofollow',
          },
        },
        {
          path: 'saved',
          name: 'account-saved',
          component: AccountSavedView,
          meta: {
            seoTitle: 'Избранное и сравнение — Shoria',
            seoDescription: 'Сохранённые товары и сравнение внутри личного кабинета Shoria.',
            seoRobots: 'noindex,nofollow',
          },
        },
      ],
    },
    {
      path: '/orders',
      redirect: '/account/orders',
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
