/**
 * Базовый сценарий нагрузки витрины (спринт 20).
 *
 * Запуск (нужен k6): https://grafana.com/docs/k6/latest/set-up/install-k6/
 *
 *   BASE_URL=http://localhost:8080 k6 run ops/load-tests/k6/catalog-core.js
 *
 * Пороги p95/p99 ориентировочные — поджимайте под свой SLA после замеров на пустом и наполненном каталоге.
 */
import http from 'k6/http';
import { check, sleep } from 'k6';

const base = __ENV.BASE_URL || 'http://localhost:8080';

export const options = {
  scenarios: {
    steady: {
      executor: 'constant-vus',
      vus: Number(__ENV.VUS || 10),
      duration: __ENV.DURATION || '45s',
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.05'],
    http_req_duration: ['p(95)<2500', 'p(99)<5000'],
  },
};

const paths = [
  () => `${base}/api/health`,
  () => `${base}/api/categories`,
  () => `${base}/api/home`,
  () => `${base}/api/brands`,
  () => `${base}/api/navigation`,
  () => `${base}/api/site-settings`,
  () => `${base}/api/products?page=1`,
  () => `${base}/api/search/suggest?q=nike`,
];

export default function () {
  const url = paths[Math.floor(Math.random() * paths.length)]();
  const res = http.get(url, { tags: { name: url.split('?')[0].replace(base, '') } });

  check(res, {
    'status is 2xx': (r) => r.status >= 200 && r.status < 300,
  });

  sleep(0.3 + Math.random() * 0.5);
}
