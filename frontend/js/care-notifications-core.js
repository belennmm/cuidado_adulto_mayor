(() => {
  const prefixes = {
    seen: "care-notifications-seen",
    alarmed: "care-notifications-alarmed",
    snoozed: "care-notifications-snoozed",
  }

  function readSet(type, role, date) {
    try { return new Set(JSON.parse(localStorage.getItem(`${prefixes[type]}:${role}:${date}`)) || []) } catch { return new Set() }
  }
  function writeSet(type, role, date, values) {
    localStorage.setItem(`${prefixes[type]}:${role}:${date}`, JSON.stringify([...values]))
  }
  function readSeen(role, date) { return readSet("seen", role, date) }
  function writeSeen(role, date, values) { writeSet("seen", role, date, values) }
  function readAlarmed(role, date) { return readSet("alarmed", role, date) }
  function writeAlarmed(role, date, values) { writeSet("alarmed", role, date, values) }
  function readSnoozed(role, date) {
    try { return JSON.parse(localStorage.getItem(`${prefixes.snoozed}:${role}:${date}`)) || {} } catch { return {} }
  }
  function writeSnoozed(role, date, values) {
    localStorage.setItem(`${prefixes.snoozed}:${role}:${date}`, JSON.stringify(values))
  }
  function formatTime(value) { return value ? String(value).slice(0, 5) : "sin hora" }
  function medicationLabel(item) {
    return `${item.medication_name || "Medicamento"} para ${item.older_adult_name || "adulto mayor"} (${item.schedule || "sin horario"})`
  }
  function parseScheduleTimes(schedule) {
    return [...String(schedule || "").matchAll(/(\d{1,2}):(\d{2})\s*(AM|PM)?/gi)].map((match) => {
      let hour = Number(match[1])
      const minutes = Number(match[2])
      const period = String(match[3] || "").toUpperCase()
      if (!Number.isInteger(hour) || !Number.isInteger(minutes) || minutes > 59) return null
      if (period === "PM" && hour < 12) hour += 12
      if (period === "AM" && hour === 12) hour = 0
      if (hour > 23) return null
      return { label: `${String(hour).padStart(2, "0")}:${String(minutes).padStart(2, "0")}`, minutes: (hour * 60) + minutes }
    }).filter(Boolean)
  }
  function currentMinutes() {
    const now = new Date()
    return (now.getHours() * 60) + now.getMinutes()
  }
  function collectAlerts(data, routineUrl = "./routine.html") {
    const date = data?.date || new Date().toISOString().slice(0, 10)
    const incidents = (data?.incidents || []).map((incident) => ({
      key: `incident:${incident.id || incident.title || incident.incident_time}`,
      title: incident.severity === "alta" ? "Incidente urgente" : "Nuevo incidente",
      body: `${incident.title || "Incidente"} - ${incident.adult_name || "Adulto mayor"} a las ${formatTime(incident.incident_time)}`,
      url: "./incidents.html",
    }))
    const medications = (data?.next_medications || []).filter((item) => item.due_today !== false && !item.administered_today).flatMap((item) => {
      const times = parseScheduleTimes(item.schedule)
      return (times.length ? times : [{ label: item.schedule || "sin-horario", minutes: null }]).map((time) => ({
        key: `medication:${date}:${item.id || item.medication_name}:${time.label}`,
        alarmKey: `alarm:${date}:${item.id || item.medication_name}:${time.label}`,
        type: "medication", dueMinutes: time.minutes, title: "Medicamento pendiente", body: medicationLabel(item), url: routineUrl,
      }))
    })
    return { date, alerts: [...incidents, ...medications] }
  }

  window.CareNotificationsCore = Object.freeze({ readSeen, writeSeen, readAlarmed, writeAlarmed, readSnoozed, writeSnoozed, parseScheduleTimes, currentMinutes, collectAlerts })
})()
