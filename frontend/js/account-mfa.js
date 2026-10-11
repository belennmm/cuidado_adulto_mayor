(() => {
  const anchor = document.getElementById('revokeSessionsForm')
  if (!anchor) return
  const section = document.createElement('section')
  section.id = 'accountMfa'
  const heading = document.createElement('h2')
  heading.textContent = 'Autenticacion de dos factores'
  const info = document.createElement('p')
  info.textContent = 'Usa una aplicacion autenticadora. Los 10 codigos de recuperacion son de un solo uso y caducan en 7 dias. Guarda la clave y los codigos fuera de esta aplicacion. Al activar, modificar o desactivar MFA se cerraran tus sesiones. Si acabas de usar un codigo del autenticador, espera al siguiente.'
  const status = document.createElement('p')
  status.setAttribute('aria-live', 'polite')
  const form = document.createElement('form')
  for (const [name, labelText, type] of [['current_password', 'Contrasena actual', 'password'], ['code', 'Codigo del autenticador o de recuperacion', 'text']]) {
    const label = document.createElement('label')
    label.textContent = labelText
    const input = document.createElement('input')
    input.name = name
    input.type = type
    input.autocomplete = name === 'code' ? 'one-time-code' : 'current-password'
    input.required = name === 'current_password'
    input.maxLength = name === 'code' ? 32 : 1024
    label.append(input)
    form.append(label)
  }
  const output = document.createElement('pre')
  output.style.whiteSpace = 'pre-wrap'
  let interacted = false
  let busy = false
  const buttons = []
  for (const [label, path, method] of [['Preparar autenticador', '/mfa/setup', 'POST'], ['Confirmar activacion', '/mfa/confirm', 'POST'], ['Regenerar codigos', '/mfa/recovery-codes', 'POST'], ['Desactivar MFA', '/mfa', 'DELETE']]) {
    const button = document.createElement('button')
    button.type = 'button'
    button.textContent = label
    button.addEventListener('click', async () => {
      if (busy || !form.reportValidity()) return
      busy = true
      interacted = true
      buttons.forEach(item => { item.disabled = true })
      output.textContent = ''
      const payload = { current_password: form.elements.current_password.value }
      if (form.elements.code.value.trim()) payload.code = form.elements.code.value.trim()
      form.reset()
      try {
        const data = await window.CuidadoApi.fetchJson(path, { method, body: JSON.stringify(payload) })
        status.textContent = data.message
        if (data.secret) output.textContent = `Clave manual (confirma en 10 minutos): ${data.secret}`
        else {
          window.AuthSession?.clearSession()
          if (data.recovery_codes) output.textContent = `Guarda estos codigos antes de salir:\n${data.recovery_codes.join('\n')}`
          login.hidden = false
          buttons.forEach(item => { item.hidden = true })
        }
      } catch (error) { status.textContent = error.message || 'No se pudo completar la solicitud.' }
      finally { busy = false; buttons.forEach(item => { item.disabled = false }) }
    })
    buttons.push(button)
    form.append(button)
  }
  form.addEventListener('submit', event => event.preventDefault())
  const clear = document.createElement('button')
  clear.type = 'button'
  clear.textContent = 'Ocultar clave y codigos'
  clear.addEventListener('click', () => { output.textContent = ''; form.reset() })
  const login = document.createElement('a')
  login.href = '../../index.html'
  login.textContent = 'Volver a iniciar sesion'
  login.hidden = true
  section.append(heading, info, status, form, output, clear, login)
  anchor.parentNode.insertBefore(section, anchor)
  window.CuidadoApi.fetchJson('/mfa').then(data => {
    if (!interacted) status.textContent = data.enabled ? `MFA activo. Codigos disponibles: ${data.recovery_codes_remaining}` : 'MFA desactivado.'
  }).catch(() => { if (!interacted) status.textContent = 'No se pudo consultar MFA.' })
})()
