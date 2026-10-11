(() => {
  const form = document.getElementById('recoveryForm')
  const message = document.getElementById('recoveryMessage')
  const submit = form.querySelector('button')
  const resetting = form.dataset.mode === 'reset'
  // Secrets are entered in the form, never taken from query/fragment or Web Storage.
  if (resetting) window.history.replaceState(null, '', window.location.pathname)
  form.addEventListener('submit', async (event) => {
    event.preventDefault()
    if (submit.disabled) return
    submit.disabled = true
    message.textContent = ''
    const payload = { email: form.elements.email.value.trim() }
    if (resetting) {
      payload.token = form.elements.token.value.trim()
      payload.password = form.elements.password.value
      payload.password_confirmation = form.elements.password_confirmation.value
    }
    try {
      const data = await window.CuidadoApi.fetchJson(resetting ? '/reset-password' : '/forgot-password', {
        method: 'POST', body: JSON.stringify(payload),
      })
      message.textContent = data.message
      if (resetting) {
        form.reset()
        return
      }
    } catch (error) {
      message.textContent = error.message || 'No se pudo completar la solicitud.'
    }
    submit.disabled = false
  })
})()
