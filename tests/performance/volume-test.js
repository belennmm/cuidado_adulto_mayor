import http from 'k6/http';
import { check, sleep } from 'k6';

const apiUrl = __ENV.API_URL || 'http://host.docker.internal:8080/api';
const profile = __ENV.TEST_PROFILE || 'smoke';

const profiles = {
  smoke: { stages: [{ duration: '10s', target: 3 }, { duration: '10s', target: 3 }, { duration: '5s', target: 0 }] },
  load: { stages: [{ duration: '30s', target: 15 }, { duration: '1m', target: 15 }, { duration: '20s', target: 0 }] },
  stress: { stages: [{ duration: '30s', target: 20 }, { duration: '30s', target: 40 }, { duration: '1m', target: 40 }, { duration: '30s', target: 0 }] },
  volume100: { stages: [{ duration: '30s', target: 1 }, { duration: '60s', target: 1 }, { duration: '30s', target: 0 }] },
};

if (!profiles[profile]) {
  throw new Error(`Perfil desconocido: ${profile}. Use smoke, load, stress o volume100.`);
}

const volume100Targets = { administrator: 34, professional: 33, family: 33 };

const scenario = (exec, role, startTime = '0s') => ({
  executor: 'ramping-vus',
  exec,
  startTime,
  gracefulRampDown: '5s',
  stages: profile === 'volume100'
    ? profiles[profile].stages.map((stage) => ({ ...stage, target: stage.target === 0 ? 0 : volume100Targets[role] }))
    : profiles[profile].stages,
});

export const options = {
  setupTimeout: '3m',
  scenarios: {
    administrator: scenario('administratorTraffic', 'administrator'),
    professional: scenario('professionalTraffic', 'professional'),
    family: scenario('familyTraffic', 'family'),
  },
  thresholds: {
    http_req_failed: ['rate<0.01'],
    http_req_duration: ['p(95)<1000', 'p(99)<2000'],
    checks: ['rate>0.99'],
  },
};

const passwords = {
  administrator: __ENV.ADMIN_PASSWORD,
  professional: __ENV.PROFESSIONAL_PASSWORD,
  family: __ENV.FAMILY_PASSWORD,
};

if (Object.values(passwords).some(password => !password)) throw new Error('Configure ADMIN_PASSWORD, PROFESSIONAL_PASSWORD y FAMILY_PASSWORD.');

const account = (role, index) => ({
  email: `perf-${role}-${String(index).padStart(3, '0')}@performance.test`,
  password: passwords[role],
});

function login(account) {
  const response = http.post(`${apiUrl}/login`, JSON.stringify(account), {
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    tags: { endpoint: 'login' },
  });

  const token = response.status === 200 ? response.json('token') : null;
  const valid = check(response, {
    'login responds 200': (res) => res.status === 200,
    'login returns token': () => Boolean(token),
  });

  if (!valid) {
    throw new Error(`No fue posible iniciar sesion para ${account.email}: HTTP ${response.status}`);
  }

  return token;
}

const maximumGlobalVus = profile === 'volume100'
  ? 100
  : Math.max(...profiles[profile].stages.map((stage) => stage.target)) * 3;

export function setup() {
  return Object.fromEntries(Object.keys(passwords).map((role) => [
    role,
    Array.from({ length: maximumGlobalVus }, (_, index) => login(account(role, index + 1))),
  ]));
}

function tokenFor(tokens) {
  return tokens[__VU - 1];
}

function authenticatedGet(path, token, endpoint) {
  const response = http.get(`${apiUrl}${path}`, {
    headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
    tags: { endpoint },
  });

  check(response, {
    [`${endpoint} responds 200`]: (res) => res.status === 200,
    [`${endpoint} returns JSON`]: (res) => (res.headers['Content-Type'] || '').includes('application/json'),
  });
}

export function administratorTraffic(data) {
  const token = tokenFor(data.administrator);
  authenticatedGet('/admin/dashboard-summary', token, 'admin_dashboard');
  authenticatedGet('/admin/users', token, 'admin_users');
  authenticatedGet('/admin/older-adults', token, 'admin_older_adults');
  sleep(1);
}

export function professionalTraffic(data) {
  const token = tokenFor(data.professional);
  authenticatedGet('/professional/overview', token, 'professional_overview');
  authenticatedGet('/professional/older-adults', token, 'professional_older_adults');
  authenticatedGet('/professional/reminders', token, 'professional_reminders');
  sleep(1);
}

export function familyTraffic(data) {
  const token = tokenFor(data.family);
  authenticatedGet('/family/overview', token, 'family_overview');
  authenticatedGet('/family/older-adults', token, 'family_older_adults');
  authenticatedGet('/family/incidents', token, 'family_incidents');
  sleep(1);
}
