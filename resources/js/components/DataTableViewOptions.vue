<script setup lang="ts">
import { computed } from 'vue';
import { Settings2, Check } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

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
    groupable?: boolean | string;
    component?: string;
}

const props = defineProps<{
    columns: Column[];
    modelValue: string[]; // Array of hidden column keys
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: string[]): void;
}>();

const toggleableColumns = computed(() => {
    return props.columns.filter(column => column.toggleable);
});

const onToggle = (columnKey: string) => {
    let newHiddenColumns: string[];

    if (props.modelValue.includes(columnKey)) {
        // Becoming visible: remove from hidden
        newHiddenColumns = props.modelValue.filter(key => key !== columnKey);
    } else {
        // Becoming hidden: add
        newHiddenColumns = [...props.modelValue, columnKey];
    }
    
    emit('update:modelValue', newHiddenColumns);
};
</script>

<template>
    <DropdownMenu v-if="toggleableColumns.length > 0">
        <DropdownMenuTrigger asChild>
            <Button
                variant="outline"
                size="sm"
                class="ml-auto hidden h-9 lg:flex"
            >
                <Settings2 class="mr-2 h-4 w-4" />
                View
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-[180px]">
            <DropdownMenuLabel>Toggle Columns</DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem
                v-for="column in toggleableColumns"
                :key="column.key"
                class="capitalize"
                @click.stop.prevent="onToggle(column.key)"
            >
                <div class="mr-2 flex h-4 w-4 items-center justify-center">
                    <Check
                        v-if="!modelValue.includes(column.key)"
                        class="h-4 w-4"
                    />
                </div>
                {{ column.label }}
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
