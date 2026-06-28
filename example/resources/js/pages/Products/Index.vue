<script setup lang="ts">
import InertiaTable from '@/components/inertia-table/InertiaTable.vue';
import type { TableData } from '@/components/inertia-table/types';
import { Head } from '@inertiajs/vue3';

defineProps<{
    products: Record<string, any>;
}>();
</script>

<template>

    <Head title="Products" />
    <div class="container mx-auto space-y-6 p-6">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Products</h1>
            <p class="text-muted-foreground">
                Laravel 13 playground demo for digit7s/inertia-table.
            </p>
        </div>

        <InertiaTable :table-data="products as any" />
    </div>
</template>
