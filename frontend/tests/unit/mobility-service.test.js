import { describe, expect, it } from "vitest"
import { getMobilityExercises, localMobilityExercises } from "../../js/mobility-service.js"

describe("MobilityService", () => {
  it("entrega una copia independiente del catálogo local", async () => {
    const first = await getMobilityExercises()
    const second = await getMobilityExercises()

    expect(first).toHaveLength(localMobilityExercises.length)
    expect(first).not.toBe(localMobilityExercises)
    expect(first[0]).not.toBe(second[0])
    expect(first[0].comorealizarlo).not.toBe(second[0].comorealizarlo)
  })

  it("mantiene la información necesaria para la vista", async () => {
    const exercises = await getMobilityExercises()
    expect(exercises[0]).toEqual(expect.objectContaining({
      id: expect.any(String),
      nombre: expect.any(String),
      area: expect.any(String),
      tipo: expect.any(String),
      videoId: expect.any(String),
    }))
  })
})
