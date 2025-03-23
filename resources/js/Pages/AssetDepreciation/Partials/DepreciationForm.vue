<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import AppInput from '@/Components/AppInput.vue';
import AppTextarea from '@/Components/AppTextarea.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppUtilityButton from '@/Components/AppUtilityButton.vue';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    asset: {
        type: Object,
        required: true
    },
    entry: {
        type: Object,
        default: null
    },
    accounts: {
        type: Array,
        required: true
    }
});

const isAmortization = computed(() => props.asset.acquisition_type === 'fixed_rental');

const depreciationMethods = [
    { value: 'straight-line', label: 'Garis Lurus' },
    { value: 'declining-balance', label: 'Saldo Menurun' },
];

// Calculate next entry date
const nextEntryDate = computed(() => {
    if (props.entry) return (new Date(props.entry.entry_date).toISOString().split('T')[0]);
    
    const today = new Date();
    return today.toISOString().split('T')[0];
});

// Calculate next period dates
const nextPeriodDates = computed(() => {
    if (props.entry) {
        return {
            start: new Date(props.entry.period_start).toISOString().split('T')[0],
            end: new Date(props.entry.period_end).toISOString().split('T')[0]
        };
    }
    
    const existing = props.asset.depreciation_entries || [];
    if (existing.length > 0) {
        // Find the last entry
        const sortedEntries = [...existing].sort((a, b) => {
            return new Date(b.period_end) - new Date(a.period_end);
        });
        
        const lastEnd = new Date(sortedEntries[0].period_end);
        const nextStart = new Date(lastEnd);
        nextStart.setDate(nextStart.getDate() + 1);
        
        const nextEnd = new Date(nextStart);
        nextEnd.setMonth(nextEnd.getMonth() + 1);
        nextEnd.setDate(nextEnd.getDate() - 1);
        
        return {
            start: nextStart.toISOString().split('T')[0],
            end: nextEnd.toISOString().split('T')[0]
        };
    }
    
    // If no existing entries, use the appropriate reference date
    let referenceDate;
    if (isAmortization.value) {
        referenceDate = props.asset.rental_start_date ? new Date(props.asset.rental_start_date) : new Date();
    } else {
        referenceDate = props.asset.purchase_date ? new Date(props.asset.purchase_date) : new Date();
    }
    
    const periodStart = new Date(referenceDate);
    const periodEnd = new Date(periodStart);
    periodEnd.setMonth(periodEnd.getMonth() + 1);
    periodEnd.setDate(periodEnd.getDate() - 1);
    
    return {
        start: periodStart.toISOString().split('T')[0],
        end: periodEnd.toISOString().split('T')[0]
    };
});

// Calculate the depreciation or amortization amount
const calculatedAmount = computed(() => {
    if (isAmortization.value) {
        // Amortization calculation for rental assets
        const rentalAmount = props.asset.rental_amount || 0;
        const termMonths = props.asset.amortization_term_months || 12; // Default to 1 year if not set
        
        // Monthly amortization amount (always straight-line for amortization)
        return rentalAmount / termMonths;
    } else {
        // Depreciation calculation for purchased assets
        const purchaseCost = props.asset.purchase_cost || 0;
        const salvageValue = props.asset.salvage_value || 0;
        const usefulLife = props.asset.useful_life_months || 60; // Default to 5 years if not set
        
        // Total depreciable amount
        const depreciableAmount = purchaseCost - salvageValue;
        
        // Monthly depreciation amount (straight-line)
        return depreciableAmount / usefulLife;
    }
});

// Get the current cumulative amount and remaining value
const currentValues = computed(() => {
    const existing = props.asset.depreciation_entries || [];
    const baseValue = isAmortization.value ? 
                    (props.asset.rental_amount || 0) : 
                    (props.asset.purchase_cost || 0);
    
    if (existing.length > 0) {
        // Find the entry with the highest cumulative amount
        const sortedEntries = [...existing].sort((a, b) => {
            return b.cumulative_amount - a.cumulative_amount;
        });
        
        return {
            cumulative: Number(sortedEntries[0].cumulative_amount) || 0,
            remaining: Number(sortedEntries[0].remaining_value) || baseValue
        };
    }
    
    return {
        cumulative: 0,
        remaining: baseValue
    };
});

const form = useForm({
    entry_date: props.entry?.entry_date ? new Date(props.entry.entry_date).toISOString().split('T')[0] : nextEntryDate.value,
    type: props.entry?.type || (isAmortization.value ? 'amortization' : 'depreciation'),
    amount: props.entry?.amount || calculatedAmount.value,
    period_start: props.entry?.period_start ? new Date(props.entry.period_start).toISOString().split('T')[0] : nextPeriodDates.value.start,
    period_end: props.entry?.period_end ? new Date(props.entry.period_end).toISOString().split('T')[0] : nextPeriodDates.value.end,
    notes: props.entry?.notes || '',
    create_another: false,
});

