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

const PLACEHOLDER_VIDEO_ID = "qw0coMbqROo"
const PLACEHOLDER_VIDEO_TITLE = "Estiramientos de miembro superior 1"

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

function createVideoButton(exercise) {
  const button = document.createElement("button")
  button.type = "button"
  button.className = "mobility-video-btn"
  button.setAttribute("aria-label", `Ver video del ejercicio: ${exercise.title}`)
  button.setAttribute("title", "Ver video guiado")
  
  const icon = document.createElement("i")
  icon.className = "bx bx-play"
  icon.setAttribute("aria-hidden", "true")
  
  button.append(icon, document.createTextNode("Ver video"))
  
  button.addEventListener("click", (e) => {
    e.preventDefault()
    openVideoModal(exercise.title)
  })
  
  return button
}

function createExerciseCard(exercise) {
  const card = document.createElement("article")
  card.className = "mobility-card"
  card.setAttribute("aria-labelledby", `mobilityExercise-${exercise.id}`)

  const header = document.createElement("div")
  header.className = "mobility-card-header"
  const title = appendTextElement(header, "h3", "", exercise.title)
  title.id = `mobilityExercise-${exercise.id}`
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

  const videoBtn = createVideoButton(exercise)
  card.append(videoBtn)

  return card
}

function openVideoModal(exerciseTitle) {
  let modal = document.getElementById("videoModal")
  if (!modal) {
    createVideoModal()
    modal = document.getElementById("videoModal")
  }
  
  const titleElement = modal.querySelector(".video-modal-title")
  if (titleElement) {
    titleElement.textContent = exerciseTitle
  }
  
  modal.classList.add("active")
  modal.setAttribute("aria-hidden", "false")
  document.body.style.overflow = "hidden"
}

function closeVideoModal() {
  const modal = document.getElementById("videoModal")
  if (modal) {
    modal.classList.remove("active")
    modal.setAttribute("aria-hidden", "true")
    document.body.style.overflow = ""
    
    const iframe = modal.querySelector("iframe")
    if (iframe) {
      const currentSrc = iframe.src
      iframe.src = ""
      setTimeout(() => {
        iframe.src = currentSrc
      }, 0)
    }
  }
}

function createVideoModal() {
  const modal = document.createElement("div")
  modal.id = "videoModal"
  modal.className = "video-modal-overlay"
  modal.setAttribute("aria-hidden", "true")
  modal.setAttribute("role", "dialog")
  modal.setAttribute("aria-labelledby", "videoModalTitle")
  
  const modalContent = document.createElement("div")
  modalContent.className = "video-modal-content"
  modalContent.setAttribute("role", "document")
  
  const modalHeader = document.createElement("div")
  modalHeader.className = "video-modal-header"
  
  const title = document.createElement("h2")
  title.id = "videoModalTitle"
  title.className = "video-modal-title"
  title.textContent = ""
  
  const closeBtn = document.createElement("button")
  closeBtn.type = "button"
  closeBtn.className = "video-modal-close"
  closeBtn.setAttribute("aria-label", "Cerrar video")
  closeBtn.setAttribute("title", "Cerrar")
  
  const closeIcon = document.createElement("i")
  closeIcon.className = "bx bx-x"
  closeIcon.setAttribute("aria-hidden", "true")
  
  closeBtn.append(closeIcon)
  closeBtn.addEventListener("click", closeVideoModal)
  
  modalHeader.append(title, closeBtn)
  
  const videoContainer = document.createElement("div")
  videoContainer.className = "video-modal-video-container"
  
  const iframe = document.createElement("iframe")
  iframe.width = "560"
  iframe.height = "315"
  iframe.src = `https://www.youtube.com/embed/${PLACEHOLDER_VIDEO_ID}?si=Gq6MbZVyFc6VBSXx`
  iframe.title = PLACEHOLDER_VIDEO_TITLE
  iframe.setAttribute("frameborder", "0")
  iframe.setAttribute("allow", "accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share")
  iframe.setAttribute("referrerpolicy", "strict-origin-when-cross-origin")
  iframe.setAttribute("allowfullscreen", "")
  
  videoContainer.append(iframe)
  
  modalContent.append(modalHeader, videoContainer)
  modal.append(modalContent)
  
  modal.addEventListener("click", (e) => {
    if (e.target === modal) {
      closeVideoModal()
    }
  })
  
  document.body.append(modal)
  
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && modal.classList.contains("active")) {
      closeVideoModal()
    }
  })
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
