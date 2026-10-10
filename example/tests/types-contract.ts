import type {
    Column,
    FilterSchema,
    TableData,
    TableRow,
} from '../../resources/js/components/types';

const searchableColumn: Column = {
    key: 'company.name',
    label: 'Company',
    type: 'text',
    meta: {},
    sortable: true,
    searchable: true,
    visible: true,
    toggleable: true,
    toggledHiddenByDefault: false,
    groupable: false,
    component: null,
};

const invalidSearchableMetadata: Column = {
    ...searchableColumn,
    // @ts-expect-error Search field names stay server-side; this payload flag is boolean.
    searchable: ['companies.name'],
};

const filter: FilterSchema = {
    key: 'status',
    label: 'Status',
    type: 'select',
    options: { active: 'Active' },
    value: null,
};

const row: TableRow = {
    id: 1,
    _column_values: { 'company.name': 'Acme' },
    _row_link: null,
    _actions: [],
};

const payload: TableData = {
    data: [row],
    links: [{ url: null, label: '1', active: true }],
    meta: {
        current_page: 1,
        from: null,
        last_page: 1,
        path: '/companies',
        per_page: 15,
        to: null,
        total: 0,
        table_class: 'companies',
    },
    columns: [searchableColumn],
    filters: [filter],
    query: {
        search: '',
        sort: '',
        dir: 'asc',
        group: null,
        group_dir: null,
        filter: {},
        perPage: 15,
    },
    paginated: true,
};

void invalidSearchableMetadata;
void payload;
