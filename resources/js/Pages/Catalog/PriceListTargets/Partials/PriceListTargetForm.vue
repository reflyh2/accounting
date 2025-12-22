<script setup>
import { useForm } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppCheckbox from '@/Components/AppCheckbox.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppPopoverSearch from '@/Components/AppPopoverSearch.vue';
import { ref, computed } from 'vue';

const props = defineProps({
    target: Object,
    priceLists: Array,
    companies: Array,
    partnerGroups: Array,
    partnerDisplay: String,
    channels: Object,
    filters: Object,
});

const form = useForm({
    price_list_id: props.target?.price_list_id ?? props.priceLists?.[0]?.id ?? null,
    company_id: props.target?.company_id ?? null,
    partner_id: props.target?.partner_id ?? null,
    partner_group_id: props.target?.partner_group_id ?? null,
    channel: props.target?.channel ?? null,
    priority: props.target?.priority ?? 0,
    is_active: props.target?.is_active ?? true,
    valid_from: props.target?.valid_from ?? '',
    valid_to: props.target?.valid_to ?? '',
});

const submitted = ref(false);

const priceListOptions = computed(() => props.priceLists.map(list => ({ 
    value: list.id, 
    label: `${list.name} (${list.code})` 
})));

const companyOptions = computed(() => [
    { value: null, label: 'Semua Perusahaan' }, 
    ...props.companies.map(c => ({ value: c.id, label: c.name }))
]);

const partnerGroupOptions = computed(() => [
    { value: null, label: 'Semua Grup' }, 
    ...props.partnerGroups.map(g => ({ value: g.id, label: g.name }))
]);

const channelOptions = computed(() => [
    { value: null, label: 'Semua Kanal' },
    ...Object.entries(props.channels).map(([value, label]) => ({ value, label }))
]);

function handlePartnerUpdate(value) {
    form.partner_id = value;
}

function submitForm() {
    submitted.value = true;
    
    if (props.target) {
        form.put(route('catalog.price-list-targets.update', props.target.id), {
            preserveScroll: true,
            onSuccess: () => { submitted.value = false; },
            onError: () => { submitted.value = false; }
        });
    } else {
        form.post(route('catalog.price-list-targets.store'), {
            preserveScroll: true,
            onSuccess: () => { submitted.value = false; },
            onError: () => { submitted.value = false; }
        });
    }
}
</script>

<template>
    <form @submit.prevent="submitForm" class="space-y-4">
        <div class="flex justify-between">
            <div class="w-2/3 max-w-2xl mr-8">
                <AppSelect
                    v-model="form.price_list_id"
                    :options="priceListOptions"
                    label="Daftar Harga:"
                    :error="form.errors.price_list_id"
                    required
                />

                <div class="grid grid-cols-2 gap-4">
                    <AppSelect
                        v-model="form.company_id"
                        :options="companyOptions"
                        label="Perusahaan:"
                        :error="form.errors.company_id"
                    />
                    <AppSelect
                        v-model="form.partner_group_id"
                        :options="partnerGroupOptions"
                        label="Grup Partner:"
                        :error="form.errors.partner_group_id"
                    />
                </div>

                <AppPopoverSearch
                    v-model="form.partner_id"
                    label="Partner (opsional):"
                    :url="route('api.partners')"
                    :displayKeys="['code', 'name']"
                    :tableHeaders="[
                        { key: 'code', label: 'Kode' },
                        { key: 'name', label: 'Nama' },
                    ]"
                    :initialDisplayValue="partnerDisplay"
                    :error="form.errors.partner_id"
                    placeholder="Cari partner"
                    @update:modelValue="handlePartnerUpdate"
                />

                <div class="grid grid-cols-3 gap-4">
                    <AppSelect
                        v-model="form.channel"
                        :options="channelOptions"
                        label="Kanal:"
                        :error="form.errors.channel"
                    />
                    <AppInput
                        v-model="form.priority"
                        type="number"
                        min="0"
                        label="Prioritas:"
                        :error="form.errors.priority"
                    />
                    <div class="flex items-center mt-6">
                        <AppCheckbox v-model:checked="form.is_active" label="Aktif" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <AppInput
                        v-model="form.valid_from"
                        type="date"
                        label="Berlaku Dari:"
                        :error="form.errors.valid_from"
                    />
                    <AppInput
                        v-model="form.valid_to"
                        type="date"
                        label="Berlaku Sampai:"
                        :error="form.errors.valid_to"
                    />
                </div>
            </div>

            <div class="w-1/3 bg-gray-100 p-4 rounded-lg text-sm">
                <h3 class="text-lg font-semibold mb-2">Informasi</h3>
                <p class="mb-2">Target harga menentukan siapa yang menggunakan daftar harga tertentu.</p>
                <ul class="list-disc list-inside">
                    <li><b>Prioritas:</b> Prioritas lebih rendah diproses lebih dulu</li>
                    <li><b>Partner:</b> Target spesifik untuk partner</li>
                    <li><b>Grup Partner:</b> Target untuk grup partner</li>
                    <li><b>Perusahaan:</b> Target untuk perusahaan tertentu</li>
                </ul>
                <p class="mt-2 text-gray-600">Tidak dapat menargetkan partner dan grup partner sekaligus.</p>
            </div>
        </div>
        
        <div class="mt-4 flex items-center">
            <AppPrimaryButton type="submit" class="mr-2">
                {{ props.target ? 'Simpan Perubahan' : 'Buat Target Pelanggan' }}
            </AppPrimaryButton>
            <AppSecondaryButton @click="$inertia.visit(route('catalog.price-list-targets.index', filters))">
                Batal
            </AppSecondaryButton>
        </div>
    </form>
</template>
