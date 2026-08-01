const MODIFIERS = ['mod', 'meta', 'cmd', 'ctrl', 'control', 'alt', 'option', 'shift']

const IS_APPLE =
    typeof navigator !== 'undefined' && /Mac|iPhone|iPad|iPod/.test(navigator.platform)

/**
 * Split a binding into its chord steps: 'g a' is G-then-A, Linear-style.
 * Most bindings are a single step. Each step is matched with
 * matchesKeybinding against its own keydown.
 */
export function splitKeybinding(binding: string): string[] {
    return binding.trim().toLowerCase().split(/\s+/)
}

/**
 * Match a KeyboardEvent against a binding like 'mod+k', where 'mod' means Cmd
 * on macOS and Ctrl elsewhere. Modifiers the binding doesn't name must not be
 * held, so a bare 'a' binding won't also fire on 'mod+a'.
 */
export function matchesKeybinding(event: KeyboardEvent, binding: string): boolean {
    const parts = binding
        .toLowerCase()
        .split('+')
        .map((part) => part.trim())

    const key = parts.filter((part) => !MODIFIERS.includes(part)).pop()

    if (!key || !matchesKey(event, key)) {
        return false
    }

    if (parts.includes('mod')) {
        if (!(event.metaKey || event.ctrlKey)) return false
    } else {
        if ((parts.includes('meta') || parts.includes('cmd')) !== event.metaKey) return false
        if ((parts.includes('ctrl') || parts.includes('control')) !== event.ctrlKey) return false
    }

    if ((parts.includes('alt') || parts.includes('option')) !== event.altKey) return false
    if (parts.includes('shift') !== event.shiftKey) return false

    return true
}

function matchesKey(event: KeyboardEvent, key: string): boolean {
    // Alt/Option changes event.key ('®' for ⌥R on macOS), so letters and
    // digits also match on the physical key code.
    if (/^[a-z]$/.test(key)) {
        return event.key.toLowerCase() === key || event.code === `Key${key.toUpperCase()}`
    }

    if (/^[0-9]$/.test(key)) {
        return event.key === key || event.code === `Digit${key}`
    }

    if (key === 'space') {
        return event.key === ' ' || event.code === 'Space'
    }

    return event.key.toLowerCase() === key
}

/**
 * Whether the binding includes a non-shift modifier. Bindings without one
 * (like 'a', 'shift+p', or the chord 'g a') would collide with typing, so
 * they only fire while no input is focused.
 */
export function hasKeybindingModifier(binding: string): boolean {
    return splitKeybinding(binding).every((step) =>
        step.split('+').some((part) => MODIFIERS.includes(part.trim()) && part.trim() !== 'shift'),
    )
}

export function isEditableTarget(target: EventTarget | null): boolean {
    if (!(target instanceof HTMLElement)) return false

    return (
        target.isContentEditable ||
        target instanceof HTMLInputElement ||
        target instanceof HTMLTextAreaElement ||
        target instanceof HTMLSelectElement
    )
}

const APPLE_MODIFIER_LABELS: Record<string, string> = {
    ctrl: '⌃',
    control: '⌃',
    alt: '⌥',
    option: '⌥',
    shift: '⇧',
    mod: '⌘',
    meta: '⌘',
    cmd: '⌘',
}

const MODIFIER_LABELS: Record<string, string> = {
    ctrl: 'Ctrl',
    control: 'Ctrl',
    mod: 'Ctrl',
    alt: 'Alt',
    option: 'Alt',
    shift: 'Shift',
    meta: 'Win',
    cmd: 'Win',
}

// Display order matches Linear: ⌘ ⌃ ⌥ ⇧ on macOS, Ctrl Alt Shift Win elsewhere.
const MODIFIER_ORDER = ['⌘', 'Ctrl', '⌃', 'Alt', '⌥', '⇧', 'Shift', 'Win']

const KEY_LABELS: Record<string, string> = {
    arrowup: '↑',
    arrowdown: '↓',
    arrowleft: '←',
    arrowright: '→',
    enter: '↵',
    escape: 'Esc',
    backspace: '⌫',
    delete: '⌦',
    space: 'Space',
    comma: ',',
    period: '.',
}

/**
 * Format a binding as display chips grouped per chord step: 'mod+shift+m'
 * becomes [['⌘', '⇧', 'M']] on macOS and [['Ctrl', 'Shift', 'M']]
 * elsewhere, while the chord 'g a' becomes [['G'], ['A']] — rendered as
 * "G then A".
 */
export function formatKeybinding(binding: string): string[][] {
    return splitKeybinding(binding).map(formatCombo)
}

function formatCombo(combo: string): string[] {
    const parts = combo
        .toLowerCase()
        .split('+')
        .map((part) => part.trim())
        .filter((part) => part !== '')

    const labels = IS_APPLE ? APPLE_MODIFIER_LABELS : MODIFIER_LABELS

    const modifiers = parts
        .filter((part) => MODIFIERS.includes(part))
        .map((part) => labels[part])
        .filter((label, index, all) => all.indexOf(label) === index)
        .sort((a, b) => MODIFIER_ORDER.indexOf(a) - MODIFIER_ORDER.indexOf(b))

    const key = parts.filter((part) => !MODIFIERS.includes(part)).pop()

    if (!key) return modifiers

    const label =
        KEY_LABELS[key] ?? (key.length === 1 ? key.toUpperCase() : key[0].toUpperCase() + key.slice(1))

    return [...modifiers, label]
}
