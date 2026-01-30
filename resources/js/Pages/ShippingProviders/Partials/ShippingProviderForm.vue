<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppTextarea from '@/Components/AppTextarea.vue';
import AppCheckbox from '@/Components/AppCheckbox.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppUtilityButton from '@/Components/AppUtilityButton.vue';

const props = defineProps({
    shippingProvider: Object,
    typeOptions: Object,
    filters: Object,
});

const form = useForm({
    code: props.shippingProvider?.code || '',
    name: props.shippingProvider?.name || '',
    type: props.shippingProvider?.type || 'external',
    contact_person: props.shippingProvider?.contact_person || '',
    phone: props.shippingProvider?.phone || '',
    email: props.shippingProvider?.email || '',
    address: props.shippingProvider?.address || '',
    notes: props.shippingProvider?.notes || '',
    is_active: props.shippingProvider?.is_active ?? true,
    create_another: false,
});

const submitted = ref(false);

const typeSelectOptions = Object.entries(props.typeOptions).map(([value, label]) => ({
    value,
    label
}));

function resetForm() {
    form.reset();
    form.clearErrors();
    // Ensure is_active returns to default true for new records
    form.is_active = true;
}

function submitForm(createAnother = false) {
    submitted.value = true;
    form.create_another = createAnother;
    if (props.shippingProvider) {
        form.put(route('shipping-providers.update', props.shippingProvider.id), {
            preserveScroll: true,
            onSuccess: () => {
                submitted.value = false;
            },
            onError: () => {
                submitted.value = false;
            }
        });
    } else {
        form.post(route('shipping-providers.store'), {
            preserveScroll: true,
            onSuccess: () => {
                submitted.value = false;
                if (createAnother) {
                    resetForm();
                }
            },
            onError: () => {
                submitted.value = false;
            }
        });
    }
}
</script>

<template>
    <div class="flex justify-between">
        <form @submit.prevent="submitForm(false)" class="w-2/3 max-w-2xl mr-8">
            <AppInput
                v-if="props.shippingProvider"
                v-model="form.code"
                label="Kode:"
                placeholder="Kode penyedia"
                :error="form.errors.code"
                required
                disabled
                hint="Kode tidak dapat diubah"
            />

            <AppInput
                v-model="form.name"
                label="Nama Penyedia:"
                placeholder="Masukkan nama penyedia pengiriman"
                :error="form.errors.name"
                autofocus
                required
            />

            <AppSelect
                v-model="form.type"
                label="Tipe Penyedia:"
                :options="typeSelectOptions"
                placeholder="Pilih tipe"
                :error="form.errors.type"
                required
            />

            <div class="grid grid-cols-2 gap-4">
                <AppInput
                    v-model="form.contact_person"
                    label="Nama Kontak:"
                    placeholder="Nama person in charge"
                    :error="form.errors.contact_person"
                />

                <AppInput
                    v-model="form.phone"
                    label="Telepon:"
                    placeholder="Nomor telepon"
                    :error="form.errors.phone"
                />
            </div>

            <AppInput
                v-model="form.email"
                label="Email:"
                type="email"
                placeholder="email@example.com"
                :error="form.errors.email"
            />

            <AppTextarea
                v-model="form.address"
                label="Alamat:"
                placeholder="Masukkan alamat lengkap"
                :error="form.errors.address"
                :rows="3"
            />

            <AppTextarea
                v-model="form.notes"
                label="Catatan:"
                placeholder="Catatan tambahan (opsional)"
                :error="form.errors.notes"
                :rows="3"
            />

            <div class="mb-4">
                <AppCheckbox
                    v-model="form.is_active"
                    label="Status Aktif"
                    :error="form.errors.is_active"
                />
                <p class="text-xs text-gray-500 mt-1">Centang untuk mengaktifkan penyedia pengiriman ini</p>
            </div>

            <div class="mt-6 flex items-center">
                <AppPrimaryButton type="submit" class="mr-2" :disabled="submitted">
                    {{ props.shippingProvider ? 'Ubah' : 'Tambah' }} Penyedia
                </AppPrimaryButton>
                <AppUtilityButton v-if="!props.shippingProvider" type="button" @click="submitForm(true)" class="mr-2" :disabled="submitted">
                    Tambah & Buat Lagi
                </AppUtilityButton>
                <AppSecondaryButton @click="$inertia.visit(route('shipping-providers.index', filters))">
                    Batal
                </AppSecondaryButton>
            </div>
        </form>

        <div class="w-1/3 bg-gray-100 p-4 rounded-lg text-sm">
            <h3 class="text-lg font-semibold mb-2">Informasi Penyedia Pengiriman</h3>
            <p class="mb-3">Kelola data penyedia jasa pengiriman untuk transaksi penjualan.</p>

            <div class="mb-3">
                <p class="font-medium mb-1">Tipe Penyedia:</p>
                <ul class="list-disc list-inside space-y-1 text-xs">
                    <li><strong>Internal:</strong> Kurir atau armada pengiriman sendiri</li>
                    <li><strong>Eksternal:</strong> Jasa pengiriman pihak ketiga (JNE, JNT, dll)</li>
                </ul>
            </div>

            <div>
                <p class="font-medium mb-1">Tips:</p>
                <ul class="list-disc list-inside text-xs space-y-1">
                    <li>Nama harus jelas dan mudah dikenali</li>
                    <li>Isi informasi kontak untuk koordinasi</li>
                    <li>Nonaktifkan jika tidak digunakan lagi</li>
                </ul>
            </div>
        </div>
    </div>
</template>
