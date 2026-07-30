import { createRoot, type Root } from 'react-dom/client'

import { SpotlightApp } from './SpotlightApp'
import type { Bridge, SpotlightConfig } from './types'

type MountElement = HTMLElement & { _spotlightRoot?: Root }

declare global {
    interface Window {
        Alpine?: any
        FilamentSpotlight?: {
            open: () => void
            close: () => void
            toggle: () => void
        }
    }
}

window.FilamentSpotlight = {
    open: () => window.dispatchEvent(new CustomEvent('filament-spotlight:open')),
    close: () => window.dispatchEvent(new CustomEvent('filament-spotlight:close')),
    toggle: () => window.dispatchEvent(new CustomEvent('filament-spotlight:toggle')),
}

function registerAlpineComponent() {
    window.Alpine.data('filamentSpotlight', (config: SpotlightConfig) => ({
        init(this: any) {
            const el = this.$el as MountElement

            // The element is inside @persist, so init() can run again after
            // SPA navigation while the React root is still alive.
            if (el._spotlightRoot) return

            const bridge: Bridge = {
                getStaticCommands: (url) => this.$wire.getStaticCommands(url),
                search: (query, url) => this.$wire.search(query, url),
                execute: (id, context) => this.$wire.execute(id, context),
            }

            el._spotlightRoot = createRoot(el)
            el._spotlightRoot.render(<SpotlightApp config={config} bridge={bridge} />)
        },

        destroy(this: any) {
            const el = this.$el as MountElement

            el._spotlightRoot?.unmount()
            delete el._spotlightRoot
        },
    }))
}

if (window.Alpine) {
    registerAlpineComponent()
} else {
    document.addEventListener('alpine:init', registerAlpineComponent)
}
