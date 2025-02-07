<script setup>
import { useForm } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppTextarea from '@/Components/AppTextarea.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';

const props = defineProps({
    asset: Object,
    disposal: Object,
    currentValue: Number,
});

const form = useForm({
    disposal_date: props.disposal ? new Date(props.disposal.disposal_date).toISOString().split('T')[0] : new Date().toISOString().split('T')[0],
    disposal_method: props.disposal?.disposal_method || 'sale',
    disposal_amount: props.disposal?.disposal_amount || '',
    book_value_at_disposal: props.disposal?.book_value_at_disposal || props.currentValue || 0,
    reason: props.disposal?.reason || '',
    notes: props.disposal?.notes || '',
});

const disposalMethods = [
    { value: 'sale', label: 'Sale' },
    { value: 'scrap', label: 'Scrap' },
    { value: 'donation', label: 'Donation' },
];

function submitForm() {
    if (props.disposal) {
        form.put(route('asset-disposals.update', [props.asset.id, props.disposal.id]), {
            preserveScroll: true,
        });
    } else {
        form.post(route('asset-disposals.store', props.asset.id), {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <form @submit.prevent="submitForm" class="space-y-4">
        <div class="flex justify-between">
            <div class="w-2/3 max-w-2xl mr-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <AppSelect
                        v-model="form.disposal_method"
                        :options="disposalMethods"
                        label="Disposal Method"
                        :error="form.errors.disposal_method"
                        required
                    />

                    <AppInput
                        v-model="form.disposal_date"
                        type="date"
                        label="Disposal Date"
                        :error="form.errors.disposal_date"
                        required
                    />

                    <AppInput
                        v-if="form.disposal_method === 'sale'"
                        v-model="form.disposal_amount"
                        label="Disposal Amount"
                        :number-format="true"
                        :error="form.errors.disposal_amount"
                        required
                    />

                    <AppInput
                        v-model="form.book_value_at_disposal"
                        label="Book Value at Disposal"
                        :number-format="true"
                        :error="form.errors.book_value_at_disposal"
                        required
                        readonly
                    />

                    <div class="col-span-2">
                        <AppTextarea
                            v-model="form.reason"
                            label="Disposal Reason"
                            :error="form.errors.reason"
                            required
                        />
                    </div>

                    <div class="col-span-2">
                        <AppTextarea
                            v-model="form.notes"
                            label="Additional Notes"
                            :error="form.errors.notes"
                        />
                    </div>
                </div>

                <div class="mt-4 flex items-center">
                    <AppPrimaryButton type="submit" class="mr-2">
                        {{ disposal ? 'Update' : 'Create' }} Disposal Request
                    </AppPrimaryButton>
                    <AppSecondaryButton
                        @click="$inertia.visit(route('assets.show', asset.id))"
                    >
                        Cancel
                    </AppSecondaryButton>
                </div>
            </div>

            <div class="w-1/3 bg-gray-100 p-4 rounded-lg text-sm">
                <h3 class="text-lg font-semibold mb-2">Disposal Information</h3>
                <p class="mb-2">Please provide the following disposal details:</p>
                <ul class="list-disc list-inside space-y-1">
                    <li>Choose the disposal method</li>
                    <li>Set the disposal date</li>
                    <li>For sales, enter the disposal amount</li>
                    <li>Provide a reason for disposal</li>
                    <li>Add any additional notes or instructions</li>
                </ul>
            </div>
        </div>
    </form>
</template> 