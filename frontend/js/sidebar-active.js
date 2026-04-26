(() => {
    function normalizePath(pathname) {
        return pathname.replace(/\/+$/, "")
    }

    document.addEventListener("DOMContentLoaded", () => {
        const sidebarLinks = document.querySelectorAll("aside.sidebar a.nav-icon[href]")
        if (!sidebarLinks.length) return

        sidebarLinks.forEach((link) => link.classList.remove("active"))

        const currentPath = normalizePath(window.location.pathname)
        let matchedLink = null

        for (const link of sidebarLinks) {
            const href = link.getAttribute("href")
            if (!href || href === "#") continue

            let url = null
            try {
                url = new URL(href, window.location.href)
            } catch {
                continue
            }

            if (normalizePath(url.pathname) === currentPath) {
                matchedLink = link
                break
            }
        }

        if (!matchedLink) {
            const currentFile = currentPath.split("/").pop()
            for (const link of sidebarLinks) {
                const href = link.getAttribute("href")
                if (!href || href === "#") continue

                const linkFile = href.split("/").pop().split("?")[0].split("#")[0]
                if (linkFile === currentFile) {
                    matchedLink = link
                    break
                }
            }
        }

        if (matchedLink) {
            matchedLink.classList.add("active")
        }
    })
})()