<script setup lang="ts">
import { X } from 'lucide-vue-next';
import * as LucideIcons from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { BulkAction } from './types';

defineProps<{
    selectedCount: number;
    actions: BulkAction[];
    loading?: boolean;
    selectAllMatching?: boolean;
    totalCount?: number;
}>();

const emit = defineEmits<{
    clear: [];
    action: [action: BulkAction];
}>();

const handleClear = () => {
    emit('clear');
};

const handleAction = (action: BulkAction) => {
    emit('action', action);
};
</script>

<template>
    <div class="fixed bottom-6 left-1/2 z-50 flex -translate-x-1/2 animate-in items-center gap-4 rounded-full border bg-background px-4 py-2 shadow-lg transition-all duration-300 fade-in-0 slide-in-from-bottom-8">
        <Badge variant="secondary" class="rounded-full px-2.5 py-0.5 shadow-none">
            {{ selectAllMatching && totalCount !== undefined ? totalCount : selectedCount }} Selected
        </Badge>
        
        <div class="h-4 w-px bg-border"></div>
        
        <div class="flex items-center gap-1">
            <Button
                v-for="action in actions"
                :key="action.key"
                type="button"
                variant="ghost"
                size="sm"
                class="h-8 rounded-full px-3 text-sm"
                :disabled="loading"
                @click="handleAction(action)"
            >
                <component
                    :is="(LucideIcons as Record<string, any>)[action.icon || '']"
                    v-if="action.icon"
                    class="mr-1.5 h-4 w-4"
                />
                {{ action.label }}
            </Button>
        </div>
        
        <div class="h-4 w-px bg-border"></div>
        
        <Button
            type="button"
            variant="ghost"
            size="icon"
            class="h-8 w-8 rounded-full"
            :disabled="loading"
            @click="handleClear"
            aria-label="Clear selected rows"
        >
            <X class="h-4 w-4" />
        </Button>
    </div>
</template>
