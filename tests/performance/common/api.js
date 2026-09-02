import http from "k6/http"
import { check, fail } from "k6"

import { config } from "../config.js"

const jsonHeaders = {
  Accept: "application/json",
  "Content-Type": "application/json",
}

export function login(role, credentials) {
  const response = http.post(
    `${config.baseUrl}/login`,
    JSON.stringify(credentials),
    {
      headers: jsonHeaders,
      tags: { endpoint: "login", role },
    },
  )

  const valid = check(response, {
    [`${role}: login responde 200`]: (res) => res.status === 200,
    [`${role}: login devuelve token`]: (res) => Boolean(res.json("token")),
  })

  if (!valid) {
    fail(`No fue posible autenticar el rol ${role}. Estado HTTP: ${response.status}`)
  }

  return response.json("token")
}

export function authenticatedGet(path, token, name, role) {
  const response = http.get(`${config.baseUrl}${path}`, {
    headers: {
      Accept: "application/json",
      Authorization: `Bearer ${token}`,
    },
    tags: { endpoint: name, role },
  })

  check(response, {
    [`${role}: ${name} responde 200`]: (res) => res.status === 200,
    [`${role}: ${name} devuelve JSON`]: (res) =>
      String(res.headers["Content-Type"] || "").includes("application/json"),
  })

  return response
}
