import { existsSync } from "node:fs"
import { dirname, resolve } from "node:path"
import { spawnSync } from "node:child_process"
import { fileURLToPath } from "node:url"

const frontendRoot = resolve(dirname(fileURLToPath(import.meta.url)), "..")

// Existing files grouped by the quality attributes they verify. A file may
// cover both attributes; the aggregate runs each file only once.
const groups = {
  security: [
    "tests/unit/api-config.test.js",
    "tests/unit/auth-session.test.js",
    "tests/unit/browser-api.test.js",
    "tests/unit/safe-output-context.test.js",
    "tests/unit/url-and-injection-security.test.js",
    "tests/dom/account-mfa.test.js",
    "tests/dom/account-sessions.test.js",
    "tests/dom/protected-page-auth.test.js",
    "tests/dom/family-auth-guard.test.js",
    "tests/dom/safe-admin-lists.test.js",
  ],
  reliability: [
    "tests/unit/api-config.test.js",
    "tests/dom/form-session-expiration.test.js",
    "tests/dom/navigation-links.test.js",
    "tests/dom/admin-user-api-error.test.js",
    "tests/dom/admin-shifts-api-error.test.js",
    "tests/dom/older-adult-create-error.test.js",
    "tests/dom/family-dashboard-api-error.test.js",
    "tests/dom/list-pagination.test.js",
  ],
}

const category = process.argv[2]
const selected = category === "non-functional"
  ? [...new Set([...groups.security, ...groups.reliability])]
  : groups[category]

if (!Array.isArray(selected)) {
  console.error("Categoría no válida. Usa non-functional, security o reliability.")
  process.exit(1)
}

for (const file of selected) {
  if (!existsSync(resolve(frontendRoot, file))) {
    console.error(`Falta una prueba de la categoría ${category}: ${file}`)
    process.exit(1)
  }
}

console.log(`Frontend — ${category}: ${selected.length} archivos; sin backend, escáneres externos ni rendimiento.`)
const result = spawnSync(process.execPath, [
  resolve(frontendRoot, "node_modules/vitest/vitest.mjs"), "run", ...selected, ...process.argv.slice(3),
], { cwd: frontendRoot, stdio: "inherit" })

if (result.error) console.error(result.error.message)
if (result.signal) console.error(`Vitest interrumpido: ${result.signal}`)
process.exit(result.status ?? 1)
