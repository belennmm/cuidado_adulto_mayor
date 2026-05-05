(() => {
  const api = window.ProfessionalCare
  const days = {
    0: "Domingo",
    1: "Lunes",
    2: "Martes",
    3: "Miercoles",
    4: "Jueves",
    5: "Viernes",
    6: "Sabado",
  }

  function renderSchedule(schedule) {
    return `
      <article class="professional-row">
        <span class="row-icon"><i class="bx bxs-time"></i></span>
        <div>
          <h3>${api.escapeHtml(days[schedule.day_of_week] || "Dia")}</h3>
          <p>${api.escapeHtml(api.formatTime(schedule.start_time))} - ${api.escapeHtml(api.formatTime(schedule.end_time))}</p>
        </div>
        <span class="badge badge-blue">${api.escapeHtml(schedule.notes || "Asignado")}</span>
      </article>
    `
  }

  async function loadSchedules() {
    const list = document.getElementById("professionalSchedulesList")
    if (!list) return

    try {
      const data = await api.fetchJson("/professional/schedules")
      list.innerHTML = data.schedules?.length
        ? data.schedules.map(renderSchedule).join("")
        : api.renderEmpty("No tienes turnos asignados por ahora.")
    } catch (error) {
      list.innerHTML = api.renderEmpty(error.message)
    }
  }

  document.addEventListener("DOMContentLoaded", loadSchedules)
})()
