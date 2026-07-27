export const localMobilityExercises = Object.freeze([
  {
    id: "cuello-suave",
    title: "Movilidad suave de cuello",
    focus: "Cuello y hombros",
    duration: "2 minutos",
    repetitions: "5 repeticiones por lado",
    instructions: [
      "Siéntate con la espalda apoyada y los pies firmes en el suelo.",
      "Gira lentamente la cabeza hacia un lado sin forzar el movimiento.",
      "Vuelve al centro y repite hacia el otro lado.",
    ],
    precaution: "Suspende el ejercicio si aparece dolor, mareo o molestia.",
  },
  {
    id: "hombros-circulos",
    title: "Círculos de hombros",
    focus: "Hombros y parte superior de la espalda",
    duration: "3 minutos",
    repetitions: "8 círculos hacia cada dirección",
    instructions: [
      "Mantén una postura cómoda, sentado o de pie con apoyo cercano.",
      "Eleva los hombros y llévalos suavemente hacia atrás y abajo.",
      "Cambia de dirección después de completar las repeticiones.",
    ],
    precaution: "Realiza movimientos lentos y sin elevar los hombros con tensión.",
  },
  {
    id: "tobillos-flexion",
    title: "Flexión de tobillos",
    focus: "Tobillos y piernas",
    duration: "3 minutos",
    repetitions: "10 repeticiones por pie",
    instructions: [
      "Siéntate en una silla estable con los pies apoyados.",
      "Eleva la punta de un pie manteniendo el talón en el suelo.",
      "Luego eleva el talón y repite alternando ambos pies.",
    ],
    precaution: "Usa calzado antideslizante y mantén la silla sobre una superficie firme.",
  },
  {
    id: "marcha-sentada",
    title: "Marcha sentada",
    focus: "Caderas y piernas",
    duration: "3 minutos",
    repetitions: "10 elevaciones por pierna",
    instructions: [
      "Siéntate hacia el fondo de una silla estable.",
      "Eleva una rodilla de forma controlada y bájala lentamente.",
      "Alterna las piernas manteniendo el abdomen relajado.",
    ],
    precaution: "Cuenta con supervisión si existe riesgo de pérdida de equilibrio.",
  },
])

/**
 * Punto de integración futuro: sustituir los datos locales por una llamada a la API.
 */
export async function getMobilityExercises() {
  return localMobilityExercises.map((exercise) => ({
    ...exercise,
    instructions: [...exercise.instructions],
  }))
}

function appendTextElement(parent, tagName, className, text) {
  const element = document.createElement(tagName)
  if (className) element.className = className
  element.textContent = text
  parent.append(element)
  return element
}

function createExerciseCard(exercise) {
  const card = document.createElement("article")
  card.className = "mobility-card"

  const header = document.createElement("div")
  header.className = "mobility-card-header"
  appendTextElement(header, "h3", "", exercise.title)
  appendTextElement(header, "span", "mobility-focus", exercise.focus)
  card.append(header)

  const details = document.createElement("dl")
  details.className = "mobility-details"
  ;[
    ["Duración", exercise.duration],
    ["Repeticiones", exercise.repetitions],
  ].forEach(([label, value]) => {
    const detail = document.createElement("div")
    appendTextElement(detail, "dt", "", label)
    appendTextElement(detail, "dd", "", value)
    details.append(detail)
  })
  card.append(details)

  appendTextElement(card, "h4", "mobility-instructions-title", "Cómo realizarlo")
  const instructions = document.createElement("ol")
  instructions.className = "mobility-instructions"
  exercise.instructions.forEach((instruction) => appendTextElement(instructions, "li", "", instruction))
  card.append(instructions)

  const precaution = document.createElement("p")
  precaution.className = "mobility-precaution"
  const icon = document.createElement("i")
  icon.className = "bx bx-info-circle"
  icon.setAttribute("aria-hidden", "true")
  precaution.append(icon, document.createTextNode(exercise.precaution))
  card.append(precaution)

  return card
}

export function renderMobilityExercises(container, exercises) {
  if (!container) return

  const fragment = document.createDocumentFragment()
  exercises.forEach((exercise) => fragment.append(createExerciseCard(exercise)))
  container.replaceChildren(fragment)
}

export async function initializeMobilityPage() {
  const container = document.getElementById("mobilityExercises")
  if (!container) return

  try {
    renderMobilityExercises(container, await getMobilityExercises())
  } catch {
    container.replaceChildren()
    appendTextElement(container, "p", "mobility-load-error", "No se pudieron cargar los ejercicios de movilidad.")
  }
}

if (typeof document !== "undefined") {
  document.addEventListener("DOMContentLoaded", initializeMobilityPage)
}
