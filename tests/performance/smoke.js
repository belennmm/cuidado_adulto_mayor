import { thresholds } from "./config.js"
import { authenticateUsers, runSmokeJourney } from "./common/journeys.js"

export const options = {
  vus: 1,
  iterations: 3,
  thresholds,
  tags: { test_type: "smoke" },
}

export const setup = authenticateUsers

export default runSmokeJourney
