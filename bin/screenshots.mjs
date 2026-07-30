// Generates the README screenshots against the workbench demo app.
//
//   npm run screenshots
//
// Requires `composer install` and `npx playwright install chromium`.
// Boots `vendor/bin/testbench serve` (see testbench.yaml), logs in via the
// workbench auto-login route, opens the command menu, and captures light and
// dark variants into art/.

import { spawn, spawnSync } from 'node:child_process'
import { mkdirSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { chromium } from 'playwright'

const PORT = Number(process.env.SCREENSHOT_PORT ?? 9973)
const BASE = `http://127.0.0.1:${PORT}`
const ROOT = fileURLToPath(new URL('..', import.meta.url))
const OUT = fileURLToPath(new URL('../art', import.meta.url))
const SEARCH_QUERY = 'ca'
const PADDING = 48

// Clip to the dialog's bounding box plus padding, so the README shows the
// menu itself with a strip of the dimmed panel behind it as margin.
async function screenshotMenu(page, path) {
    const box = await page.locator('.fi-spotlight').boundingBox()
    const viewport = page.viewportSize()

    const x = Math.max(0, box.x - PADDING)
    const y = Math.max(0, box.y - PADDING)

    await page.screenshot({
        path,
        clip: {
            x,
            y,
            width: Math.min(viewport.width, box.x + box.width + PADDING) - x,
            height: Math.min(viewport.height, box.y + box.height + PADDING) - y,
        },
    })
}

async function serverResponds() {
    try {
        const response = await fetch(`${BASE}/admin/login`)
        return response.ok
    } catch {
        return false
    }
}

async function startServer() {
    if (await serverResponds()) {
        throw new Error(`Something is already listening on port ${PORT} — stop it or set SCREENSHOT_PORT.`)
    }

    const build = spawnSync('vendor/bin/testbench', ['workbench:build'], { cwd: ROOT, stdio: 'inherit' })

    if (build.status !== 0) {
        throw new Error('workbench:build failed')
    }

    const server = spawn('vendor/bin/testbench', ['serve', `--port=${PORT}`, '--no-reload'], {
        cwd: ROOT,
        stdio: 'ignore',
        detached: true,
    })

    for (let attempt = 0; attempt < 60; attempt++) {
        if (await serverResponds()) return server
        await new Promise((resolve) => setTimeout(resolve, 500))
    }

    throw new Error('Server did not come up within 30s')
}

async function capture(browser, colorScheme) {
    const context = await browser.newContext({
        baseURL: BASE,
        viewport: { width: 1280, height: 800 },
        deviceScaleFactor: 2,
        colorScheme,
    })

    // Filament persists the theme choice in localStorage.
    await context.addInitScript((theme) => localStorage.setItem('theme', theme), colorScheme)

    const page = await context.newPage()

    // The workbench route logs in the demo user and redirects to /admin.
    await page.goto('/_workbench')
    await page.waitForURL('**/admin')
    await page.waitForFunction(() => window.FilamentSpotlight !== undefined)
    await page.evaluate(() => document.fonts.ready)

    // Presentation-only: blur the panel behind the overlay so the clipped
    // screenshot's margin reads as a calm backdrop instead of stray page text.
    await page.addStyleTag({ content: '.fi-spotlight-overlay { backdrop-filter: blur(8px); }' })

    await page.evaluate(() => window.FilamentSpotlight.open())
    await page.locator('.fi-spotlight [cmdk-item]').first().waitFor()
    await page.waitForTimeout(500)
    await screenshotMenu(page, `${OUT}/spotlight-${colorScheme}.png`)

    await page.locator('[cmdk-input]').pressSequentially(SEARCH_QUERY, { delay: 60 })
    // Wait for the debounced server-side search to append record results.
    await page.locator('.fi-spotlight [cmdk-item]', { hasText: 'Cameron Williamson' }).waitFor()
    await page.waitForTimeout(500)
    await screenshotMenu(page, `${OUT}/spotlight-search-${colorScheme}.png`)

    // Contextual commands: open the menu on Jane Cooper's edit page (the
    // seeder creates her right after the demo user, so she has ID 2).
    await page.goto('/admin/users/2/edit')
    await page.waitForFunction(() => window.FilamentSpotlight !== undefined)
    await page.evaluate(() => document.fonts.ready)
    await page.addStyleTag({ content: '.fi-spotlight-overlay { backdrop-filter: blur(8px); }' })

    await page.evaluate(() => window.FilamentSpotlight.open())
    await page.locator('.fi-spotlight [cmdk-item]', { hasText: 'Impersonate' }).waitFor()
    await page.waitForTimeout(500)
    await screenshotMenu(page, `${OUT}/spotlight-context-${colorScheme}.png`)

    await context.close()
}

mkdirSync(OUT, { recursive: true })

const server = await startServer()

try {
    const browser = await chromium.launch()

    for (const colorScheme of ['light', 'dark']) {
        await capture(browser, colorScheme)
        console.log(`Captured ${colorScheme} screenshots`)
    }

    await browser.close()
} finally {
    process.kill(-server.pid, 'SIGTERM')
}
