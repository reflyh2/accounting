<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppCheckbox from '@/Components/AppCheckbox.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppPopoverSearch from '@/Components/AppPopoverSearch.vue';
import { computed } from 'vue';

const props = defineProps({
    mode: String,
    target: Object,
    priceLists: Array,
    companies: Array,
    partnerGroups: Array,
    partnerDisplay: String,
});

const form = useForm({
    price_list_id: props.target?.price_list_id ?? props.priceLists?.[0]?.id ?? null,
    company_id: props.target?.company_id ?? null,
    partner_id: props.target?.partner_id ?? null,
    partner_group_id: props.target?.partner_group_id ?? null,
    channel: props.target?.channel ?? '',
    priority: props.target?.priority ?? 0,
    is_active: props.target?.is_active ?? true,
    valid_from: props.target?.valid_from ?? '',
    valid_to: props.target?.valid_to ?? '',
});

const heading = computed(() => (props.mode === 'edit' ? 'Edit Target' : 'Create Target'));

function submit() {
    if (props.mode === 'edit') {
        form.put(route('catalog.price-list-targets.update', props.target.id));
    } else {
        form.post(route('catalog.price-list-targets.store'));
    }
}

function handlePartnerUpdate(value) {
    form.partner_id = value;
}
</script>

<template>
    <Head :title="heading" />

    <AuthenticatedLayout>
        <template #header>
            <h2>{{ heading }}</h2>
        </template>

        <div class="bg-white shadow-sm sm:rounded border border-gray-200 p-4 max-w-3xl">
            <form class="space-y-4" @submit.prevent="submit">
                <AppSelect
                    v-model="form.price_list_id"
                    :options="priceLists.map(list => ({ value: list.id, label: `${list.name} (${list.code})` }))"
                    label="Price List"
                    :error="form.errors.price_list_id"
                    required
                />

                <div class="grid md:grid-cols-2 gap-4">
                    <AppSelect
                        v-model="form.company_id"
                        :options="[{ value: null, label: 'All Companies' }, ...companies.map(c => ({ value: c.id, label: c.name }))]"
                        label="Company"
                        :error="form.errors.company_id"
                    />
                    <AppSelect
                        v-model="form.partner_group_id"
                        :options="[{ value: null, label: 'All Groups' }, ...partnerGroups.map(g => ({ value: g.id, label: g.name }))]"
                        label="Partner Group"
                        :error="form.errors.partner_group_id"
                    />
                </div>

                <AppPopoverSearch
                    v-model="form.partner_id"
                    label="Partner"
                    :url="route('api.partners')"
                    :displayKeys="['code', 'name']"
                    :tableHeaders="[
                        { key: 'code', label: 'Code' },
                        { key: 'name', label: 'Name' },
                    ]"
                    :initialDisplayValue="partnerDisplay"
                    :error="form.errors.partner_id"
                    placeholder="Search partner (optional)"
                    @update:modelValue="handlePartnerUpdate"
                />

                <div class="grid md:grid-cols-3 gap-4">
                    <AppInput
                        v-model="form.channel"
                        label="Channel"
                        placeholder="e.g. web, POS"
                        :error="form.errors.channel"
                    />
                    <AppInput
                        v-model="form.priority"
                        type="number"
                        min="0"
                        label="Priority"
                        :error="form.errors.priority"
                    />
                    <div class="flex items-center mt-6">
                        <AppCheckbox v-model="form.is_active" label="Active" />
                    </div>
                </div>

                <div class="grid md:grid-cols-2 gap-4">
                    <AppInput
                        v-model="form.valid_from"
                        type="date"
                        label="Valid From"
                        :error="form.errors.valid_from"
                    />
                    <AppInput
                        v-model="form.valid_to"
                        type="date"
                        label="Valid To"
                        :error="form.errors.valid_to"
                    />
                </div>

                <div class="flex items-center gap-2">
                    <AppPrimaryButton type="submit">
                        Save
                    </AppPrimaryButton>
                    <AppSecondaryButton :href="route('catalog.price-list-targets.index')" as="a">
                        Cancel
                    </AppSecondaryButton>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>

