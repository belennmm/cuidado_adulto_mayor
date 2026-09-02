import exec from "k6/execution"
import { sleep } from "k6"

import { config } from "../config.js"
import { authenticatedGet, login } from "./api.js"

export function authenticateUsers() {
  return {
    admin: login("admin", config.credentials.admin),
    professional: login("professional", config.credentials.professional),
    family: login("family", config.credentials.family),
  }
}

function adminJourney(token) {
  authenticatedGet("/admin/dashboard-summary", token, "admin_dashboard", "admin")
  sleep(config.thinkTime)
  authenticatedGet("/admin/medication-statistics?filter=month", token, "medication_statistics", "admin")
  sleep(config.thinkTime)
  authenticatedGet("/admin/schedules/calendar?start_date=2026-09-01&end_date=2026-09-30", token, "schedule_calendar", "admin")
}

function professionalJourney(token) {
  authenticatedGet("/professional/overview", token, "professional_overview", "professional")
  sleep(config.thinkTime)
  authenticatedGet("/professional/older-adults", token, "professional_older_adults", "professional")
  sleep(config.thinkTime)
  authenticatedGet("/professional/routines", token, "professional_routines", "professional")
}

function familyJourney(token) {
  authenticatedGet("/family/overview", token, "family_overview", "family")
  sleep(config.thinkTime)
  authenticatedGet("/family/older-adults", token, "family_older_adults", "family")
  sleep(config.thinkTime)
  authenticatedGet("/family/incidents", token, "family_incidents", "family")
}

export function runMixedJourney(tokens) {
  const roleIndex = exec.vu.idInTest % 10

  // Mezcla aproximada: 20% administradores, 50% profesionales, 30% familiares.
  if (roleIndex < 2) {
    adminJourney(tokens.admin)
  } else if (roleIndex < 7) {
    professionalJourney(tokens.professional)
  } else {
    familyJourney(tokens.family)
  }

  sleep(config.thinkTime)
}

export function runSmokeJourney(tokens) {
  const roleIndex = exec.scenario.iterationInTest % 3

  if (roleIndex === 0) {
    adminJourney(tokens.admin)
  } else if (roleIndex === 1) {
    professionalJourney(tokens.professional)
  } else {
    familyJourney(tokens.family)
  }
}
