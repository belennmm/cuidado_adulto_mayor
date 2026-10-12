import { beforeEach, describe, expect, it, vi } from 'vitest'

describe('dispositivos de la cuenta', () => {
  beforeEach(async () => {
    document.body.innerHTML = '<form id="revokeSessionsForm"><input id="revokeSessionsPassword"><button type="submit">Cerrar todas</button><p id="revokeSessionsMessage"></p></form><button id="refreshAccountSessions">Actualizar</button><p id="accountSessionsMessage"></p><ul id="accountSessionsList"></ul><form id="reauthenticateForm"><input id="reauthenticatePassword"><button>Confirmar</button><p id="reauthenticateMessage"></p></form>'
    window.AuthSession = { clearSession: vi.fn() }
    window.navigateWithLoading = vi.fn()
    window.CuidadoApi = { fetchJson: vi.fn().mockResolvedValue({ sessions: [{ id: 'session-id', current: false, type: 'browser', device: '<img src=x onerror=alert(1)>', ip_address: '192.0.2.1', last_activity: '2026-10-11 10:00:00' }] }) }
    await import('../../js/account-sessions.js')
    await vi.waitFor(() => expect(document.querySelectorAll('#accountSessionsList li')).toHaveLength(1))
  })
  it('muestra dispositivo, IP y actividad como texto', () => {
    expect(document.getElementById('accountSessionsList').textContent).toContain('<img')
    expect(document.getElementById('accountSessionsList').textContent).toContain('192.0.2.1')
    expect(document.querySelector('img')).toBeNull()
  })
  it('cierra el dispositivo seleccionado y actualiza el listado', async () => {
    window.CuidadoApi.fetchJson.mockResolvedValueOnce({ current: false }).mockResolvedValueOnce({ sessions: [] })
    document.querySelector('#accountSessionsList button').click()
    await vi.waitFor(() => expect(window.CuidadoApi.fetchJson).toHaveBeenCalledWith('/sessions/session-id', { method: 'DELETE' }))
    await vi.waitFor(() => expect(document.querySelectorAll('#accountSessionsList li')).toHaveLength(0))
    expect(window.AuthSession.clearSession).not.toHaveBeenCalled()
  })
  it('sale cuando el servidor confirma el cierre de la sesión actual', async () => {
    window.CuidadoApi.fetchJson.mockResolvedValueOnce({ current: true })
    document.querySelector('#accountSessionsList button').click()
    await vi.waitFor(() => expect(window.navigateWithLoading).toHaveBeenCalledWith('../../index.html'))
    expect(window.AuthSession.clearSession).toHaveBeenCalledOnce()
  })
  it('permite reintentar un cierre rechazado', async () => {
    window.CuidadoApi.fetchJson.mockRejectedValueOnce(new Error('Solicitud rechazada'))
    document.querySelector('#accountSessionsList button').click()
    await vi.waitFor(() => expect(document.getElementById('accountSessionsMessage').textContent).toBe('Solicitud rechazada'))
    expect(document.querySelector('#accountSessionsList button').disabled).toBe(false)
  })
  it('confirma con el servidor y borra la contraseña incluso cuando falla', async () => {
    window.CuidadoApi.fetchJson.mockRejectedValueOnce(new Error('Contraseña incorrecta'))
    document.getElementById('reauthenticatePassword').value = '  Exact-Password!  '
    document.getElementById('reauthenticateForm').dispatchEvent(new Event('submit', { cancelable: true }))
    expect(window.CuidadoApi.fetchJson).toHaveBeenCalledWith('/reauthenticate', { method: 'POST', body: JSON.stringify({ current_password: '  Exact-Password!  ' }) })
    await vi.waitFor(() => expect(document.getElementById('reauthenticatePassword').value).toBe(''))
    expect(document.getElementById('reauthenticateMessage').textContent).toBe('Contraseña incorrecta')
  })
})
