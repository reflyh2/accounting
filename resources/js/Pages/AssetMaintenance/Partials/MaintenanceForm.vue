<script setup>
import { useForm } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppTextarea from '@/Components/AppTextarea.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppUtilityButton from '@/Components/AppUtilityButton.vue';
import AppSelect from '@/Components/AppSelect.vue';
import { ref, watch } from 'vue';

const props = defineProps({
    asset: Object,
    maintenance: Object,
    filters: Object,
    maintenanceTypes: Array,
});

const form = useForm({
    maintenance_date: props.maintenance ? new Date(props.maintenance.maintenance_date).toISOString().split('T')[0] : new Date().toISOString().split('T')[0],
    maintenance_type_id: props.maintenance?.maintenance_type_id || '',
    cost: props.maintenance?.cost || '',
    description: props.maintenance?.description || '',
    performed_by: props.maintenance?.performed_by || '',
    next_maintenance_date: props.maintenance?.next_maintenance_date ? new Date(props.maintenance.next_maintenance_date).toISOString().split('T')[0] : '',
    create_another: false,
});

// Helper function to add days to a date
function addDays(dateString, days) {
    const date = new Date(dateString);
    date.setDate(date.getDate() + parseInt(days));
    return date.toISOString().split('T')[0];
}

// Calculate next maintenance date when maintenance type or date changes
watch(() => form.maintenance_type_id, (newTypeId) => {
    if (newTypeId && form.maintenance_date) {
        const selectedType = props.maintenanceTypes.find(type => type.id === parseInt(newTypeId));
        if (selectedType && selectedType.maintenance_interval_days) {
            form.next_maintenance_date = addDays(form.maintenance_date, selectedType.maintenance_interval_days);
        }
    }
});

watch(() => form.maintenance_date, (newDate) => {
    if (newDate && form.maintenance_type_id) {
        const selectedType = props.maintenanceTypes.find(type => type.id === parseInt(form.maintenance_type_id));
        if (selectedType && selectedType.maintenance_interval_days) {
            form.next_maintenance_date = addDays(newDate, selectedType.maintenance_interval_days);
        }
    }
});

function submitForm(createAnother = false) {
    form.create_another = createAnother;
    
    if (props.maintenance) {
        form.put(route('asset-maintenance.update', props.maintenance.id), {
            preserveScroll: true,
            onSuccess: () => {
                if (createAnother) {
                    form.reset();
                    form.clearErrors();
                }
            }
        });
    } else {
        form.post(route('asset-maintenance.store', props.asset.id), {
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
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <AppInput
                v-model="form.maintenance_date"
                label="Tanggal Pemeliharaan"
                type="date"
                :error="form.errors.maintenance_date"
                required
            />

            <AppSelect
                v-model="form.maintenance_type_id"
                label="Jenis Pemeliharaan"
                :options="maintenanceTypes.map(type => ({ 
                    value: type.id, 
                    label: type.name + (type.maintenance_interval ? ' (' + type.maintenance_interval + ')' : '')
                }))"
                :error="form.errors.maintenance_type_id"
                placeholder="Pilih jenis pemeliharaan"
                required
            />

            <AppInput
                v-model="form.cost"
                label="Biaya"
                :number-format="true"
                :error="form.errors.cost"
                required
            />

            <AppInput
                v-model="form.performed_by"
                label="Dilakukan Oleh"
                :error="form.errors.performed_by"
            />

            <AppInput
                v-model="form.next_maintenance_date"
                label="Jadwal Pemeliharaan Berikutnya"
                type="date"
                :error="form.errors.next_maintenance_date"
                :min="form.maintenance_date"
                :hint="form.maintenance_type_id ? 'Tanggal ini dihitung otomatis berdasarkan interval pemeliharaan yang dipilih' : ''"
            />
        </div>

        <AppTextarea
            v-model="form.description"
            label="Deskripsi Pemeliharaan"
            :error="form.errors.description"
            required
        />

        <div class="flex items-center mt-4">
            <AppPrimaryButton type="submit" class="mr-2">
                {{ maintenance ? 'Ubah' : 'Buat' }} Catatan
            </AppPrimaryButton>
            <AppUtilityButton
                v-if="!maintenance"
                type="button"
                @click="submitForm(true)"
                class="mr-2"
            >
                Tambah & Buat Lagi
            </AppUtilityButton>
            <AppSecondaryButton
                @click="$inertia.visit(route('asset-maintenance.index', asset.id))"
            >
                Batal
            </AppSecondaryButton>
        </div>
    </form>
</template> 