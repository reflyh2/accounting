<script setup>
import { useForm } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppCheckbox from '@/Components/AppCheckbox.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import { ref, computed } from 'vue';

const props = defineProps({
    priceList: Object,
    companies: Array,
    currencies: Array,
    partnerGroups: Array,
    channels: Object,
    filters: Object,
});

const form = useForm({
    company_id: props.priceList?.company_id || null,
    code: props.priceList?.code || '',
    name: props.priceList?.name || '',
    currency_id: props.priceList?.currency_id || null,
    channel: props.priceList?.channel || null,
    partner_group_id: props.priceList?.partner_group_id || null,
    valid_from: props.priceList?.valid_from || '',
    valid_to: props.priceList?.valid_to || '',
    is_active: props.priceList?.is_active ?? true,
});

const submitted = ref(false);

const companyOptions = computed(() => props.companies.map(c => ({ value: c.id, label: c.name })));
const currencyOptions = computed(() => props.currencies.map(c => ({ value: c.id, label: `${c.code} - ${c.name}` })));
const partnerGroupOptions = computed(() => [
    { value: null, label: 'Semua Grup' },
    ...props.partnerGroups.map(g => ({ value: g.id, label: g.name }))
]);
const channelOptions = computed(() => [
    { value: null, label: 'Semua Kanal' },
    ...Object.entries(props.channels).map(([value, label]) => ({ value, label }))
]);

function submitForm() {
    submitted.value = true;
    
    if (props.priceList) {
        form.put(route('catalog.price-lists.update', props.priceList.id), {
            preserveScroll: true,
            onSuccess: () => { submitted.value = false; },
            onError: () => { submitted.value = false; }
        });
    } else {
        form.post(route('catalog.price-lists.store'), {
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
                <div class="grid grid-cols-2 gap-4">
                    <AppInput
                        v-model="form.code"
                        label="Kode:"
                        :error="form.errors.code"
                        required
                        placeholder="Contoh: PL-001"
                    />
                    
                    <AppInput
                        v-model="form.name"
                        label="Nama:"
                        :error="form.errors.name"
                        required
                        placeholder="Nama daftar harga"
                    />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <AppSelect
                        v-model="form.company_id"
                        :options="companyOptions"
                        label="Perusahaan:"
                        :error="form.errors.company_id"
                        required
                        placeholder="Pilih perusahaan"
                    />
                    
                    <AppSelect
                        v-model="form.currency_id"
                        :options="currencyOptions"
                        label="Mata Uang:"
                        :error="form.errors.currency_id"
                        required
                        placeholder="Pilih mata uang"
                    />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <AppSelect
                        v-model="form.channel"
                        :options="channelOptions"
                        label="Kanal:"
                        :error="form.errors.channel"
                    />
                    
                    <AppSelect
                        v-model="form.partner_group_id"
                        :options="partnerGroupOptions"
                        label="Grup Partner:"
                        :error="form.errors.partner_group_id"
                    />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <AppInput
                        v-model="form.valid_from"
                        label="Berlaku Dari:"
                        :error="form.errors.valid_from"
                        type="date"
                    />
                    
                    <AppInput
                        v-model="form.valid_to"
                        label="Berlaku Sampai:"
                        :error="form.errors.valid_to"
                        type="date"
                    />
                </div>
                
                <div class="mt-4">
                    <AppCheckbox
                        v-model:checked="form.is_active"
                        label="Aktif"
                    />
                </div>
            </div>

            <div class="w-1/3 bg-gray-100 p-4 rounded-lg text-sm">
                <h3 class="text-lg font-semibold mb-2">Informasi</h3>
                <p class="mb-2">Daftar harga menentukan harga produk untuk berbagai konteks.</p>
                <ul class="list-disc list-inside">
                    <li><b>Kanal:</b> Harga khusus untuk kanal tertentu (web, POS, dll)</li>
                    <li><b>Grup Partner:</b> Harga khusus untuk grup partner tertentu</li>
                    <li><b>Periode:</b> Harga berlaku dalam rentang tanggal tertentu</li>
                </ul>
                <p class="mt-2 text-gray-600">Item harga dapat ditambahkan setelah daftar harga dibuat.</p>
            </div>
        </div>
        
        <div class="mt-4 flex items-center">
            <AppPrimaryButton type="submit" class="mr-2">
                {{ props.priceList ? 'Simpan Perubahan' : 'Buat Daftar Harga' }}
            </AppPrimaryButton>
            <AppSecondaryButton @click="$inertia.visit(route('catalog.price-lists.index', filters))">
                Batal
            </AppSecondaryButton>
        </div>
    </form>
</template>
