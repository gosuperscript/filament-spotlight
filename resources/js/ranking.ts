import commandScore from 'command-score'

import type { CommandItem, GroupDefinition } from './types'

export function rankStaticItems(items: CommandItem[], query: string): CommandItem[] {
    if (!query) {
        return [...items].sort((a, b) => a.sort - b.sort)
    }

    return items
        .map((item) => ({ item, score: commandScore(item.label, query, item.keywords) }))
        .filter(({ score }) => score > 0)
        .sort((a, b) => b.score - a.score)
        .map(({ item }) => item)
}

export type RenderGroup = {
    key: string
    label: string
    items: CommandItem[]
}

export type GroupedItems = {
    ungrouped: CommandItem[]
    groups: RenderGroup[]
    dynamicUngrouped: CommandItem[]
    dynamicGroups: RenderGroup[]
}

/**
 * Group static and dynamic items for rendering. Static items always come
 * first (ungrouped, then registered groups by sort, then remaining groups in
 * first-seen order); dynamic (server) results render below them so arriving
 * responses never push the static commands around. A dynamic item whose group
 * already exists statically merges into it to avoid duplicate headings.
 * Duplicate IDs keep their first (static) occurrence.
 */
export function groupItems(
    staticItems: CommandItem[],
    dynamicItems: CommandItem[],
    definitions: GroupDefinition[],
): GroupedItems {
    const seen = new Set<string>()
    const ungrouped: CommandItem[] = []
    const dynamicUngrouped: CommandItem[] = []
    const staticGroups = new Map<string, CommandItem[]>()
    const dynamicGroups = new Map<string, CommandItem[]>()

    const push = (groups: Map<string, CommandItem[]>, key: string, item: CommandItem) => {
        const list = groups.get(key) ?? []
        list.push(item)
        groups.set(key, list)
    }

    for (const item of staticItems) {
        if (seen.has(item.id)) continue
        seen.add(item.id)

        item.group ? push(staticGroups, item.group, item) : ungrouped.push(item)
    }

    for (const item of dynamicItems) {
        if (seen.has(item.id)) continue
        seen.add(item.id)

        if (!item.group) {
            dynamicUngrouped.push(item)
        } else if (staticGroups.has(item.group)) {
            push(staticGroups, item.group, item)
        } else {
            push(dynamicGroups, item.group, item)
        }
    }

    const toRenderGroups = (groups: Map<string, CommandItem[]>): RenderGroup[] =>
        [...definitions.map((definition) => definition.name), ...groups.keys()]
            .filter((key, index, keys) => groups.has(key) && keys.indexOf(key) === index)
            .map((key) => ({
                key,
                label: definitions.find((definition) => definition.name === key)?.label ?? key,
                items: groups.get(key)!,
            }))

    return {
        ungrouped,
        groups: toRenderGroups(staticGroups),
        dynamicUngrouped,
        dynamicGroups: toRenderGroups(dynamicGroups),
    }
}
