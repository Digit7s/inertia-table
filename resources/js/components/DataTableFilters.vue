<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { debounce } from 'lodash-es';
import { Filter as FilterIcon, X } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';

export interface FilterSchema {
    key: string;
    label: string;
    type: string; // 'text' | 'select' | 'boolean'
    options?: Record<string, string>;
    value: any;
}

const props = defineProps<{
    filters: FilterSchema[];
    query: Record<string, any>;
}>();

// Create reactive state for filters based on current URL query.filter parameters
const filterState = ref<Record<string, any>>({});
const isInitialized = ref(false);

const initializeState = () => {
    // Fill from props
    const currentQueryFilters = props.query.filter || {};
    
    for (const filter of props.filters) {
        if (currentQueryFilters[filter.key] !== undefined) {
             filterState.value[filter.key] = filter.type === 'boolean' 
                 ? (currentQueryFilters[filter.key] === 'true' || currentQueryFilters[filter.key] === true || currentQueryFilters[filter.key] === '1')
                 : currentQueryFilters[filter.key];
        } else {
             // Reset to default
             filterState.value[filter.key] = filter.type === 'boolean' ? false : (filter.type === 'select' ? 'all' : null);
        }
    }
    isInitialized.value = true;
};

initializeState();

// Watch for external prop changes (e.g. going back/forward in history)
watch(() => props.query.filter, (newFilter) => {
    isInitialized.value = false;
    initializeState();
}, { deep: true });

const activeFilterCount = computed(() => {
    return Object.values(filterState.value).filter(val => val !== null && val !== '' && val !== false && val !== 'all').length;
});

const emitUpdate = debounce(() => {
    // Only send non-empty filter values
    const cleanFilters: Record<string, any> = {};
    for (const [key, val] of Object.entries(filterState.value)) {
        if (val !== null && val !== '' && val !== false && val !== 'all') {
            cleanFilters[key] = val;
        }
    }

    router.get(window.location.pathname, {
        ...props.query,
        page: 1, // reset page to 1 when filtering
        filter: cleanFilters,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}, 300);

// Watch for internal state changes and emit update
watch(filterState, () => {
    if (isInitialized.value) {
        emitUpdate();
    }
}, { deep: true });

const resetFilters = () => {
    // Set all to empty/false and emit will pick it up
    for (const filter of props.filters) {
        filterState.value[filter.key] = filter.type === 'boolean' ? false : (filter.type === 'select' ? 'all' : null);
    }
};
</script>

<template>
    <Popover>
        <PopoverTrigger as-child>
            <Button variant="outline" class="gap-2">
                <FilterIcon class="h-4 w-4" />
                Filters
                <Badge
                    v-if="activeFilterCount > 0"
                    variant="secondary"
                    class="ml-1 h-5 w-5 rounded-md px-1 font-normal text-xs"
                >
                    {{ activeFilterCount }}
                </Badge>
            </Button>
        </PopoverTrigger>
        <PopoverContent class="w-80" align="end">
            <div class="grid gap-4">
                <div class="space-y-2">
                    <h4 class="font-medium leading-none">Filters</h4>
                    <p class="text-sm text-muted-foreground">
                        Refine the table data based on the following criteria.
                    </p>
                </div>
                
                <div class="grid gap-4 py-2">
                    <div v-for="filter in filters" :key="filter.key" class="grid gap-2">
                        <Label :for="`filter-${filter.key}`">{{ filter.label }}</Label>
                        
                        <!-- Select Filter -->
                        <Select 
                            v-if="filter.type === 'select'" 
                            v-model="filterState[filter.key]"
                        >
                            <SelectTrigger class="w-full" :id="`filter-${filter.key}`">
                                <SelectValue placeholder="Select..." />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="all">All</SelectItem>
                                    <SelectItem 
                                        v-for="(label, value) in filter.options" 
                                        :key="value" 
                                        :value="String(value)"
                                    >
                                        {{ label }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>

                        <!-- Text Filter -->
                        <Input 
                            v-else-if="filter.type === 'text'"
                            :id="`filter-${filter.key}`"
                            v-model="filterState[filter.key]"
                            placeholder="Search..."
                        />
                        
                        <!-- Boolean Filter -->
                        <div v-else-if="filter.type === 'boolean'" class="flex items-center space-x-2 pt-1">
                            <Switch 
                                :id="`filter-${filter.key}`" 
                                :checked="filterState[filter.key]"
                                @update:checked="filterState[filter.key] = $event"
                            />
                            <Label :for="`filter-${filter.key}`" class="font-normal">Enable</Label>
                        </div>
                    </div>
                </div>

                <div v-if="activeFilterCount > 0" class="flex justify-end pt-2 border-t">
                    <Button variant="ghost" size="sm" @click="resetFilters" class="text-muted-foreground h-8 px-2 lg:px-3">
                        Reset Filters
                        <X class="ml-2 h-4 w-4" />
                    </Button>
                </div>
            </div>
        </PopoverContent>
    </Popover>
</template>
