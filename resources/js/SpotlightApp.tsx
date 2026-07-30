import { Command } from 'cmdk'
import { Fragment, useCallback, useEffect, useMemo, useRef, useState } from 'react'

import {
    formatKeybinding,
    hasKeybindingModifier,
    isEditableTarget,
    matchesKeybinding,
    splitKeybinding,
} from './keybindings'
import { groupItems, rankStaticItems } from './ranking'
import type { Bridge, CommandItem, ContextChip, SpotlightConfig } from './types'

const SEARCH_DEBOUNCE_MS = 250
const CHORD_TIMEOUT_MS = 1000

type Props = {
    config: SpotlightConfig
    bridge: Bridge
}

export function SpotlightApp({ config, bridge }: Props) {
    const [open, setOpen] = useState(false)
    const [query, setQuery] = useState('')
    const [staticItems, setStaticItems] = useState<CommandItem[] | null>(null)
    const [dynamicItems, setDynamicItems] = useState<CommandItem[]>([])
    const [loading, setLoading] = useState(false)
    const [context, setContext] = useState<ContextChip | null>(null)
    // Backspace on an empty query steps out of the context, Linear-style;
    // reopening the menu steps back in.
    const [contextActive, setContextActive] = useState(true)
    const [keybindingItems, setKeybindingItems] = useState<CommandItem[]>(config.keybindingItems)

    const staticStale = useRef(false)
    const requestSeq = useRef(0)
    // First step of a chord binding ('g' of 'g a') waiting for its second key.
    const chordPrefix = useRef<string | null>(null)
    const chordTimer = useRef<number | undefined>(undefined)

    useEffect(() => {
        const onOpen = () => setOpen(true)
        const onClose = () => setOpen(false)
        const onToggle = () => setOpen((open) => !open)

        // Livewire's PHP-side dispatch() surfaces as browser CustomEvents, so
        // $this->dispatch('filament-spotlight:open') works with no extra glue.
        window.addEventListener('filament-spotlight:open', onOpen)
        window.addEventListener('filament-spotlight:close', onClose)
        window.addEventListener('filament-spotlight:toggle', onToggle)

        return () => {
            window.removeEventListener('filament-spotlight:open', onOpen)
            window.removeEventListener('filament-spotlight:close', onClose)
            window.removeEventListener('filament-spotlight:toggle', onToggle)
        }
    }, [])

    useEffect(() => {
        const onNavigated = () => {
            setOpen(false)
            // Navigation items and badges can differ per page or tenant, so
            // refetch the static index the next time the menu opens.
            staticStale.current = true
            // Contextual shortcuts follow the page; full page loads remount
            // with fresh config instead, so this only matters under SPA mode.
            bridge
                .getKeybindingCommands(window.location.href)
                .then(setKeybindingItems)
                .catch(() => {})
        }

        document.addEventListener('livewire:navigated', onNavigated)

        return () => document.removeEventListener('livewire:navigated', onNavigated)
    }, [bridge])

    useEffect(() => {
        if (!open) {
            setQuery('')
            setDynamicItems([])
            setLoading(false)
            setContextActive(true)
            return
        }

        if (staticItems === null || staticStale.current) {
            staticStale.current = false
            bridge
                .getStaticCommands(window.location.href)
                .then((payload) => {
                    setStaticItems(payload.commands)
                    setContext(payload.context)
                })
                .catch(() => {
                    setStaticItems([])
                    setContext(null)
                })
        }
    }, [open])

    useEffect(() => {
        if (!open) return

        const trimmed = query.trim()

        if (!trimmed) {
            setDynamicItems([])
            setLoading(false)
            return
        }

        const seq = ++requestSeq.current
        setLoading(true)

        const timeout = setTimeout(() => {
            bridge
                .search(trimmed, contextActive ? window.location.href : null)
                .then((items) => {
                    // Livewire promises cannot be aborted; drop stale responses.
                    if (requestSeq.current !== seq) return
                    setDynamicItems(items)
                    setLoading(false)
                })
                .catch(() => {
                    if (requestSeq.current === seq) setLoading(false)
                })
        }, SEARCH_DEBOUNCE_MS)

        return () => clearTimeout(timeout)
    }, [query, open, contextActive])

    const navigate = useCallback(
        (url: string, newTab: boolean) => {
            if (newTab) {
                window.open(url, '_blank', 'noopener')
                return
            }

            const livewire = (window as any).Livewire

            if (config.spaEnabled && livewire?.navigate && url.startsWith(window.location.origin)) {
                livewire.navigate(url)
            } else {
                window.location.assign(url)
            }
        },
        [config.spaEnabled],
    )

    const runItem = useCallback(
        async (item: CommandItem) => {
            if (item.type === 'url' && item.url) {
                setOpen(false)
                navigate(item.url, item.openInNewTab)
                return
            }

            if (item.type === 'dispatch' && item.event) {
                setOpen(false)
                ;(window as any).Livewire?.dispatch(item.event, item.eventArgs)
                return
            }

            const result = await bridge.execute(item.id, {
                query: query.trim(),
                url: window.location.href,
            })
            setOpen(false)

            if (result?.redirect) {
                navigate(result.redirect, false)
            }
        },
        [bridge, navigate, query],
    )

    // Stepping out of the context hides its pinned commands (and their
    // shortcuts) without refetching — the chip disappears with them.
    const scopedStatic = useMemo(
        () => (contextActive ? (staticItems ?? []) : (staticItems ?? []).filter((item) => !item.contextual)),
        [staticItems, contextActive],
    )

    const scopedDynamic = useMemo(
        () => (contextActive ? dynamicItems : dynamicItems.filter((item) => !item.contextual)),
        [dynamicItems, contextActive],
    )

    useEffect(() => {
        const clearChord = () => {
            chordPrefix.current = null
            window.clearTimeout(chordTimer.current)
        }

        const onKeyDown = (event: KeyboardEvent) => {
            if (config.keybindings.some((binding) => matchesKeybinding(event, binding))) {
                event.preventDefault()
                clearChord()
                setOpen((open) => !open)
                return
            }

            if (event.defaultPrevented || event.repeat) return

            // While the menu is open only single-step modifier shortcuts fire
            // — plain keys (and chords) belong to the search input. While it
            // is closed, item shortcuts work Linear-style anywhere on the
            // page, except that unmodified ones never steal keystrokes from a
            // focused field.
            const candidates = open
                ? [...scopedStatic, ...scopedDynamic].filter(
                      (item) =>
                          item.keybinding &&
                          splitKeybinding(item.keybinding).length === 1 &&
                          hasKeybindingModifier(item.keybinding),
                  )
                : keybindingItems.filter(
                      (item) =>
                          item.keybinding &&
                          (hasKeybindingModifier(item.keybinding) || !isEditableTarget(event.target)),
                  )

            if (chordPrefix.current !== null) {
                const prefix = chordPrefix.current
                clearChord()

                const chorded = candidates.find((item) => {
                    const steps = splitKeybinding(item.keybinding!)

                    return steps.length === 2 && steps[0] === prefix && matchesKeybinding(event, steps[1])
                })

                if (chorded) {
                    event.preventDefault()
                    void runItem(chorded)
                    return
                }
            }

            const single = candidates.find((item) => {
                const steps = splitKeybinding(item.keybinding!)

                return steps.length === 1 && matchesKeybinding(event, steps[0])
            })

            if (single) {
                event.preventDefault()
                clearChord()
                void runItem(single)
                return
            }

            // A key that opens one or more chords ('g' of 'g a') arms the
            // prefix and waits for the second step.
            const prefix = candidates
                .map((item) => splitKeybinding(item.keybinding!))
                .find((steps) => steps.length === 2 && matchesKeybinding(event, steps[0]))?.[0]

            if (prefix !== undefined) {
                event.preventDefault()
                chordPrefix.current = prefix
                window.clearTimeout(chordTimer.current)
                chordTimer.current = window.setTimeout(clearChord, CHORD_TIMEOUT_MS)
            }
        }

        document.addEventListener('keydown', onKeyDown)

        return () => {
            document.removeEventListener('keydown', onKeyDown)
            clearChord()
        }
    }, [config.keybindings, keybindingItems, open, scopedStatic, scopedDynamic, runItem])

    const rankedStatic = useMemo(
        () => rankStaticItems(scopedStatic, query.trim()),
        [scopedStatic, query],
    )

    const grouped = useMemo(
        () => groupItems(rankedStatic, scopedDynamic, config.groups),
        [rankedStatic, scopedDynamic, config.groups],
    )

    const isEmpty =
        grouped.contextualUngrouped.length === 0 &&
        grouped.contextualGroups.length === 0 &&
        grouped.ungrouped.length === 0 &&
        grouped.groups.length === 0 &&
        grouped.dynamicUngrouped.length === 0 &&
        grouped.dynamicGroups.length === 0

    return (
        <Command.Dialog
            open={open}
            onOpenChange={setOpen}
            shouldFilter={false}
            label="Command menu"
            overlayClassName="fi-spotlight-overlay"
            contentClassName="fi-spotlight"
        >
            {contextActive && context && (
                <div className="fi-spotlight-context">
                    <span className="fi-spotlight-context-chip">
                        {context.badge && (
                            <span className="fi-spotlight-context-badge">{context.badge}</span>
                        )}

                        <span className="fi-spotlight-context-label">{context.label}</span>
                    </span>
                </div>
            )}

            <Command.Input
                value={query}
                onValueChange={setQuery}
                placeholder={config.placeholder}
                onKeyDown={(event) => {
                    if (event.key === 'Backspace' && query === '' && contextActive && context) {
                        event.preventDefault()
                        setContextActive(false)
                    }
                }}
            />

            {/* Contextual (current record/page) commands are pinned on top.
                Static commands render next and server results append below,
                so responses arriving never push the list around. */}
            <Command.List>
                {grouped.contextualUngrouped.map((item) => (
                    <Item key={item.id} item={item} then={config.i18n.then} onSelect={() => runItem(item)} />
                ))}

                {grouped.contextualGroups.map((group) => (
                    <Command.Group key={group.key} heading={group.label}>
                        {group.items.map((item) => (
                            <Item key={item.id} item={item} then={config.i18n.then} onSelect={() => runItem(item)} />
                        ))}
                    </Command.Group>
                ))}

                {grouped.ungrouped.map((item) => (
                    <Item key={item.id} item={item} then={config.i18n.then} onSelect={() => runItem(item)} />
                ))}

                {grouped.groups.map((group) => (
                    <Command.Group key={group.key} heading={group.label}>
                        {group.items.map((item) => (
                            <Item key={item.id} item={item} then={config.i18n.then} onSelect={() => runItem(item)} />
                        ))}
                    </Command.Group>
                ))}

                {grouped.dynamicUngrouped.map((item) => (
                    <Item key={item.id} item={item} then={config.i18n.then} onSelect={() => runItem(item)} />
                ))}

                {grouped.dynamicGroups.map((group) => (
                    <Command.Group key={group.key} heading={group.label}>
                        {group.items.map((item) => (
                            <Item key={item.id} item={item} then={config.i18n.then} onSelect={() => runItem(item)} />
                        ))}
                    </Command.Group>
                ))}

                {loading && <Command.Loading>{config.i18n.loading}</Command.Loading>}

                {!loading && isEmpty && query.trim() !== '' && (
                    <Command.Empty>{config.i18n.empty}</Command.Empty>
                )}
            </Command.List>
        </Command.Dialog>
    )
}

function Item({ item, then, onSelect }: { item: CommandItem; then: string; onSelect: () => void }) {
    return (
        <Command.Item value={item.id} onSelect={onSelect}>
            <span
                className="fi-spotlight-item-icon"
                aria-hidden="true"
                {...(item.icon ? { dangerouslySetInnerHTML: { __html: item.icon } } : {})}
            />

            <span className="fi-spotlight-item-text">
                <span className="fi-spotlight-item-label">{item.label}</span>

                {item.description && (
                    <span className="fi-spotlight-item-description">{item.description}</span>
                )}
            </span>

            {item.keybinding && (
                <span className="fi-spotlight-item-keybinding" aria-hidden="true">
                    {formatKeybinding(item.keybinding).map((step, stepIndex) => (
                        <Fragment key={stepIndex}>
                            {stepIndex > 0 && (
                                <span className="fi-spotlight-item-keybinding-then">{then}</span>
                            )}
                            {step.map((key, index) => (
                                <kbd key={index}>{key}</kbd>
                            ))}
                        </Fragment>
                    ))}
                </span>
            )}
        </Command.Item>
    )
}
