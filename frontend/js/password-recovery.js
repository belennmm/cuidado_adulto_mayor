(() => {
  const form = document.getElementById('recoveryForm')
  const message = document.getElementById('recoveryMessage')
  const submit = form.querySelector('button')
  const resetting = form.dataset.mode === 'reset'
  const fragment = new URLSearchParams(window.location.hash.slice(1))
  const token = fragment.get('token') || ''
  const email = fragment.get('email') || ''
  if (resetting) {
    window.history.replaceState(null, '', window.location.pathname)
    form.elements.email.value = email
    if (!/^[a-f0-9]{64}$/i.test(token) || !email) {
      message.textContent = 'El enlace no es válido. Solicita uno nuevo.'
      submit.disabled = true
    }
  }
  form.addEventListener('submit', async (event) => {
    event.preventDefault()
    if (submit.disabled) return
    submit.disabled = true
    message.textContent = ''
    const payload = { email: form.elements.email.value.trim() }
    if (resetting) {
      payload.token = token
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
