const numberFromEnv = (name, fallback) => {
  const value = Number(__ENV[name])
  return Number.isFinite(value) && value > 0 ? value : fallback
}

export const config = {
  baseUrl: (__ENV.BASE_URL || "http://host.docker.internal:8080/api").replace(/\/$/, ""),
  thinkTime: numberFromEnv("THINK_TIME", 1),
  credentials: {
    admin: {
      email: __ENV.ADMIN_EMAIL || "admin@performance.test",
      password: __ENV.ADMIN_PASSWORD || "Performance123!",
    },
    professional: {
      email: __ENV.PROFESSIONAL_EMAIL || "professional@performance.test",
      password: __ENV.PROFESSIONAL_PASSWORD || "Performance123!",
    },
    family: {
      email: __ENV.FAMILY_EMAIL || "family@performance.test",
      password: __ENV.FAMILY_PASSWORD || "Performance123!",
    },
  },
}

export const thresholds = {
  http_req_failed: ["rate<0.01"],
  http_req_duration: ["p(95)<800", "p(99)<1500"],
  checks: ["rate>0.99"],
}