// Watch for changes in amount to update cumulative and remaining values
watch(() => form.amount, (newAmount) => {
    const amount = Number(newAmount || 0);
    form.cumulative_amount = currentValues.value.cumulative + amount;
    form.remaining_value = currentValues.value.remaining - amount;
});

function submitForm(createAnother = false) {
    form.create_another = createAnother;
    
    if (props.entry) {
        form.put(route('asset-depreciation.update', [props.entry.id]), {
            preserveScroll: true,
            onSuccess: () => {
                if (createAnother) {
                    form.reset();
                    form.clearErrors();
                }
            }
        });
    } else {
        form.post(route('asset-depreciation.store', props.asset.id), {
            preserveScroll: true,
            onSuccess: () => {
                if (createAnother) {
                    form.reset();
                    form.clearErrors();
                    
                    // Update values for next entry
                    const newCumulative = currentValues.value.cumulative + Number(form.amount);
                    const newRemaining = currentValues.value.remaining - Number(form.amount);
                    
                    // Get next period dates
                    const lastEnd = new Date(form.period_end);
                    const nextStart = new Date(lastEnd);
                    nextStart.setDate(nextStart.getDate() + 1);
                    
                    const nextEnd = new Date(nextStart);
                    nextEnd.setMonth(nextEnd.getMonth() + 1);
                    nextEnd.setDate(nextEnd.getDate() - 1);
                    
                    form.entry_date = new Date().toISOString().split('T')[0];
                    form.cumulative_amount = newCumulative;
                    form.remaining_value = newRemaining;
                    form.period_start = nextStart.toISOString().split('T')[0];
                    form.period_end = nextEnd.toISOString().split('T')[0];
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
                v-model="form.entry_date"
                type="date"
                :label="isAmortization ? 'Tanggal Amortisasi' : 'Tanggal Penyusutan'"
                :error="form.errors.entry_date"
                required
            />

            <AppSelect
                v-model="form.type"
                :options="[
                    { value: 'depreciation', label: 'Penyusutan' },
                    { value: 'amortization', label: 'Amortisasi' }
                ]"
                label="Tipe"
                :error="form.errors.type"
                required
                :disabled="true"
            />

            <AppInput
                v-model="form.period_start"
                type="date"
                label="Awal Periode"
                :error="form.errors.period_start"
                required
            />

            <AppInput
                v-model="form.period_end"
                type="date"
                label="Akhir Periode"
                :error="form.errors.period_end"
                required
            />

            <AppInput
                v-model="form.amount"
                :label="isAmortization ? 'Jumlah Amortisasi' : 'Jumlah Penyusutan'"
                :number-format="true"
                :error="form.errors.amount"
                required
            />
        </div>

        <div class="bg-gray-50 p-4 rounded-lg">
            <h3 class="font-medium mb-2">{{ isAmortization ? 'Informasi Amortisasi' : 'Informasi Penyusutan' }}</h3>
            <div class="grid grid-cols-2 gap-2 text-sm">
                <div v-if="isAmortization">
                    <p class="text-gray-600">Nilai Sewa:</p>
                    <p>{{ formatNumber(asset.rental_amount) }}</p>
                </div>
                <div v-else>
                    <p class="text-gray-600">Nilai Perolehan:</p>
                    <p>{{ formatNumber(asset.purchase_cost) }}</p>
                </div>
                
                <div v-if="!isAmortization">
                    <p class="text-gray-600">Nilai Residu:</p>
                    <p>{{ formatNumber(asset.salvage_value) }}</p>
                </div>
                
                <div v-if="!isAmortization">
                    <p class="text-gray-600">Metode:</p>
                    <p>{{ depreciationMethods.find(method => method.value === asset.depreciation_method)?.label || 'Garis Lurus' }}</p>
                </div>
                
                <div>
                    <p class="text-gray-600">{{ isAmortization ? 'Periode Amortisasi:' : 'Masa Manfaat:' }}</p>
                    <p>{{ isAmortization ? asset.amortization_term_months : asset.useful_life_months }} bulan</p>
                </div>
            </div>
        </div>

        <AppTextarea
            v-model="form.notes"
            label="Catatan"
            :error="form.errors.notes"
        />

        <div class="flex items-center mt-4">
            <AppPrimaryButton type="submit" class="mr-2">
                {{ entry ? 'Simpan Perubahan' : 'Simpan' }}
            </AppPrimaryButton>
            <AppUtilityButton
                v-if="!entry"
                type="button"
                @click="submitForm(true)"
                class="mr-2"
            >
                Tambah & Buat Lagi
            </AppUtilityButton>
            <AppSecondaryButton
                @click="$inertia.visit(route('asset-depreciation.index', asset.id))"
            >
                Batal
            </AppSecondaryButton>
        </div>
    </form>
</template> 