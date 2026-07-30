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
}

export type GroupDefinition = {
    name: string
    label: string
    sort: number
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
    }
}

export type Bridge = {
    getStaticCommands: () => Promise<CommandItem[]>
    search: (query: string) => Promise<CommandItem[]>
    execute: (id: string, context: Record<string, unknown>) => Promise<{ redirect?: string } | null>
}
