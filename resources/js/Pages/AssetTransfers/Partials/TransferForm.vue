<script setup>
import { useForm } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppTextarea from '@/Components/AppTextarea.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';

const props = defineProps({
    asset: Object,
    transfer: Object,
});

const form = useForm({
    from_department: props.transfer?.from_department || props.asset.department || '',
    to_department: props.transfer?.to_department || '',
    from_location: props.transfer?.from_location || props.asset.location || '',
    to_location: props.transfer?.to_location || '',
    transfer_date: props.transfer ? new Date(props.transfer.transfer_date).toISOString().split('T')[0] : new Date().toISOString().split('T')[0],
    reason: props.transfer?.reason || '',
    notes: props.transfer?.notes || '',
});

function submitForm() {
    if (props.transfer) {
        form.put(route('asset-transfers.update', [props.asset.id, props.transfer.id]), {
            preserveScroll: true,
        });
    } else {
        form.post(route('asset-transfers.store', props.asset.id), {
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
                    <AppInput
                        v-model="form.from_department"
                        label="From Department"
                        :error="form.errors.from_department"
                        required
                    />

                    <AppInput
                        v-model="form.to_department"
                        label="To Department"
                        :error="form.errors.to_department"
                        required
                    />

                    <AppInput
                        v-model="form.from_location"
                        label="From Location"
                        :error="form.errors.from_location"
                        required
                    />

                    <AppInput
                        v-model="form.to_location"
                        label="To Location"
                        :error="form.errors.to_location"
                        required
                    />

                    <AppInput
                        v-model="form.transfer_date"
                        type="date"
                        label="Transfer Date"
                        :error="form.errors.transfer_date"
                        required
                    />

                    <div class="col-span-2">
                        <AppTextarea
                            v-model="form.reason"
                            label="Transfer Reason"
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
                        {{ transfer ? 'Update' : 'Create' }} Transfer Request
                    </AppPrimaryButton>
                    <AppSecondaryButton
                        @click="$inertia.visit(route('assets.show', asset.id))"
                    >
                        Cancel
                    </AppSecondaryButton>
                </div>
            </div>

            <div class="w-1/3 bg-gray-100 p-4 rounded-lg text-sm">
                <h3 class="text-lg font-semibold mb-2">Transfer Information</h3>
                <p class="mb-2">Please provide the following transfer details:</p>
                <ul class="list-disc list-inside space-y-1">
                    <li>Current and new department information</li>
                    <li>Current and new location details</li>
                    <li>Planned transfer date</li>
                    <li>Reason for the transfer</li>
                    <li>Any additional notes or special instructions</li>
                </ul>
            </div>
        </div>
    </form>
</template> 