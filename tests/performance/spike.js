import { thresholds } from "./config.js"
import { authenticateUsers, runMixedJourney } from "./common/journeys.js"

export const options = {
  stages: [
    { duration: "30s", target: 5 },
    { duration: "10s", target: 100 },
    { duration: "1m", target: 100 },
    { duration: "10s", target: 5 },
    { duration: "1m", target: 5 },
    { duration: "20s", target: 0 },
  ],
  thresholds,
  tags: { test_type: "spike" },
}

export const setup = authenticateUsers

export default runMixedJourney
