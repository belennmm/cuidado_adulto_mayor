(() => {
  const soundStorageKey = "care-notifications-sound"
  let audioContext = null
  let activeAlarm = null
  function supportsNotifications() { return "Notification" in window }
  function notify(alert) {
    if (!supportsNotifications() || Notification.permission !== "granted") return
    const notification = new Notification(alert.title, { body: alert.body, tag: alert.key, renotify: false })
    notification.onclick = () => { window.focus(); if (alert.url) window.location.href = alert.url; notification.close() }
  }
  function isSoundEnabled() { return localStorage.getItem(soundStorageKey) !== "muted" }
  function setSoundEnabled(enabled) { localStorage.setItem(soundStorageKey, enabled ? "enabled" : "muted") }
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
      oscillator.connect(gain); gain.connect(audioContext.destination); oscillator.start(now); oscillator.stop(now + 0.34)
    } catch { /* The visual alert remains available when audio is blocked. */ }
  }
  function stopAlarmSound() { if (activeAlarm) { window.clearInterval(activeAlarm.timer); activeAlarm = null } }
  function playAlarmSound() {
    if (!isSoundEnabled()) return
    stopAlarmSound()
    let count = 0
    activeAlarm = { timer: window.setInterval(() => { count += 1; playAlertSound(); if (count >= 12) stopAlarmSound() }, 900) }
    playAlertSound()
  }
  function unlockAudio() {
    if (!isSoundEnabled()) return
    const AudioCtor = window.AudioContext || window.webkitAudioContext
    if (!AudioCtor) return
    try { audioContext = audioContext || new AudioCtor(); if (audioContext.state === "suspended") audioContext.resume() } catch { /* Optional audio. */ }
  }
  window.CareNotificationsAudio = Object.freeze({ supportsNotifications, notify, isSoundEnabled, setSoundEnabled, playAlertSound, stopAlarmSound, playAlarmSound, unlockAudio })
})()
