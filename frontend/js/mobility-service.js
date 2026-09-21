export const localMobilityExercises = Object.freeze([
  {
    id: "estiramiento-superior-1",
    nombre: "Estiramientos de miembro superior 1",
    area: "Hombros y brazos",
    tipo: "Estiramiento guiado",
    comorealizarlo: [
      "De pie o sentado, levanta el brazo derecho hacia el lado.",
      "Coloca la mano izquierda sobre el codo derecho.",
      "Presiona suavemente el codo hacia el lado izquierdo, sintiendo el estiramiento en el hombro.",
      "Mantén la posición durante 20-30 segundos.",
      "Relaja y repite del otro lado."
    ],
    recomendacion: "Realiza este ejercicio 2-3 veces al día. Evita rebotes bruscos y estira solo hasta sentir una leve molestia, nunca dolor.",
    fuente: "Hospital Universitario de Fuenlabrada",
    videoId: "qw0coMbqROo"
  },
  {
    id: "cervical-activa-1",
    nombre: "Ejercicios activos de columna cervical 1",
    area: "Cuello",
    tipo: "Movilidad guiada",
    comorealizarlo: [
      "De pie o sentado con la espalda recta.",
      "Lentamente gira la cabeza hacia el lado derecho, intentando llevar la barbilla hacia el hombro.",
      "Mantén la posición 15-20 segundos.",
      "Lentamente gira la cabeza hacia el lado izquierdo.",
      "Mantén la posición 15-20 segundos.",
      "Repite 5 veces de cada lado."
    ],
    recomendacion: "Realiza este movimiento suavemente sin forzar. Si sientes mareos, detente inmediatamente y consulta con un profesional.",
    fuente: "Hospital Universitario de Fuenlabrada",
    videoId: "0bWCkSuNmac"
  },
  {
    id: "cervical-activa-2",
    nombre: "Ejercicios activos de columna cervical 2",
    area: "Cuello y columna",
    tipo: "Movilidad guiada",
    comorealizarlo: [
      "De pie o sentado con la espalda recta.",
      "Lentamente inclina la cabeza hacia adelante, llevando la barbilla hacia el pecho.",
      "Mantén la posición 15-20 segundos.",
      "Lentamente levanta la cabeza a la posición inicial.",
      "Inclina la cabeza hacia atrás suavemente, mirando hacia arriba.",
      "Mantén 15-20 segundos y regresa a la posición inicial.",
      "Repite 5 veces de cada movimiento."
    ],
    recomendacion: "Evita movimientos bruscos. Este ejercicio ayuda a mejorar la flexibilidad del cuello. Respira lentamente durante toda la ejecución.",
    fuente: "Hospital Universitario de Fuenlabrada",
    videoId: "i43K-q15d5k"
  },
  {
    id: "estiramiento-inferior-1",
    nombre: "Estiramientos de miembro inferior 1",
    area: "Piernas",
    tipo: "Estiramiento guiado",
    comorealizarlo: [
      "Sentado en una silla, extiende la pierna derecha hacia adelante.",
      "Inclina el tronco hacia adelante desde las caderas, manteniendo la espalda recta.",
      "Siente el estiramiento en la parte posterior del muslo.",
      "Mantén la posición 20-30 segundos.",
      "Relaja y repite con la pierna izquierda.",
      "Realiza 3 series de cada lado."
    ],
    recomendacion: "Este estiramiento es ideal para mejorar la flexibilidad de las piernas. No hagas rebotes y estira solo hasta sentir una leve molestia.",
    fuente: "Hospital Universitario de Fuenlabrada",
    videoId: "ElaXEroNFw4"
  },
])

export async function getMobilityExercises() {
  return localMobilityExercises.map((exercise) => ({
    ...exercise,
    comorealizarlo: [...exercise.comorealizarlo],
  }))
}

