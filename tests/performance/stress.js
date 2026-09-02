import { thresholds } from "./config.js"
import { authenticateUsers, runMixedJourney } from "./common/journeys.js"

export const options = {
  stages: [
    { duration: "1m", target: 20 },
    { duration: "2m", target: 50 },
    { duration: "2m", target: 100 },
    { duration: "2m", target: 150 },
    { duration: "1m", target: 150 },
    { duration: "2m", target: 0 },
  ],
  thresholds,
  tags: { test_type: "stress" },
}

export const setup = authenticateUsers

export default runMixedJourney
