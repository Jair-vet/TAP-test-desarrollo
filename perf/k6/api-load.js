/**
 * Prueba de carga de la API con presupuestos de rendimiento (k6).
 *
 *   ./scripts/test-perf.sh        (o: make test-perf)   → corre en Docker, sin instalar nada
 *
 * Simula a los 4 perfiles demo navegando al mismo tiempo (listados, búsquedas,
 * órdenes, filtros, tablero) durante 30 s a ritmo constante. Si se rompe un
 * presupuesto, k6 termina con error y el CI falla.
 *
 * Presupuestos (thresholds):
 *   - errores HTTP ................ < 1 %
 *   - latencia p95 de lecturas .... < 300 ms
 *   - latencia p99 de lecturas .... < 800 ms
 *   - verificaciones correctas .... > 99 %
 *
 * El ritmo (6 peticiones/s repartidas entre 4 usuarios ≈ 90/min por usuario)
 * respeta el límite de la API (120/min por usuario): se mide rendimiento, no el
 * rate limit. Las contraseñas llegan por variables de entorno (nunca en el código).
 */
import exec from 'k6/execution';
import http from 'k6/http';
import { check } from 'k6';

const BASE = __ENV.BASE_URL || 'http://web/api/v1';

/** Cada usuario demo recorre las pantallas que su perfil puede ver. */
const USERS = [
  {
    email: 'admin@tap.test',
    password: __ENV.TAP_ADMIN_PASSWORD,
    urls: ['/dashboard', '/audit-logs', '/audit-logs?entity_type=product&event=updated', '/profiles', '/audit-logs?search=admin'],
  },
  {
    email: 'almacen@tap.test',
    password: __ENV.TAP_DEMO_PASSWORD,
    urls: ['/products?sort=price&direction=asc', '/products?search=casco', '/products?page=2&per_page=10', '/products?sort=name&direction=desc&per_page=25', '/products?search=bota%20seg'],
  },
  {
    email: 'consulta@tap.test',
    password: __ENV.TAP_DEMO_PASSWORD,
    urls: ['/products', '/users', '/products?search=die', '/users?sort=email&direction=asc'],
  },
  {
    email: 'rh@tap.test',
    password: __ENV.TAP_DEMO_PASSWORD,
    urls: ['/users?search=a', '/profiles', '/users?sort=name&direction=asc', '/profiles?search=alm'],
  },
];

export const options = {
  scenarios: {
    navegacion: {
      executor: 'constant-arrival-rate',
      rate: 6,
      timeUnit: '1s',
      duration: '30s',
      preAllocatedVUs: 10,
      maxVUs: 30,
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.01'],
    'http_req_duration{tipo:lectura}': ['p(95)<300', 'p(99)<800'],
    checks: ['rate>0.99'],
  },
  summaryTrendStats: ['avg', 'min', 'med', 'p(90)', 'p(95)', 'p(99)', 'max'],
};

/** Una sesión por usuario demo (se hace una sola vez, antes de la carga). */
export function setup() {
  return USERS.map((user) => {
    const res = http.post(`${BASE}/auth/login`, JSON.stringify({ email: user.email, password: user.password }), {
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      tags: { tipo: 'login' },
    });
    const token = res.status === 200 ? res.json('data.access_token') : null;
    if (!token) {
      throw new Error(`No se pudo iniciar sesión con ${user.email} (HTTP ${res.status}). ¿Contraseñas del .env?`);
    }
    return { token, urls: user.urls };
  });
}

/** Cada iteración = una petición de lectura de alguno de los usuarios (en turno). */
export default function (sessions) {
  const i = exec.scenario.iterationInTest;
  const session = sessions[i % sessions.length];
  const url = session.urls[Math.floor(i / sessions.length) % session.urls.length];

  const res = http.get(`${BASE}${url}`, {
    headers: { Authorization: `Bearer ${session.token}`, Accept: 'application/json' },
    tags: { tipo: 'lectura', name: url.split('?')[0] },
  });

  check(res, {
    'responde 200': (r) => r.status === 200,
    'trae datos': (r) => r.status === 200 && r.json('data') !== undefined,
    'trae Server-Timing': (r) => (r.headers['Server-Timing'] || '').includes('db;dur='),
  });
}
