import { Command } from 'cmdk'
import { useCallback, useEffect, useMemo, useRef, useState } from 'react'

import {
    formatKeybinding,
    hasKeybindingModifier,
    isEditableTarget,
    matchesKeybinding,
} from './keybindings'
import { groupItems, rankStaticItems } from './ranking'
import type { Bridge, CommandItem, SpotlightConfig } from './types'

const SEARCH_DEBOUNCE_MS = 250

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

    const staticStale = useRef(false)
    const requestSeq = useRef(0)

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
        }

        document.addEventListener('livewire:navigated', onNavigated)

        return () => document.removeEventListener('livewire:navigated', onNavigated)
    }, [])

    useEffect(() => {
        if (!open) {
            setQuery('')
            setDynamicItems([])
            setLoading(false)
            return
        }

        if (staticItems === null || staticStale.current) {
            staticStale.current = false
            bridge
                .getStaticCommands(window.location.href)
                .then(setStaticItems)
                .catch(() => setStaticItems([]))
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
                .search(trimmed, window.location.href)
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
    }, [query, open])

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

    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            if (config.keybindings.some((binding) => matchesKeybinding(event, binding))) {
                event.preventDefault()
                setOpen((open) => !open)
                return
            }

            if (event.defaultPrevented || event.repeat) return

            // While the menu is open only modifier shortcuts fire — plain
            // keys belong to the search input. While it is closed, item
            // shortcuts work Linear-style anywhere on the page, except that
            // unmodified ones never steal keystrokes from a focused field.
            const candidates = open
                ? [...(staticItems ?? []), ...dynamicItems].filter(
                      (item) => item.keybinding && hasKeybindingModifier(item.keybinding),
                  )
                : config.keybindingItems.filter(
                      (item) =>
                          item.keybinding &&
                          (hasKeybindingModifier(item.keybinding) || !isEditableTarget(event.target)),
                  )

            const item = candidates.find((item) => matchesKeybinding(event, item.keybinding!))

            if (item) {
                event.preventDefault()
                void runItem(item)
            }
        }

        document.addEventListener('keydown', onKeyDown)

        return () => document.removeEventListener('keydown', onKeyDown)
    }, [config.keybindings, config.keybindingItems, open, staticItems, dynamicItems, runItem])

    const rankedStatic = useMemo(
        () => rankStaticItems(staticItems ?? [], query.trim()),
        [staticItems, query],
    )

    const grouped = useMemo(
        () => groupItems(rankedStatic, dynamicItems, config.groups),
        [rankedStatic, dynamicItems, config.groups],
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
            <Command.Input value={query} onValueChange={setQuery} placeholder={config.placeholder} />

            {/* Contextual (current record/page) commands are pinned on top.
                Static commands render next and server results append below,
                so responses arriving never push the list around. */}
            <Command.List>
                {grouped.contextualUngrouped.map((item) => (
                    <Item key={item.id} item={item} onSelect={() => runItem(item)} />
                ))}

                {grouped.contextualGroups.map((group) => (
                    <Command.Group key={group.key} heading={group.label}>
                        {group.items.map((item) => (
                            <Item key={item.id} item={item} onSelect={() => runItem(item)} />
                        ))}
                    </Command.Group>
                ))}

                {grouped.ungrouped.map((item) => (
                    <Item key={item.id} item={item} onSelect={() => runItem(item)} />
                ))}

                {grouped.groups.map((group) => (
                    <Command.Group key={group.key} heading={group.label}>
                        {group.items.map((item) => (
                            <Item key={item.id} item={item} onSelect={() => runItem(item)} />
                        ))}
                    </Command.Group>
                ))}

                {grouped.dynamicUngrouped.map((item) => (
                    <Item key={item.id} item={item} onSelect={() => runItem(item)} />
                ))}

                {grouped.dynamicGroups.map((group) => (
                    <Command.Group key={group.key} heading={group.label}>
                        {group.items.map((item) => (
                            <Item key={item.id} item={item} onSelect={() => runItem(item)} />
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

function Item({ item, onSelect }: { item: CommandItem; onSelect: () => void }) {
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
                    {formatKeybinding(item.keybinding).map((key, index) => (
                        <kbd key={index}>{key}</kbd>
                    ))}
                </span>
            )}
        </Command.Item>
    )
}
