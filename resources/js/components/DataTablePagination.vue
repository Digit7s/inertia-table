<script setup lang="ts">
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { PaginationMeta, PaginationLink } from './types';

const props = defineProps<{
    meta: PaginationMeta;
    links: PaginationLink[];
    perPage: string | number;
    perPageOptions?: number[];
    defaultPerPage?: number;
}>();

const emit = defineEmits<{
    'update:perPage': [value: string];
    'page-change': [page: number];
    'per-page-change': [value: number];
}>();

const handlePerPageChange = (value: string) => {
    emit('update:perPage', value);
    emit('per-page-change', Number(value));
};

const handlePageChange = (page: number) => {
    emit('page-change', page);
};
</script>

<template>
    <div class="flex flex-col items-center justify-between gap-4 px-2 sm:flex-row">
        <div class="flex items-center gap-2">
            <p class="text-sm font-medium whitespace-nowrap text-muted-foreground">
                Rows per page
            </p>
            <Select v-if="perPageOptions" :model-value="String(perPage)"
                @update:model-value="(v) => handlePerPageChange(String(v))">
                <SelectTrigger class="h-8 w-[70px]" aria-label="Rows per page">
                    <SelectValue :placeholder="defaultPerPage?.toString()" />
                </SelectTrigger>
                <SelectContent side="top">
                    <SelectItem v-for="pageSize in perPageOptions" :key="pageSize" :value="pageSize.toString()">
                        {{ pageSize }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <div class="flex items-center gap-4 sm:ml-auto">
            <div class="hidden text-sm whitespace-nowrap text-muted-foreground sm:block">
                Showing {{ meta.from || 0 }} to {{ meta.to || 0 }} of {{ meta.total }} entries
            </div>
            <!-- Controls -->
            <div class="flex items-center space-x-2">
                <Button type="button" variant="outline" size="sm" :disabled="meta.current_page === 1"
                    @click="handlePageChange(meta.current_page - 1)" aria-label="Previous page">
                    <ChevronLeft class="h-4 w-4" />
                </Button>
                <div class="flex items-center gap-1">
                    <template v-for="link in links" :key="link.label">
                        <Button v-if="!isNaN(Number(link.label))" type="button" variant="outline" size="sm" :class="link.active
                                ? 'border-primary bg-primary text-primary-foreground hover:bg-primary hover:text-primary-foreground focus:bg-primary focus:text-primary-foreground'
                                : ''
                            " @click="!link.active && handlePageChange(Number(link.label))"
                            :aria-label="link.active ? `Current page, page ${link.label}` : `Go to page ${link.label}`"
                            :aria-current="link.active ? 'page' : undefined"
                            :aria-disabled="link.active ? 'true' : undefined">
                            {{ link.label }}
                        </Button>
                        <span v-else-if="link.label === '...'" class="px-2" aria-hidden="true">...</span>
                    </template>
                </div>
                <Button type="button" variant="outline" size="sm" :disabled="meta.current_page === meta.last_page"
                    @click="handlePageChange(meta.current_page + 1)" aria-label="Next page">
                    <ChevronRight class="h-4 w-4" />
                </Button>
            </div>
        </div>
    </div>
</template>
