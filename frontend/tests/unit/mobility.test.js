import { describe, expect, it } from "vitest"
import { getMobilityExercises, localMobilityExercises, renderMobilityExercises } from "../../js/mobility.js"

describe("ejercicios de movilidad", () => {
  it("expone ejercicios locales con la información necesaria para una tarjeta", async () => {
    const exercises = await getMobilityExercises()

    expect(exercises).toHaveLength(localMobilityExercises.length)
    expect(exercises[0]).toMatchObject({
      id: expect.any(String),
      nombre: expect.any(String),
      comorealizarlo: expect.any(Array),
      recomendacion: expect.any(String),
    })
    expect(exercises[0]).not.toBe(localMobilityExercises[0])
    expect(exercises[0].comorealizarlo).not.toBe(localMobilityExercises[0].comorealizarlo)
  })

  it("genera una tarjeta por cada ejercicio", () => {
    const container = document.createElement("div")

    const exercises = localMobilityExercises.slice(0, 2)
    const instructionCount = exercises.reduce(
      (total, exercise) => total + exercise.comorealizarlo.length,
      0,
    )

    renderMobilityExercises(container, exercises)

    expect(container.querySelectorAll(".mobility-card")).toHaveLength(2)
    expect(container.querySelector("h3").textContent).toBe(exercises[0].nombre)
    expect(container.querySelectorAll(".mobility-instructions li")).toHaveLength(instructionCount)
  })
})
