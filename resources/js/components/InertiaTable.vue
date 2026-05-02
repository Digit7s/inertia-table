<script setup lang="ts">
import { ref, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import { debounce, get } from 'lodash-es';
import { Badge } from '@/components/ui/badge';
import {
    DropdownMenu,
    DropdownMenuTrigger,
    DropdownMenuContent,
    DropdownMenuItem,
} from '@/components/ui/dropdown-menu';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Input } from '@/components/ui/input';
import {
    ChevronUp,
    ChevronDown,
    Search,
    ChevronLeft,
    ChevronRight,
    MoreHorizontal,
    Check,
    X,
} from 'lucide-vue-next';
import * as LucideIcons from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import DataTableFilters from './DataTableFilters.vue';
import DataTableViewOptions from './DataTableViewOptions.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { computed, onMounted } from 'vue';

interface Column {
    key: string;
    label: string;
    type: string;
    meta?: Record<string, any>;
    sortable: boolean;
    searchable: boolean;
    visible: boolean;
    toggleable: boolean;
    toggledHiddenByDefault: boolean;
    groupable: boolean | string;
    component?: string;
}

interface BulkAction {
    key: string;
    label: string;
    icon?: string;
    requires_confirmation?: boolean;
    confirm_title?: string;
    confirm_description?: string;
}

interface TableData {
    data: any[];
    links: any[];
    meta: {
        current_page: number;
        from: number;
        last_page: number;
        path: string;
        per_page: number;
        to: number;
        total: number;
        table_class?: string;
    };
    columns: Column[];
    filters: any[];
    query: {
        search: string;
        sort: string;
        dir: 'asc' | 'desc';
        group?: string;
        group_dir?: 'asc' | 'desc';
        filter: Record<string, any>;
        perPage?: number;
    };
    groups?: Column[];
    per_page_options?: number[];
    default_per_page?: number;
    bulk_actions?: BulkAction[];
    paginated?: boolean;
}

const props = defineProps<{
    tableData: TableData;
}>();

const emit = defineEmits(['success', 'action']);

const search = ref(props.tableData.query?.search || '');

