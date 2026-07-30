const MODIFIERS = ['mod', 'meta', 'cmd', 'ctrl', 'control', 'alt', 'option', 'shift']

/**
 * Match a KeyboardEvent against a binding like 'mod+k', where 'mod' means Cmd
 * on macOS and Ctrl elsewhere.
 */
export function matchesKeybinding(event: KeyboardEvent, binding: string): boolean {
    const parts = binding
        .toLowerCase()
        .split('+')
        .map((part) => part.trim())

    const key = parts.filter((part) => !MODIFIERS.includes(part)).pop()

    if (!key || event.key.toLowerCase() !== key) {
        return false
    }

    if (parts.includes('mod') && !(event.metaKey || event.ctrlKey)) return false
    if ((parts.includes('meta') || parts.includes('cmd')) && !event.metaKey) return false
    if ((parts.includes('ctrl') || parts.includes('control')) && !event.ctrlKey) return false
    if ((parts.includes('alt') || parts.includes('option')) !== event.altKey) return false
    if (parts.includes('shift') !== event.shiftKey) return false

    return true
}
