<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Download, ChevronDown } from 'lucide-vue-next';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { ExportAction } from './types';

const props = defineProps<{
    exports: ExportAction[];
    tableClass: string;
    query?: Record<string, any>;
    hiddenColumns?: string[];
}>();

const handleExport = (exportAction: ExportAction) => {
    const params = new URLSearchParams();
    
    // Add the table class identifier
    params.append('table_class', props.tableClass);
    params.append('export_name', exportAction.name);
    
    // Append current table query state (search, filters, sort, etc.)
    if (props.query) {
        Object.entries(props.query).forEach(([key, value]) => {
            if (value !== null && value !== undefined && value !== '') {
                if (typeof value === 'object') {
                    // For filters which might be objects/arrays, JSON stringify or just skip complex ones.
                    // Actually Inertia usually passes query strings as is. Let's serialize arrays properly.
                    if (Array.isArray(value)) {
                        value.forEach(v => params.append(`${key}[]`, v));
                    } else {
                        // Assuming simple object structure like filters[status]=active
                        Object.entries(value).forEach(([subKey, subValue]) => {
                            if (subValue !== null && subValue !== undefined && subValue !== '') {
                                params.append(`${key}[${subKey}]`, String(subValue));
                            }
                        });
                    }
                } else {
                    params.append(key, String(value));
                }
            }
        });
    }

    // Append hidden columns if needed
    if (props.hiddenColumns && props.hiddenColumns.length > 0) {
        params.append('hidden', props.hiddenColumns.join(','));
    }

    const url = `${exportAction.url}?${params.toString()}`;
    window.location.href = url;
};
</script>

<template>
    <div v-if="exports && exports.length > 0">
        <!-- Single export button -->
        <Button
            v-if="exports.length === 1"
            type="button"
            variant="outline"
            size="sm"
            class="ml-auto flex h-9"
            @click="handleExport(exports[0])"
            aria-label="Export table data as CSV"
        >
            <Download class="h-4 w-4 sm:mr-2" />
            <span class="hidden sm:inline">{{ exports[0].label }}</span>
        </Button>

        <!-- Multiple export dropdown -->
        <DropdownMenu v-else>
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="ml-auto flex h-9"
                    aria-label="Export table data options"
                >
                    <Download class="h-4 w-4 sm:mr-2" />
                    <span class="hidden sm:inline">Export</span>
                    <ChevronDown class="ml-2 h-4 w-4 text-muted-foreground hidden sm:block" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-[180px]">
                <DropdownMenuItem
                    v-for="exportAction in exports"
                    :key="exportAction.name"
                    @click.stop.prevent="handleExport(exportAction)"
                    class="cursor-pointer"
                >
                    <Download class="mr-2 h-4 w-4" />
                    {{ exportAction.label }}
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    </div>
</template>
