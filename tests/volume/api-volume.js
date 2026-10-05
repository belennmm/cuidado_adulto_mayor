import http from 'k6/http';
import { check, sleep } from 'k6';

const BASE_URL = (__ENV.BASE_URL || 'http://host.docker.internal:8080/api').replace(/\/$/, '');
const EMAIL = __ENV.K6_EMAIL || 'zap.admin@example.test';
const PASSWORD = __ENV.K6_PASSWORD || 'ZapAdmin-2026!';

export const options = {
  stages: [
    { duration: '30s', target: 10 },
    { duration: '60s', target: 25 },
    { duration: '60s', target: 50 },
    { duration: '30s', target: 0 },
  ],
  thresholds: {
    http_req_failed: ['rate<0.01'],
    http_req_duration: ['p(95)<1000', 'p(99)<2000'],
    checks: ['rate>0.99'],
  },
};

export function setup() {
  const response = http.post(`${BASE_URL}/login`, JSON.stringify({ email: EMAIL, password: PASSWORD }), {
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
  });

  check(response, { 'login de carga responde 200': (r) => r.status === 200 });
  if (response.status !== 200) {
    throw new Error(`No se pudo obtener token para la prueba de volumen: HTTP ${response.status}`);
  }

  return { token: response.json('token') };
}

export default function (data) {
  const headers = {
    Authorization: `Bearer ${data.token}`,
    Accept: 'application/json',
  };
  const endpoint = Math.random() < 0.7 ? '/me' : '/mobility-exercises';
  const response = http.get(`${BASE_URL}${endpoint}`, { headers, tags: { endpoint } });

  check(response, {
    [`GET ${endpoint} responde 2xx`]: (r) => r.status >= 200 && r.status < 300,
    'respuesta JSON': (r) => (r.headers['Content-Type'] || '').includes('application/json'),
  });
  sleep(0.2);
}
