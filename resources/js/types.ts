export type CommandItem = {
    id: string
    type: 'action' | 'url' | 'dispatch'
    label: string
    description: string | null
    icon: string | null
    group: string | null
    keywords: string[]
    keybinding: string | null
    sort: number
    url: string | null
    openInNewTab: boolean
    event: string | null
    eventArgs: unknown[]
    context: Record<string, unknown>
    contextual: boolean
}

export type GroupDefinition = {
    name: string
    label: string
    sort: number
}

export type ContextChip = {
    badge: string | null
    label: string
}

export type StaticCommandsPayload = {
    context: ContextChip | null
    commands: CommandItem[]
}

export type SpotlightConfig = {
    keybindings: string[]
    keybindingItems: CommandItem[]
    placeholder: string
    spaEnabled: boolean
    groups: GroupDefinition[]
    i18n: {
        empty: string
        loading: string
        then: string
    }
}

export type Bridge = {
    getStaticCommands: (url: string) => Promise<StaticCommandsPayload>
    getKeybindingCommands: (url: string) => Promise<CommandItem[]>
    search: (query: string, url: string | null) => Promise<CommandItem[]>
    execute: (id: string, context: { query: string; url: string }) => Promise<{ redirect?: string } | null>
}
