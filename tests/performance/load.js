import { thresholds } from "./config.js"
import { authenticateUsers, runMixedJourney } from "./common/journeys.js"

export const options = {
  stages: [
    { duration: "1m", target: 5 },
    { duration: "2m", target: 20 },
    { duration: "2m", target: 20 },
    { duration: "1m", target: 0 },
  ],
  thresholds,
  tags: { test_type: "load" },
}

export const setup = authenticateUsers

export default runMixedJourney
