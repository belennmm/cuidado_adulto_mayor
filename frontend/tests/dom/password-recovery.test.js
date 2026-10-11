import { describe, expect, it, vi } from 'vitest'

async function setup(reset = false, fragment = '') {
  window.history.replaceState(null, '', `/pages/${reset ? 'reset' : 'forgot'}-password.html${fragment}`)
  document.body.innerHTML = `<form id="recoveryForm" data-mode="${reset ? 'reset' : 'request'}"><input name="email"><input name="password"><input name="password_confirmation"><button>Enviar</button></form><p id="recoveryMessage"></p>`
  window.CuidadoApi = { fetchJson: vi.fn().mockResolvedValue({ message: 'Solicitud recibida' }) }
  await import('../../js/password-recovery.js')
  return document.getElementById('recoveryForm')
}
function submit(form) { form.dispatchEvent(new Event('submit', { cancelable: true })) }

describe('recuperación de contraseña', () => {
  it('envía únicamente el correo y bloquea envíos simultáneos', async () => {
    const form = await setup()
    form.elements.email.value = 'user@example.test'
    submit(form)
    submit(form)
    expect(window.CuidadoApi.fetchJson).toHaveBeenCalledOnce()
    expect(window.CuidadoApi.fetchJson).toHaveBeenCalledWith('/forgot-password', { method: 'POST', body: JSON.stringify({ email: 'user@example.test' }) })
    await vi.waitFor(() => expect(document.getElementById('recoveryMessage').textContent).toBe('Solicitud recibida'))
  })
  it('retira el secreto de la URL y no lo guarda en Web Storage', async () => {
    const token = 'a'.repeat(64)
    const form = await setup(true, `#token=${token}&email=user%40example.test`)
    expect(window.location.hash).toBe('')
    expect(localStorage.length).toBe(0)
    expect(sessionStorage.length).toBe(0)
    form.elements.password.value = 'Exact-Password!2026'
    form.elements.password_confirmation.value = 'Exact-Password!2026'
    submit(form)
    expect(JSON.parse(window.CuidadoApi.fetchJson.mock.calls[0][1].body)).toEqual({ email: 'user@example.test', token, password: 'Exact-Password!2026', password_confirmation: 'Exact-Password!2026' })
    await vi.waitFor(() => expect(form.elements.password.value).toBe(''))
    expect(form.querySelector('button').disabled).toBe(true)
  })
  it('rechaza un enlace incompleto sin enviar solicitudes', async () => {
    const form = await setup(true, '#token=invalid')
    submit(form)
    expect(form.querySelector('button').disabled).toBe(true)
    expect(window.CuidadoApi.fetchJson).not.toHaveBeenCalled()
  })
  it('muestra errores como texto y permite reintentar', async () => {
    const form = await setup()
    window.CuidadoApi.fetchJson.mockRejectedValue(new Error('<img src=x onerror=alert(1)>'))
    submit(form)
    await vi.waitFor(() => expect(form.querySelector('button').disabled).toBe(false))
    expect(document.getElementById('recoveryMessage').textContent).toContain('<img')
    expect(document.querySelector('img')).toBeNull()
  })
})
