import { getMobilityExercises, localMobilityExercises } from "./mobility-service.js"

export { getMobilityExercises, localMobilityExercises }

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
  button.setAttribute("aria-label", `Ver video del ejercicio: ${exercise.nombre}`)
  button.setAttribute("title", "Ver video guiado")
  
  const icon = document.createElement("i")
  icon.className = "bx bx-play"
  icon.setAttribute("aria-hidden", "true")
  
  button.append(icon, document.createTextNode("Ver video"))
  
  button.addEventListener("click", (e) => {
    e.preventDefault()
    openVideoModal(exercise)
  })
  
  return button
}

function createExerciseCard(exercise) {
  const card = document.createElement("article")
  card.className = "mobility-card"
  card.setAttribute("aria-labelledby", `mobilityExercise-${exercise.id}`)

  // Título
  const title = appendTextElement(card, "h3", "", exercise.nombre)
  title.id = `mobilityExercise-${exercise.id}`

  // Área y Tipo en bloques separados (similar a mobility-details)
  const infoGrid = document.createElement("div")
  infoGrid.className = "mobility-details"
  
  const areaBlock = document.createElement("div")
  appendTextElement(areaBlock, "dt", "", "Área")
  appendTextElement(areaBlock, "dd", "", exercise.area)
  infoGrid.append(areaBlock)
  
  const tipoBlock = document.createElement("div")
  appendTextElement(tipoBlock, "dt", "", "Tipo")
  appendTextElement(tipoBlock, "dd", "", exercise.tipo)
  infoGrid.append(tipoBlock)
  
  card.append(infoGrid)

  // Cómo realizarlo
  appendTextElement(card, "h4", "mobility-instructions-title", "Cómo realizarlo")
  const instructions = document.createElement("ol")
  instructions.className = "mobility-instructions"
  exercise.comorealizarlo.forEach((instruction) => appendTextElement(instructions, "li", "", instruction))
  card.append(instructions)

  // Recomendación con icono (similar a mobility-precaution)
  const recommendation = document.createElement("p")
  recommendation.className = "mobility-precaution"
  const icon = document.createElement("i")
  icon.className = "bx bx-info-circle"
  icon.setAttribute("aria-hidden", "true")
  recommendation.append(icon, document.createTextNode(exercise.recomendacion))
  card.append(recommendation)

  // Fuente discreta
  const source = document.createElement("p")
  source.className = "mobility-source"
  source.textContent = exercise.fuente
  card.append(source)

  // Botón de video
  const videoBtn = createVideoButton(exercise)
  card.append(videoBtn)

  return card
}

function openVideoModal(exercise) {
  let modal = document.getElementById("videoModal")
  if (!modal) {
    createVideoModal()
    modal = document.getElementById("videoModal")
  }
  
  const titleElement = modal.querySelector(".video-modal-title")
  if (titleElement) {
    titleElement.textContent = exercise.nombre
  }
  
  const iframe = modal.querySelector("iframe")
  if (iframe) {
    iframe.src = `https://www.youtube.com/embed/${exercise.videoId}?si=Gq6MbZVyFc6VBSXx`
    iframe.title = `Video: ${exercise.nombre}`
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
      iframe.src = ""
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
  iframe.src = ""
  iframe.title = ""
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
