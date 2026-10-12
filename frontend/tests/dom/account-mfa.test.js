import { beforeEach, describe, expect, it, vi } from 'vitest'

describe('administracion MFA', () => {
  beforeEach(async () => {
    document.body.innerHTML = '<div><form id="revokeSessionsForm"></form></div>'
    window.AuthSession = { clearSession: vi.fn() }
    window.CuidadoApi = { fetchJson: vi.fn().mockResolvedValue({ enabled: false }) }
    await import('../../js/account-mfa.js')
    await vi.waitFor(() => expect(document.querySelector('#accountMfa p[aria-live]').textContent).toBe('MFA desactivado.'))
  })

  it('muestra la clave como texto sin guardar secretos y limpia la contrasena', async () => {
    const form = document.querySelector('#accountMfa form')
    form.elements.current_password.value = 'password'
    window.CuidadoApi.fetchJson.mockResolvedValueOnce({ secret: '<img src=x onerror=alert(1)>', message: 'Preparado' })
    form.querySelector('button').click()
    await vi.waitFor(() => expect(document.querySelector('#accountMfa pre').textContent).toContain('<img'))
    expect(document.querySelector('img')).toBeNull()
    expect(form.elements.current_password.value).toBe('')
    expect(localStorage.length).toBe(0)
    expect(sessionStorage.length).toBe(0)
    expect(window.AuthSession.clearSession).not.toHaveBeenCalled()
    document.querySelector('#accountMfa > button').click()
    expect(document.querySelector('#accountMfa pre').textContent).toBe('')
  })

  it('conserva codigos visibles tras cerrar metadatos y permite volver al login', async () => {
    const form = document.querySelector('#accountMfa form')
    form.elements.current_password.value = 'password'
    form.elements.code.value = '123456'
    window.CuidadoApi.fetchJson.mockResolvedValueOnce({ recovery_codes: ['a'.repeat(32)], message: 'Activado' })
    form.querySelectorAll('button')[1].click()
    await vi.waitFor(() => expect(window.AuthSession.clearSession).toHaveBeenCalledOnce())
    expect(document.querySelector('#accountMfa pre').textContent).toContain('a'.repeat(32))
    expect(document.querySelector('#accountMfa a').hidden).toBe(false)
    expect(form.elements.code.value).toBe('')
    expect(window.CuidadoApi.fetchJson).toHaveBeenLastCalledWith('/mfa/confirm', { method: 'POST', body: JSON.stringify({ current_password: 'password', code: '123456' }) })
  })
})
