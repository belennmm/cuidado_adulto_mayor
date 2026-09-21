(() => {
  const POLL_INTERVAL = 60000
  const ALARM_CHECK_INTERVAL = 15000
  const instances = new Map()
  const escapeHtml = window.CuidadoUi.escapeHtml
  const { readSeen, writeSeen, readAlarmed, writeAlarmed, readSnoozed, writeSnoozed, currentMinutes, collectAlerts } = window.CareNotificationsCore
  const { supportsNotifications, notify, isSoundEnabled, setSoundEnabled, playAlertSound, stopAlarmSound, playAlarmSound, unlockAudio } = window.CareNotificationsAudio

  const { showToast, checkDueAlarms, renderCenter, mountCenter, mountButton } = window.CareNotificationsView.create({ escapeHtml, readAlarmed, writeAlarmed, readSnoozed, writeSnoozed, currentMinutes, supportsNotifications, isSoundEnabled, setSoundEnabled, playAlertSound, stopAlarmSound, playAlarmSound })

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
      alarmTimer: null,
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
        checkDueAlarms(this)

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
        this.alarmTimer = window.setInterval(() => checkDueAlarms(this), ALARM_CHECK_INTERVAL)
        this.poll()
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

  function initForCurrentPage() {
    const path = window.location.pathname.toLowerCase()
    const isProfessional = path.includes("/pages/cuidador-profesional/")
    const isFamily = path.includes("/pages/cuidador-familiar/")
    if (!isProfessional && !isFamily) return

    const role = isProfessional ? "professional" : "family"
    const endpoint = isProfessional ? "/professional/overview" : "/family/overview"
    const routineUrl = isProfessional ? "./routines.html" : "./routine.html"

    init({
      role,
      endpoint,
      fetchJson: (url) => window.CuidadoApi.fetchJson(url),
      mountSelector: isProfessional ? ".professional-actions" : ".family-page-header",
      routineUrl,
    })
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initForCurrentPage)
  } else {
    initForCurrentPage()
  }
})()
