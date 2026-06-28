export interface Column {
    key: string;
    label: string;
    type: string;
    meta?: Record<string, unknown>;
    sortable: boolean;
    searchable: boolean;
    visible: boolean;
    toggleable: boolean;
    toggledHiddenByDefault: boolean;
    groupable?: boolean | string;
    component?: string;
}

export interface BulkAction {
    key: string;
    label: string;
    icon?: string;
    requires_confirmation?: boolean;
    confirm_title?: string;
    confirm_description?: string;
}

export interface ExportAction {
    name: string;
    label: string;
    filename: string;
    url: string;
}

export interface PaginationMeta {
    current_page: number;
    from: number | null;
    last_page: number;
    path: string;
    url?: string;
    per_page: number;
    to: number | null;
    total: number;
    table_class?: string;
    bulk_action_url?: string;
    exports?: ExportAction[];
}

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface TableData {
    data: Record<string, unknown>[];
    links: PaginationLink[];
    meta: PaginationMeta;
    columns: Column[];
    filters: FilterSchema[];
    query: {
        search: string;
        sort: string;
        dir: 'asc' | 'desc';
        group?: string;
        group_dir?: 'asc' | 'desc';
        filter: Record<string, unknown>;
        perPage?: number;
    };
    groups?: Column[];
    per_page_options?: number[];
    default_per_page?: number;
    bulk_actions?: BulkAction[];
    paginated?: boolean;
}

export interface FilterSchema {
    key: string;
    label: string;
    type: string; // 'text' | 'select' | 'boolean'
    options?: Record<string, string>;
    value: unknown;
}
