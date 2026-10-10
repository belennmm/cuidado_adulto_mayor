(() => {
  function httpUrl(value, base = window.location.href) {
    if (typeof value !== "string" || !value || /[\u0000-\u0020\u007f\\]/.test(value)) return null
    try {
      const url = new URL(value, base)
      if (!["http:", "https:"].includes(url.protocol) || url.username || url.password) return null
      return url
    } catch { return null }
  }
  function localUrl(value) {
    const url = httpUrl(value)
    return url && url.origin === window.location.origin ? url.href : null
  }
  window.CuidadoUrls = Object.freeze({ httpUrl, localUrl })
})()
