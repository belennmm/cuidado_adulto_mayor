(() => {
  const POLL_INTERVAL = 60000
  const storagePrefix = "care-notifications-seen"
  const soundStorageKey = "care-notifications-sound"
  const instances = new Map()
  let audioContext = null

  function supportsNotifications() {
    return "Notification" in window
  }

  function readSeen(role, date) {
    try {
      return new Set(JSON.parse(localStorage.getItem(`${storagePrefix}:${role}:${date}`)) || [])
    } catch {
      return new Set()
    }
  }

  function writeSeen(role, date, seen) {
    localStorage.setItem(`${storagePrefix}:${role}:${date}`, JSON.stringify([...seen]))
  }

  function formatTime(value) {
    if (!value) return "sin hora"
    return String(value).slice(0, 5)
  }

  function escapeHtml(value) {
    return String(value ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;")
  }

  function medicationLabel(medication) {
    const name = medication.medication_name || "Medicamento"
    const adult = medication.older_adult_name || "adulto mayor"
    const schedule = medication.schedule || "sin horario"
    return `${name} para ${adult} (${schedule})`
  }

  function collectAlerts(data, routineUrl = "./routine.html") {
    const date = data?.date || new Date().toISOString().slice(0, 10)
    const incidents = (data?.incidents || []).map((incident) => ({
      key: `incident:${incident.id || incident.title || incident.incident_time}`,
      title: incident.severity === "alta" ? "Incidente urgente" : "Nuevo incidente",
      body: `${incident.title || "Incidente"} - ${incident.adult_name || "Adulto mayor"} a las ${formatTime(incident.incident_time)}`,
      url: "./incidents.html",
    }))

    const medications = (data?.next_medications || [])
      .filter((medication) => medication.due_today !== false && !medication.administered_today)
      .map((medication) => ({
        key: `medication:${date}:${medication.id || medication.medication_name}:${medication.schedule || "sin-horario"}`,
        title: "Medicamento pendiente",
        body: medicationLabel(medication),
        url: routineUrl,
      }))

    return { date, alerts: [...incidents, ...medications] }
  }

  function notify(alert) {
    if (!supportsNotifications() || Notification.permission !== "granted") return

    const notification = new Notification(alert.title, {
      body: alert.body,
      tag: alert.key,
      renotify: false,
    })

    notification.onclick = () => {
      window.focus()
      if (alert.url) window.location.href = alert.url
      notification.close()
    }
  }

  function isSoundEnabled() {
    return localStorage.getItem(soundStorageKey) !== "muted"
  }

  function setSoundEnabled(enabled) {
    localStorage.setItem(soundStorageKey, enabled ? "enabled" : "muted")
  }

  function playAlertSound() {
    if (!isSoundEnabled()) return

    const AudioCtor = window.AudioContext || window.webkitAudioContext
    if (!AudioCtor) return

    try {
      audioContext = audioContext || new AudioCtor()
      if (audioContext.state === "suspended") audioContext.resume()
      const oscillator = audioContext.createOscillator()
      const gain = audioContext.createGain()
      const now = audioContext.currentTime

      oscillator.type = "sine"
      oscillator.frequency.setValueAtTime(880, now)
      oscillator.frequency.setValueAtTime(660, now + 0.12)
      gain.gain.setValueAtTime(0.0001, now)
      gain.gain.exponentialRampToValueAtTime(0.18, now + 0.02)
      gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.32)

      oscillator.connect(gain)
      gain.connect(audioContext.destination)
      oscillator.start(now)
      oscillator.stop(now + 0.34)
    } catch {
      // Some browsers block audio until the user interacts with the page.
    }
  }

  function unlockAudio() {
    if (!isSoundEnabled()) return

    const AudioCtor = window.AudioContext || window.webkitAudioContext
    if (!AudioCtor) return

    try {
      audioContext = audioContext || new AudioCtor()
      if (audioContext.state === "suspended") audioContext.resume()
    } catch {
      // Audio stays optional; the visual alert still works.
    }
  }

  function showToast(alerts) {
    if (!alerts.length) return

    const existing = document.querySelector(".care-toast")
    if (existing) existing.remove()

    const toast = document.createElement("div")
    toast.className = "care-toast"
    toast.innerHTML = `
      <i class="bx bxs-bell-ring"></i>
      <div>
        <strong>${escapeHtml(alerts[0].title)}</strong>
        <span>${escapeHtml(alerts.length > 1 ? `${alerts.length} alertas nuevas` : alerts[0].body)}</span>
      </div>
    `

    document.body.appendChild(toast)
    window.setTimeout(() => toast.remove(), 6500)
  }

  function renderCenter(instance, alerts) {
    if (!instance.center) return

    const badge = instance.center.querySelector(".care-bell-badge")
    const list = instance.center.querySelector(".care-notification-list")
    const empty = instance.center.querySelector(".care-notification-empty")
    const soundButton = instance.center.querySelector(".care-sound-toggle")

    if (badge) {
      badge.textContent = String(alerts.length)
      badge.hidden = alerts.length === 0
    }

    if (soundButton) {
      soundButton.innerHTML = isSoundEnabled()
        ? '<i class="bx bxs-volume-full"></i><span>Sonido activo</span>'
        : '<i class="bx bxs-volume-mute"></i><span>Silenciado</span>'
    }

    if (empty) empty.hidden = alerts.length > 0
    if (!list) return

    list.innerHTML = alerts
      .map((alert) => `
        <a class="care-notification-item" href="${escapeHtml(alert.url || "#")}">
          <strong>${escapeHtml(alert.title)}</strong>
          <span>${escapeHtml(alert.body)}</span>
        </a>
      `)
      .join("")
  }

  function mountCenter(instance) {
    const target = document.querySelector(instance.mountSelector)
    if (!target || document.getElementById(instance.centerId)) return null

    const center = document.createElement("div")
    center.id = instance.centerId
    center.className = "care-notification-center"
    center.innerHTML = `
      <button type="button" class="care-bell-button" aria-expanded="false">
        <i class="bx bxs-bell"></i>
        <span class="care-bell-badge" hidden>0</span>
      </button>
      <section class="care-notification-panel" hidden>
        <div class="care-notification-panel-header">
          <strong>Alertas</strong>
          <button type="button" class="care-sound-toggle"></button>
        </div>
        <div class="care-notification-empty">No hay alertas pendientes.</div>
        <div class="care-notification-list"></div>
      </section>
    `

    const bell = center.querySelector(".care-bell-button")
    const panel = center.querySelector(".care-notification-panel")
    const soundButton = center.querySelector(".care-sound-toggle")

    bell?.addEventListener("click", () => {
      const isOpen = panel.hidden
      panel.hidden = !isOpen
      bell.setAttribute("aria-expanded", String(isOpen))
    })

    soundButton?.addEventListener("click", () => {
      const enabled = !isSoundEnabled()
      setSoundEnabled(enabled)
      renderCenter(instance, instance.currentAlerts)
      if (enabled) playAlertSound()
    })

    document.addEventListener("click", (event) => {
      if (center.contains(event.target)) return
      panel.hidden = true
      bell?.setAttribute("aria-expanded", "false")
    })

    target.appendChild(center)
    return center
  }

  function setButtonState(button) {
    if (!button) return

    if (!supportsNotifications()) {
      button.disabled = true
      button.innerHTML = '<i class="bx bx-bell-off"></i><span>No soportadas</span>'
      return
    }

    if (Notification.permission === "granted") {
      button.disabled = true
      button.innerHTML = '<i class="bx bxs-bell-ring"></i><span>Notificaciones activas</span>'
      return
    }

    if (Notification.permission === "denied") {
      button.disabled = true
      button.innerHTML = '<i class="bx bx-bell-off"></i><span>Permiso bloqueado</span>'
      return
    }

    button.disabled = false
    button.innerHTML = '<i class="bx bx-bell"></i><span>Activar notificaciones</span>'
  }

  function mountButton(instance) {
    const target = document.querySelector(instance.mountSelector)
    if (!target || document.getElementById(instance.buttonId)) return null

    const button = document.createElement("button")
    button.type = "button"
    button.id = instance.buttonId
    button.className = "notification-button"
    button.addEventListener("click", async () => {
      if (!supportsNotifications() || Notification.permission !== "default") return

      const permission = await Notification.requestPermission()
      setButtonState(button)

      if (permission === "granted") {
        new Notification("Notificaciones activadas", {
          body: "Te avisare sobre incidentes nuevos y medicamentos pendientes.",
          tag: `${instance.role}:enabled`,
        })
        instance.seedCurrentData()
      }
    })

    target.appendChild(button)
    setButtonState(button)
    return button
  }

  function createInstance(options) {
    const instance = {
      role: options.role,
      endpoint: options.endpoint,
      fetchJson: options.fetchJson,
      routineUrl: options.routineUrl || "./routine.html",
      mountSelector: options.mountSelector,
      buttonId: `notificationButton-${options.role}`,
      centerId: `careNotificationCenter-${options.role}`,
      latestData: null,
      currentAlerts: [],
      timer: null,
      button: null,
      center: null,
      handleData(data, shouldNotify = true) {
        this.latestData = data
        const { date, alerts } = collectAlerts(data, this.routineUrl)
        const seen = readSeen(this.role, date)
        const canNotify = supportsNotifications() && Notification.permission === "granted"
        const newAlerts = alerts.filter((alert) => !seen.has(alert.key))

        this.currentAlerts = alerts
        renderCenter(this, alerts)

        if (!shouldNotify) {
          alerts.forEach((alert) => seen.add(alert.key))
          writeSeen(this.role, date, seen)
          return
        }

        if (newAlerts.length) {
          showToast(newAlerts)
          playAlertSound()

          if (canNotify) {
            newAlerts.forEach((alert) => notify(alert))
          }
        }

        newAlerts.forEach((alert) => seen.add(alert.key))

        writeSeen(this.role, date, seen)
      },
      seedCurrentData() {
        if (this.latestData) this.handleData(this.latestData, false)
      },
      async poll() {
        try {
          this.handleData(await this.fetchJson(this.endpoint), true)
        } catch {
          // Polling should stay quiet; visible pages already render load errors.
        }
      },
      start() {
        this.center = mountCenter(this)
        this.button = mountButton(this)
        renderCenter(this, [])
        document.addEventListener("pointerdown", unlockAudio, { once: true })
        document.addEventListener("keydown", unlockAudio, { once: true })
        this.timer = window.setInterval(() => this.poll(), POLL_INTERVAL)
      },
    }

    return instance
  }

  function init(options) {
    if (!options?.role || !options?.endpoint || typeof options.fetchJson !== "function") return null

    const instance = createInstance(options)
    instances.set(options.role, instance)
    instance.start()
    return instance
  }

  function handleData(role, data, shouldNotify = true) {
    const instance = instances.get(role)
    if (instance) instance.handleData(data, shouldNotify)
  }

  window.CareNotifications = {
    init,
    handleData,
  }
})()