const updateTable = (params: Record<string, any>) => {
    router.get(
        window.location.pathname,
        {
            ...props.tableData.query,
            ...params,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const perPage = ref(
    props.tableData.query?.perPage?.toString() ||
        props.tableData.default_per_page?.toString() ||
        '15',
);

watch(perPage, (value) => {
    updateTable({ perPage: Number(value), page: 1 });
});

const onSearch = debounce((value: string) => {
    updateTable({ search: value, page: 1 });
}, 300);

watch(search, (value) => {
    onSearch(value);
});

const toggleSort = (column: Column) => {
    if (!column.sortable) return;

    let dir: 'asc' | 'desc' = 'asc';
    if (props.tableData.query?.sort === column.key) {
        dir = props.tableData.query?.dir === 'asc' ? 'desc' : 'asc';
    }

    updateTable({ sort: column.key, dir });
};

const groupedData = computed(() => {
    const groupKey = props.tableData.query.group;
    if (!groupKey) {
        return [{ label: '', rows: props.tableData.data || [] }];
    }

    const groups: Record<string, { label: string; rows: any[] }> = {};
    props.tableData.data.forEach((row) => {
        // Use _group_value if provided by backend, otherwise fall back to raw value
        const val = row._group_value ?? get(row, groupKey);
        const label = val === null || val === undefined || val === '' ? 'No Group' : String(val);

        if (!groups[label]) {
            groups[label] = { label, rows: [] };
        }
        groups[label].rows.push(row);
    });

    return Object.values(groups);
});

const isSearchable = props.tableData.columns.some((c) => c.searchable);

const getBadgeVariant = (column: Column, value: any) => {
    if (column.meta?.variants && typeof column.meta.variants === 'object') {
        const variant = column.meta.variants[value];
        // Translate some common string variants to shadcn's variant map if they differ
        if (variant === 'success') return 'default'; // shadcn usually wraps success in default explicitly inside badge CSS
        if (variant === 'warning') return 'secondary'; // custom
        if (variant === 'danger') return 'destructive';
        if (variant === 'info') return 'secondary';
        return variant || 'secondary';
    }
    return 'secondary';
};

const getBadgeIcon = (column: Column, value: any) => {
    if (
        column.meta?.icons &&
        typeof column.meta.icons === 'object' &&
        column.meta.icons[value]
    ) {
        const iconName = column.meta.icons[value];
        return (LucideIcons as any)[iconName] || null;
    }
    return null;
};

const formatDate = (value: any, type: string) => {
    if (!value) return '';
    const date = new Date(value);
    if (type === 'date') {
        return date.toLocaleDateString();
    }
    return date.toLocaleString();
};

const hiddenColumns = ref<string[]>([]);

const visibleColumns = computed(() => {
    return props.tableData.columns.filter(
        (c) => !hiddenColumns.value.includes(c.key),
    );
});

onMounted(() => {
    const tableId = props.tableData.meta?.table_class || window.location.pathname;

    // Collect columns that are explicitly marked as NOT visible OR toggledHiddenByDefault from the backend
    const defaultHidden = props.tableData.columns
        .filter((c) => c.visible === false || c.toggledHiddenByDefault === true)
        .map((c) => c.key);

    try {
        const stored = localStorage.getItem(`inertia-table-hidden-${tableId}`);
        if (stored) {
            // If we have stored preferences, use them
            hiddenColumns.value = JSON.parse(stored) as string[];
        } else {
            // No stored preferences, use backend defaults
            hiddenColumns.value = defaultHidden;
        }
    } catch {
        hiddenColumns.value = defaultHidden;
    }
});

watch(
    hiddenColumns,
    (newVal) => {
        const tableId = props.tableData.meta?.table_class || window.location.pathname;
        try {
            localStorage.setItem(
                `inertia-table-hidden-${tableId}`,
                JSON.stringify(newVal),
            );
        } catch {
            // Ignore storage errors
        }
    },
    { deep: true },
);

const confirmState = ref({
    open: false,
    url: '',
    method: 'get' as any,
    title: '',
    description: '',
    data: null as any,
});

const selectedIds = ref<(string | number)[]>([]);
const selectAllMatching = ref(false);

const currentPageIds = computed(() => {
    if (!props.tableData.data) return [];
    return props.tableData.data
        .map((row) => row.id)
        .filter((id) => id !== undefined && id !== null);
});

const isAllCurrentPageSelected = computed(() => {
    if (currentPageIds.value.length === 0) return false;
    return currentPageIds.value.every((id) => selectedIds.value.includes(id));
});

const isSomeCurrentPageSelected = computed(() => {
    if (currentPageIds.value.length === 0) return false;
    return (
        currentPageIds.value.some((id) => selectedIds.value.includes(id)) &&
        !isAllCurrentPageSelected.value
    );
});

const toggleSelectAll = (checked: boolean) => {
    if (currentPageIds.value.length === 0) {
        console.error(
            "DATATABLE ERROR: currentPageIds is empty! Your backend is not passing 'id' inside the row data.",
        );
        return;
    }

    if (!checked) {
        // Deselect all on current page
        selectedIds.value = selectedIds.value.filter(
            (id) => !currentPageIds.value.includes(id),
        );
        selectAllMatching.value = false;
    } else {
        // Select all on current page (using Set to prevent duplicates)
        const newIds = new Set(selectedIds.value);
        currentPageIds.value.forEach((id) => newIds.add(id));
        selectedIds.value = Array.from(newIds);
    }
};

const toggleRow = (id: string | number, checked: boolean) => {
    if (id === undefined || id === null) {
        console.error('DATATABLE ERROR: Clicked row has no ID!');
        return;
    }

    if (!checked) {
        const index = selectedIds.value.indexOf(id);
        if (index > -1) {
            selectedIds.value.splice(index, 1);
            selectAllMatching.value = false;
        }
    } else {
        if (!selectedIds.value.includes(id)) {
            selectedIds.value.push(id);
        }
    }
};

watch(selectedIds, (newVal) => {
    if (newVal.length === 0) {
        selectAllMatching.value = false;
    }
});

const executeAction = (actionItem: any, item: any) => {
    emit('action', { key: actionItem.key, item, action: actionItem });
    if (actionItem.requires_confirmation) {
        confirmState.value = {
            open: true,
            url: actionItem.url,
            method: actionItem.method || 'delete',
            title: actionItem.confirm_title || 'Are you absolutely sure?',
            description:
                actionItem.confirm_description ||
                'This action cannot be undone.',
            data: null,
        };
        return;
    }

    if (!actionItem.url) return;

    if (actionItem.method && actionItem.method !== 'get') {
        router.visit(actionItem.url, {
            method: actionItem.method as any,
            preserveScroll: true,
        });
    } else {
        router.visit(actionItem.url, { preserveScroll: true });
    }
};

const executeBulkAction = (actionItem: BulkAction) => {
    const url = '/_inertia-table/bulk-action';

    const payloadData = {
        table_class: props.tableData.meta?.table_class,
        action_key: actionItem.key,
        ids: selectAllMatching.value ? [] : selectedIds.value,
        selectAllMatching: selectAllMatching.value,
        filters: JSON.stringify(props.tableData.query.filter || {}),
        search: props.tableData.query.search || '',
    };

    if (actionItem.requires_confirmation) {
        confirmState.value = {
            open: true,
            url: url,
            method: 'post',
            title: actionItem.confirm_title || 'Are you absolutely sure?',
            description:
                actionItem.confirm_description ||
                'This action cannot be undone.',
            data: payloadData,
        };
        return;
    }

    router.visit(url, {
        method: 'post',
        data: payloadData,
        preserveScroll: true,
        onSuccess: () => {
            selectedIds.value = [];
            selectAllMatching.value = false;
        },
    });
};

const onConfirmPendingAction = () => {
    const options: any = {
        method: confirmState.value.method,
        preserveScroll: true,
        onSuccess: () => {
            if (confirmState.value.data) {
                selectedIds.value = [];
            }
        },
        onFinish: () => {
            confirmState.value.open = false;
        },
    };

    if (confirmState.value.data) {
        options.data = confirmState.value.data;
    }

    router.visit(confirmState.value.url, options);
};
</script>

<template>
    <div class="space-y-4">
        <!-- Actions Bar -->
        <div class="flex items-center justify-between gap-4">
            <div v-if="isSearchable" class="relative w-full max-w-sm">
                <Search
                    class="absolute top-2.5 left-2.5 h-4 w-4 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    type="search"
                    placeholder="Search..."
                    class="h-10 pl-9"
                />
            </div>

            <div class="ml-auto flex items-center gap-2">
                <DataTableFilters
                    v-if="tableData.filters && tableData.filters.length > 0"
                    :filters="tableData.filters"
                    :query="tableData.query"
                />



                <DataTableViewOptions
                    :columns="tableData.columns"
                    v-model="hiddenColumns"
                />
            </div>
        </div>

        <!-- Table -->
        <div class="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead
                            v-if="
                                tableData.bulk_actions &&
                                tableData.bulk_actions.length > 0
                            "
                            class="w-[50px]"
                        >
                            <input
                                type="checkbox"
                                class="h-4 w-4 cursor-pointer rounded border-gray-300 text-primary focus:ring-primary"
                                :checked="isAllCurrentPageSelected"
                                :indeterminate.prop="isSomeCurrentPageSelected"
                                @change="
                                    toggleSelectAll(
                                        ($event.target as HTMLInputElement)
                                            .checked,
                                    )
                                "
                                aria-label="Select all"
                            />
                        </TableHead>
                        <TableHead
                            v-for="column in visibleColumns"
                            :key="column.key"
                            :class="[
                                column.sortable && 'cursor-pointer select-none',
                            ]"
                            @click="toggleSort(column)"
                        >
                            <div class="flex items-center gap-2">
                                {{ column.label }}
                                <template v-if="column.sortable">
                                    <div class="flex flex-col">
                                        <ChevronUp
                                            class="-mb-1 h-3 w-3"
                                            :class="
                                                tableData.query?.sort ===
                                                    column.key &&
                                                tableData.query?.dir === 'asc'
                                                    ? 'text-primary'
                                                    : 'text-muted-foreground/30'
                                            "
                                        />
                                        <ChevronDown
                                            class="h-3 w-3"
                                            :class="
                                                tableData.query?.sort ===
                                                    column.key &&
                                                tableData.query?.dir === 'desc'
                                                    ? 'text-primary'
                                                    : 'text-muted-foreground/30'
                                            "
                                        />
                                    </div>
                                </template>
                            </div>
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-if="
                            isAllCurrentPageSelected &&
                            tableData.meta.total > tableData.data.length
                        "
                        class="border-b bg-muted/50"
                    >
                        <TableCell
                            :colspan="
                                visibleColumns.length +
                                (tableData.bulk_actions &&
                                tableData.bulk_actions.length > 0
                                    ? 1
                                    : 0)
                            "
                            class="h-10 py-2 text-center text-sm"
                        >
                            <template v-if="selectAllMatching">
                                All
                                <span class="font-bold">{{
                                    tableData.meta.total
                                }}</span>
                                items are selected.
                                <button
                                    type="button"
                                    class="ml-1 font-medium text-primary hover:underline focus:outline-none"
                                    @click="
                                        selectedIds = [];
                                        selectAllMatching = false;
                                    "
                                >
                                    Clear selection
                                </button>
                            </template>
                            <template v-else>
                                All
                                <span class="font-bold">{{
                                    selectedIds.length
                                }}</span>
                                items on this page are selected.
                                <button
                                    type="button"
                                    class="ml-1 font-medium text-primary hover:underline focus:outline-none"
                                    @click="selectAllMatching = true"
                                >
                                    Select all
                                    <span class="font-bold">{{
                                        tableData.meta.total
                                    }}</span>
                                    items
                                </button>
                            </template>
                        </TableCell>
                    </TableRow>
                    <template v-for="group in groupedData" :key="group.label">
                        <!-- Group Header Row -->
                        <TableRow
                            v-if="tableData.query.group"
                            class="bg-muted/30 hover:bg-muted/40"
                        >
                            <TableCell
                                :colspan="
                                    visibleColumns.length +
                                    (tableData.bulk_actions &&
                                    tableData.bulk_actions.length > 0
                                        ? 1
                                        : 0)
                                "
                                class="py-2"
                            >
                                <div class="flex items-center gap-2">
                                    <span
                                        class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                    >
                                        {{
                                            tableData.groups?.find(
                                                (g) =>
                                                    g.key ===
                                                    tableData.query.group,
                                            )?.label
                                        }}:
                                    </span>
                                    <span class="text-sm font-bold">{{
                                        group.label
                                    }}</span>
                                    <Badge
                                        variant="secondary"
                                        class="ml-auto h-5 px-1.5 text-[10px]"
                                    >
                                        {{ group.rows.length }} Records
                                    </Badge>
                                </div>
                            </TableCell>
                        </TableRow>

                        <!-- Group Rows -->
                        <TableRow
                            v-for="(row, index) in group.rows"
                            :key="row.id || index"
                            :class="[
                                row._row_link
                                    ? 'cursor-pointer hover:bg-muted/50'
                                    : '',
                                selectedIds.includes(row.id) ? 'bg-muted/50' : '',
                            ]"
                            @click="
                                row._row_link
                                    ? router.visit(row._row_link)
                                    : null
                            "
                        >
                            <TableCell
                                v-if="
                                    tableData.bulk_actions &&
                                    tableData.bulk_actions.length > 0
                                "
                                @click.stop
                            >
                                <input
                                    type="checkbox"
                                    class="h-4 w-4 cursor-pointer rounded border-gray-300 text-primary focus:ring-primary"
                                    :checked="selectedIds.includes(row.id)"
                                    @change="
                                        toggleRow(
                                            row.id,
                                            (
                                                $event.target as HTMLInputElement
                                            ).checked,
                                        )
                                    "
                                    @click.stop
                                    aria-label="Select row"
                                />
                            </TableCell>
                            <TableCell
                                v-for="column in visibleColumns"
                                :key="column.key"
                                :class="[
                                    column.meta?.align === 'right'
                                        ? 'text-right'
                                        : '',
                                ]"
                            >
                                <slot :name="`cell-${column.key}`" :item="row">
                                    <!-- Render logic same as before -->
                                    <template v-if="column.type === 'badge'">
                                        <Badge
                                            :variant="
                                                getBadgeVariant(
                                                    column,
                                                    get(row, column.key),
                                                )
                                            "
                                            class="gap-1.5"
                                        >
                                            <component
                                                :is="
                                                    getBadgeIcon(
                                                        column,
                                                        get(row, column.key),
                                                    )
                                                "
                                                v-if="
                                                    getBadgeIcon(
                                                        column,
                                                        get(row, column.key),
                                                    )
                                                "
                                                class="h-3 w-3"
                                            />
                                            {{ get(row, column.key) }}
                                        </Badge>
                                    </template>

                                    <template v-else-if="column.type === 'boolean'">
                                        <div
                                            class="flex items-center gap-2"
                                            :class="[
                                                column.meta?.align === 'right'
                                                    ? 'justify-end'
                                                    : '',
                                            ]"
                                        >
                                            <Check
                                                v-if="get(row, column.key)"
                                                class="h-4 w-4 text-green-500"
                                            />
                                            <X
                                                v-else
                                                class="h-4 w-4 text-red-500"
                                            />
                                            <span
                                                v-if="
                                                    get(row, column.key) &&
                                                    column.meta?.true_label
                                                "
                                                >{{ column.meta.true_label }}</span
                                            >
                                            <span
                                                v-else-if="
                                                    !get(row, column.key) &&
                                                    column.meta?.false_label
                                                "
                                                >{{ column.meta.false_label }}</span
                                            >
                                        </div>
                                    </template>

                                    <template v-else-if="column.type === 'tags'">
                                        <div
                                            class="flex flex-wrap gap-1"
                                            :class="[
                                                column.meta?.align === 'right'
                                                    ? 'justify-end'
                                                    : '',
                                            ]"
                                        >
                                            <Badge
                                                v-for="(tag, i) in get(
                                                    row,
                                                    column.key,
                                                ) || []"
                                                :key="tag.id || i"
                                                variant="outline"
                                                class="h-5 bg-muted/50 text-[10px]"
                                                :class="column.meta?.badge_class"
                                            >
                                                {{
                                                    column.meta?.label_key
                                                        ? get(
                                                              tag,
                                                              column.meta.label_key,
                                                          )
                                                        : tag
                                                }}
                                            </Badge>
                                            <span
                                                v-if="!get(row, column.key)?.length"
                                                class="text-xs text-muted-foreground italic"
                                                >None</span
                                            >
                                        </div>
                                    </template>

                                    <template v-else-if="column.type === 'image'">
                                        <template
                                            v-if="
                                                get(row, column.key) ||
                                                column.meta?.default_url
                                            "
                                        >
                                            <img
                                                :src="
                                                    get(row, column.key) ||
                                                    column.meta?.default_url
                                                "
                                                :alt="
                                                    column.meta?.alt || column.label
                                                "
                                                :title="column.meta?.title"
                                                class="object-cover"
                                                :class="[
                                                    column.meta?.class,
                                                    column.meta?.circular
                                                        ? 'rounded-full'
                                                        : column.meta?.rounded
                                                          ? 'rounded-md'
                                                          : '',
                                                    column.meta?.size_class ||
                                                        (!column.meta?.width &&
                                                        !column.meta?.height
                                                            ? 'h-10 w-10'
                                                            : ''),
                                                ]"
                                                :style="{
                                                    width: column.meta?.width,
                                                    height: column.meta?.height,
                                                }"
                                            />
                                        </template>
                                    </template>

                                    <template v-else-if="column.type === 'action'">
                                        <div
                                            class="flex items-center gap-2"
                                            :class="[
                                                column.meta?.align === 'right'
                                                    ? 'justify-end'
                                                    : '',
                                            ]"
                                            @click.stop
                                        >
                                            <slot name="actions" :item="row">
                                                <DropdownMenu
                                                    v-if="
                                                        row._actions &&
                                                        row._actions.length > 0
                                                    "
                                                >
                                                    <DropdownMenuTrigger asChild>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                        >
                                                            <MoreHorizontal
                                                                class="h-4 w-4"
                                                            />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent
                                                        align="end"
                                                    >
                                                        <template
                                                            v-for="(
                                                                actionItem, i
                                                            ) in row._actions"
                                                            :key="
                                                                actionItem.key || i
                                                            "
                                                        >
                                                            <DropdownMenuItem
                                                                @click="
                                                                    executeAction(
                                                                        actionItem,
                                                                        row,
                                                                    )
                                                                "
                                                                class="flex w-full cursor-pointer items-center gap-2"
                                                            >
                                                                <component
                                                                    :is="
                                                                        (
                                                                            LucideIcons as any
                                                                        )[
                                                                            actionItem
                                                                                .icon
                                                                        ]
                                                                    "
                                                                    v-if="
                                                                        actionItem.icon
                                                                    "
                                                                    class="h-4 w-4"
                                                                />
                                                                {{
                                                                    actionItem.label
                                                                }}
                                                            </DropdownMenuItem>
                                                        </template>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                                <Button
                                                    v-else
                                                    variant="ghost"
                                                    size="icon"
                                                >
                                                    <MoreHorizontal
                                                        class="h-4 w-4"
                                                    />
                                                </Button>
                                            </slot>
                                        </div>
                                    </template>

                                    <template
                                        v-else-if="
                                            column.type === 'date' ||
                                            column.type === 'datetime'
                                        "
                                    >
                                        {{
                                            formatDate(
                                                get(row, column.key),
                                                column.type,
                                            )
                                        }}
                                    </template>

                                    <template v-else>
                                        {{ get(row, column.key) }}
                                    </template>
                                </slot>
                            </TableCell>
                        </TableRow>
                    </template>
                    <TableRow v-if="tableData.data.length === 0">
                        <TableCell
                            :colspan="
                                visibleColumns.length +
                                (tableData.bulk_actions &&
                                tableData.bulk_actions.length > 0
                                    ? 1
                                    : 0)
                            "
                            class="h-24 text-center"
                        >
                            No results.
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <!-- Pagination -->
        <div
            v-if="tableData.paginated !== false"
            class="flex flex-col items-center justify-between gap-4 px-2 sm:flex-row"
        >
            <div class="flex items-center gap-2">
                <p
                    class="text-sm font-medium whitespace-nowrap text-muted-foreground"
                >
                    Rows per page
                </p>
                <Select v-if="tableData.per_page_options" v-model="perPage">
                    <SelectTrigger class="h-8 w-[70px]">
                        <SelectValue
                            :placeholder="
                                tableData.default_per_page?.toString()
                            "
                        />
                    </SelectTrigger>
                    <SelectContent side="top">
                        <SelectItem
                            v-for="pageSize in tableData.per_page_options"
                            :key="pageSize"
                            :value="pageSize.toString()"
                        >
                            {{ pageSize }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="flex items-center gap-4 sm:ml-auto">
                <div
                    class="hidden text-sm whitespace-nowrap text-muted-foreground sm:block"
                >
                    Showing {{ tableData.meta.from || 0 }} to
                    {{ tableData.meta.to || 0 }} of
                    {{ tableData.meta.total }} entries
                </div>
                <!-- Controls -->
                <div class="flex items-center space-x-2">
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="tableData.meta.current_page === 1"
                        @click="
                            updateTable({
                                page: tableData.meta.current_page - 1,
                            })
                        "
                    >
                        <ChevronLeft class="h-4 w-4" />
                    </Button>
                    <div class="flex items-center gap-1">
                        <template
                            v-for="link in tableData.links"
                            :key="link.label"
                        >
                            <Button
                                v-if="!isNaN(Number(link.label))"
                                variant="outline"
                                size="sm"
                                :class="
                                    link.active &&
                                    'bg-primary text-primary-foreground hover:bg-primary/90'
                                "
                                @click="
                                    updateTable({ page: Number(link.label) })
                                "
                            >
                                {{ link.label }}
                            </Button>
                            <span v-else-if="link.label === '...'" class="px-2"
                                >...</span
                            >
                        </template>
                    </div>
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="
                            tableData.meta.current_page ===
                            tableData.meta.last_page
                        "
                        @click="
                            updateTable({
                                page: tableData.meta.current_page + 1,
                            })
                        "
                    >
                        <ChevronRight class="h-4 w-4" />
                    </Button>
                </div>
            </div>
        </div>

        <!-- Floating Bulk Action Bar -->
        <div
            v-if="
                selectedIds.length > 0 &&
                tableData.bulk_actions &&
                tableData.bulk_actions.length > 0
            "
            class="fixed bottom-6 left-1/2 z-50 flex -translate-x-1/2 animate-in items-center gap-4 rounded-full border bg-background px-4 py-2 shadow-lg transition-all duration-300 fade-in-0 slide-in-from-bottom-8"
        >
            <Badge
                variant="secondary"
                class="rounded-full px-2.5 py-0.5 shadow-none"
                >{{
                    selectAllMatching
                        ? tableData.meta.total
                        : selectedIds.length
                }}
                Selected</Badge
            >
            <div class="h-4 w-px bg-border"></div>
            <div class="flex items-center gap-1">
                <Button
                    v-for="action in tableData.bulk_actions"
                    :key="action.key"
                    variant="ghost"
                    size="sm"
                    class="h-8 rounded-full px-3 text-sm"
                    @click="executeBulkAction(action)"
                >
                    <component
                        :is="(LucideIcons as any)[action.icon]"
                        v-if="action.icon"
                        class="mr-1.5 h-4 w-4"
                    />
                    {{ action.label }}
                </Button>
            </div>
            <div class="h-4 w-px bg-border"></div>
            <Button
                variant="ghost"
                size="icon"
                class="h-8 w-8 rounded-full"
                @click="selectedIds = []"
            >
                <X class="h-4 w-4" />
            </Button>
        </div>

        <!-- Confirmation Dialog -->
        <ConfirmDialog
            v-model:open="confirmState.open"
            :title="confirmState.title"
            :description="confirmState.description"
            variant="destructive"
            @confirm="onConfirmPendingAction"
        />
    </div>
</template>
