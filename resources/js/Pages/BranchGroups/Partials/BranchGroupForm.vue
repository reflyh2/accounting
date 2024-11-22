<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppUtilityButton from '@/Components/AppUtilityButton.vue';

const props = defineProps({
    branchGroup: Object,
    filters: Object,
    companies: Array, // Add this prop
});

const form = useForm({
    name: props.branchGroup?.name || '',
    company_id: props.branchGroup?.company_id || '',
    create_another: false,
});

const submitted = ref(false);

function submitForm() {
    submitted.value = true;
    if (props.branchGroup) {
        form.put(route('branch-groups.update', props.branchGroup.id), {
            preserveScroll: true,
            onSuccess: () => {
                submitted.value = false;
            },
            onError: () => {
                submitted.value = false;
            }
        });
    } else {
        form.post(route('branch-groups.store'), {
            preserveScroll: true,
            onSuccess: () => {
                submitted.value = false;
                if (form.create_another) {
                    form.reset('name');
                    form.create_another = false;
                }
            },
            onError: () => {
                submitted.value = false;
            }
        });
    }
}

function submitAndCreateAnother() {
    form.create_another = true;
    submitForm();
}
</script>

<template>
    <div class="flex justify-between">
        <form @submit.prevent="submitForm" class="w-2/3 max-w-2xl mr-8">
            <AppInput
                v-model="form.name"
                label="Nama Kelompok Cabang:"
                placeholder="Masukkan nama kelompok cabang"
                :error="form.errors.name"
                autofocus
                required
            />
            <AppSelect
                v-model="form.company_id"
                :options="companies.map(company => ({ value: company.id, label: company.name }))"
                label="Perusahaan:"
                placeholder="Pilih perusahaan"
                :error="form.errors.company_id"
                required
                class="mt-4"
            />
            <div class="mt-4 flex items-center">
                <AppPrimaryButton type="submit" class="mr-2">
                    {{ props.branchGroup ? 'Ubah' : 'Tambah' }} Kelompok Cabang
                </AppPrimaryButton>
                <AppUtilityButton v-if="!props.branchGroup" type="button" @click="submitAndCreateAnother" class="mr-2">
                    Tambah & Buat Lagi
                </AppUtilityButton>
                <AppSecondaryButton @click="$inertia.visit(route('branch-groups.index', filters))">
                    Batal
                </AppSecondaryButton>
            </div>
        </form>
        
        <div class="w-1/3 bg-gray-100 p-4 rounded-lg text-sm">
            <h3 class="text-lg font-semibold mb-2">Informasi Kelompok Cabang</h3>
            <p class="mb-2">Kelompok Cabang membantu mengorganisir cabang-cabang Anda. Gunakan nama yang jelas dan deskriptif.</p>
            <ul class="list-disc list-inside">
                <li>Pastikan nama kelompok unik</li>
                <li>Gunakan nama yang mudah dimengerti</li>
                <li>Pertimbangkan pengelompokan berdasarkan lokasi atau fungsi</li>
            </ul>
        </div>
    </div>
</template>