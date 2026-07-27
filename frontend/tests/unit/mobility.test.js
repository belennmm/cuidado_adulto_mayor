import { describe, expect, it } from "vitest"
import { getMobilityExercises, localMobilityExercises, renderMobilityExercises } from "../../js/mobility.js"

describe("ejercicios de movilidad", () => {
  it("expone ejercicios locales con la información necesaria para una tarjeta", async () => {
    const exercises = await getMobilityExercises()

    expect(exercises).toHaveLength(localMobilityExercises.length)
    expect(exercises[0]).toMatchObject({
      id: expect.any(String),
      title: expect.any(String),
      instructions: expect.any(Array),
      precaution: expect.any(String),
    })
    expect(exercises[0]).not.toBe(localMobilityExercises[0])
  })

  it("genera una tarjeta por cada ejercicio", () => {
    const container = document.createElement("div")

    renderMobilityExercises(container, localMobilityExercises.slice(0, 2))

    expect(container.querySelectorAll(".mobility-card")).toHaveLength(2)
    expect(container.querySelector("h3").textContent).toBe("Movilidad suave de cuello")
    expect(container.querySelectorAll(".mobility-instructions li")).toHaveLength(6)
  })
})
