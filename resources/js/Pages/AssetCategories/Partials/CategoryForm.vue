<script setup>
import { useForm } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppTextarea from '@/Components/AppTextarea.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppUtilityButton from '@/Components/AppUtilityButton.vue';
import AppSelect from '@/Components/AppSelect.vue';

const props = defineProps({
    category: Object,
    companies: Array,
    filters: Object,
});

const form = useForm({
    name: props.category?.name || '',
    description: props.category?.description || '',
    company_ids: props.category?.companies?.map(c => c.id) || props.companies.map(c => c.id),
    create_another: false,
});

function submitForm(createAnother = false) {
    form.create_another = createAnother;
    
    if (props.category) {
        form.put(route('asset-categories.update', props.category.id), {
            preserveScroll: true,
            onSuccess: () => {
                if (createAnother) {
                    form.reset();
                    form.clearErrors();
                }
            }
        });
    } else {
        form.post(route('asset-categories.store'), {
            preserveScroll: true,
            onSuccess: () => {
                if (createAnother) {
                    form.reset();
                    form.clearErrors();
                }
            }
        });
    }
}
</script>

<template>
    <form @submit.prevent="submitForm(false)" class="space-y-4">
        <div class="flex justify-between">
            <div class="w-2/3 max-w-2xl mr-8">
                <div class="grid grid-cols-1 gap-4">
                    <AppInput
                    v-model="form.name"
                        label="Nama Kategori"
                        :error="form.errors.name"
                        required
                    />

                    <AppSelect
                        v-model="form.company_ids"
                        :options="props.companies.map(company => ({ value: company.id, label: company.name }))"
                        label="Perusahaan"
                        :error="form.errors.company_ids"
                        multiple
                        required
                    />

                    <AppTextarea
                        v-model="form.description"
                        label="Deskripsi"
                        :error="form.errors.description"
                    />
                </div>
            </div>
            <div class="w-1/3 bg-gray-100 p-4 rounded-lg text-sm">
                <h3 class="text-lg font-semibold mb-2">Informasi Kategori</h3>
                <p class="mb-2">Kategori adalah kelompok dari aset yang memiliki karakteristik yang sama. Contoh: Kendaraan, Mesin, Bangunan, dll.</p>
                <ul class="list-disc list-inside">
                    <li>Pilih perusahaan yang sesuai</li>
                    <li>Deskripsi kategori adalah deskripsi singkat yang menjelaskan kategori.</li>
                </ul>
            </div>
        </div>

        <div class="flex items-center mt-4">
            <AppPrimaryButton type="submit" class="mr-2">
                {{ category ? 'Ubah' : 'Buat' }} Kategori
            </AppPrimaryButton>
            <AppUtilityButton
                v-if="!category"
                type="button"
                @click="submitForm(true)"
                class="mr-2"
            >
                Tambah & Buat Lagi
            </AppUtilityButton>
            <AppSecondaryButton
                @click="$inertia.visit(route('asset-categories.index', filters))"
            >
                Batal
            </AppSecondaryButton>
        </div>
    </form>
</template> 