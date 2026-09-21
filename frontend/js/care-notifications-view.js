(() => {
  function create({ escapeHtml, readAlarmed, writeAlarmed, readSnoozed, writeSnoozed, currentMinutes, supportsNotifications, isSoundEnabled, setSoundEnabled, playAlertSound, stopAlarmSound, playAlarmSound }) {
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

    function showAlarm(instance, alert) {
      const existing = document.querySelector(".care-alarm")
      if (existing) existing.remove()

      const alarm = document.createElement("div")
      alarm.className = "care-alarm"
      alarm.innerHTML = `
        <div class="care-alarm-icon"><i class="bx bxs-alarm"></i></div>
        <div class="care-alarm-content">
          <strong>Hora del medicamento</strong>
          <span>${escapeHtml(alert.body)}</span>
        </div>
        <div class="care-alarm-actions">
          <button type="button" class="care-alarm-snooze">Posponer 5 min</button>
          <button type="button" class="care-alarm-stop">Detener</button>
        </div>
      `

      alarm.querySelector(".care-alarm-snooze")?.addEventListener("click", () => {
        const dataDate = instance.latestData?.date || new Date().toISOString().slice(0, 10)
        const snoozed = readSnoozed(instance.role, dataDate)
        const alarmed = readAlarmed(instance.role, dataDate)

        snoozed[alert.alarmKey] = Date.now() + (5 * 60 * 1000)
        alarmed.delete(alert.alarmKey)
        writeSnoozed(instance.role, dataDate, snoozed)
        writeAlarmed(instance.role, dataDate, alarmed)
        stopAlarmSound()
        alarm.remove()
        showToast([{
          title: "Recordatorio pospuesto",
          body: "La alarma volverá a sonar en 5 minutos.",
        }])
      })

      alarm.querySelector(".care-alarm-stop")?.addEventListener("click", () => {
        stopAlarmSound()
        alarm.remove()
      })

      document.body.appendChild(alarm)
    }

    function checkDueAlarms(instance) {
      const dataDate = instance.latestData?.date || new Date().toISOString().slice(0, 10)
      const alarmed = readAlarmed(instance.role, dataDate)
      const snoozed = readSnoozed(instance.role, dataDate)
      const nowMinutes = currentMinutes()
      const now = Date.now()

      const dueAlerts = instance.currentAlerts.filter((alert) => (
        alert.type === "medication"
        && Number.isInteger(alert.dueMinutes)
        && (
          (snoozed[alert.alarmKey] && now >= Number(snoozed[alert.alarmKey]))
          || (
            !snoozed[alert.alarmKey]
            && nowMinutes >= alert.dueMinutes
            && nowMinutes <= alert.dueMinutes + 1
            && !alarmed.has(alert.alarmKey)
          )
        )
      ))

      if (!dueAlerts.length) return

      dueAlerts.forEach((alert) => {
        alarmed.add(alert.alarmKey)
        delete snoozed[alert.alarmKey]
      })
      writeAlarmed(instance.role, dataDate, alarmed)
      writeSnoozed(instance.role, dataDate, snoozed)
      showAlarm(instance, dueAlerts[0])
      playAlarmSound()
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

    return Object.freeze({ showToast, checkDueAlarms, renderCenter, mountCenter, mountButton })
  }
  window.CareNotificationsView = Object.freeze({ create })
})()

