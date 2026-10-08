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

const accounts = {
  administrator: {
    email: __ENV.ADMIN_EMAIL || 'zap.admin@example.test',
    password: __ENV.ADMIN_PASSWORD || 'ZapAdmin-2026!',
  },
  professional: {
    email: __ENV.PROFESSIONAL_EMAIL || 'zap.professional@example.test',
    password: __ENV.PROFESSIONAL_PASSWORD || 'ZapPro-2026!',
  },
  family: {
    email: __ENV.FAMILY_EMAIL || 'zap.family@example.test',
    password: __ENV.FAMILY_PASSWORD || 'ZapFamily-2026!',
  },
};

function login(account) {
  const response = http.post(`${apiUrl}/login`, JSON.stringify(account), {
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    tags: { endpoint: 'login' },
  });

  const valid = check(response, {
    'login responds 200': (res) => res.status === 200,
    'login returns token': (res) => Boolean(res.json('token')),
  });

  if (!valid) {
    throw new Error(`No fue posible iniciar sesion para ${account.email}: HTTP ${response.status}`);
  }

  return response.json('token');
}

export function setup() {
  return {
    administrator: login(accounts.administrator),
    professional: login(accounts.professional),
    family: login(accounts.family),
  };
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
  authenticatedGet('/admin/dashboard-summary', data.administrator, 'admin_dashboard');
  authenticatedGet('/admin/users', data.administrator, 'admin_users');
  authenticatedGet('/admin/older-adults', data.administrator, 'admin_older_adults');
  sleep(1);
}

export function professionalTraffic(data) {
  authenticatedGet('/professional/overview', data.professional, 'professional_overview');
  authenticatedGet('/professional/older-adults', data.professional, 'professional_older_adults');
  authenticatedGet('/professional/reminders', data.professional, 'professional_reminders');
  sleep(1);
}

export function familyTraffic(data) {
  authenticatedGet('/family/overview', data.family, 'family_overview');
  authenticatedGet('/family/older-adults', data.family, 'family_older_adults');
  authenticatedGet('/family/incidents', data.family, 'family_incidents');
  sleep(1);
}
