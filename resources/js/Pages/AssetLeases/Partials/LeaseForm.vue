<script setup>
import { useForm } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppTextarea from '@/Components/AppTextarea.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';

const props = defineProps({
    asset: Object,
    lease: Object,
});

const form = useForm({
    lease_type: props.lease?.lease_type || 'operating',
    start_date: props.lease ? new Date(props.lease.start_date).toISOString().split('T')[0] : new Date().toISOString().split('T')[0],
    end_date: props.lease ? new Date(props.lease.end_date).toISOString().split('T')[0] : '',
    lease_amount: props.lease?.lease_amount || '',
    payment_frequency: props.lease?.payment_frequency || 'monthly',
    payment_amount: props.lease?.payment_amount || '',
    prepaid_amount: props.lease?.prepaid_amount || 0,
    total_obligation: props.lease?.total_obligation || '',
    interest_rate: props.lease?.interest_rate || '',
    has_escalation_clause: props.lease?.has_escalation_clause || false,
    escalation_terms: props.lease?.escalation_terms || '',
    lease_terms: props.lease?.lease_terms || '',
});

const leaseTypes = [
    { value: 'operating', label: 'Operating Lease' },
    { value: 'finance', label: 'Finance Lease' },
];

const paymentFrequencies = [
    { value: 'monthly', label: 'Monthly' },
    { value: 'quarterly', label: 'Quarterly' },
    { value: 'annually', label: 'Annually' },
];

function submitForm() {
    if (props.lease) {
        form.put(route('asset-leases.update', props.asset.id), {
            preserveScroll: true,
        });
    } else {
        form.post(route('asset-leases.store', props.asset.id), {
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
                        v-model="form.lease_type"
                        :options="leaseTypes"
                        label="Lease Type"
                        :error="form.errors.lease_type"
                        required
                    />

                    <AppSelect
                        v-model="form.payment_frequency"
                        :options="paymentFrequencies"
                        label="Payment Frequency"
                        :error="form.errors.payment_frequency"
                        required
                    />

                    <AppInput
                        v-model="form.start_date"
                        type="date"
                        label="Start Date"
                        :error="form.errors.start_date"
                        required
                    />

                    <AppInput
                        v-model="form.end_date"
                        type="date"
                        label="End Date"
                        :error="form.errors.end_date"
                        :min="form.start_date"
                        required
                    />

                    <AppInput
                        v-model="form.lease_amount"
                        label="Total Lease Amount"
                        :number-format="true"
                        :error="form.errors.lease_amount"
                        required
                    />

                    <AppInput
                        v-model="form.payment_amount"
                        label="Payment Amount"
                        :number-format="true"
                        :error="form.errors.payment_amount"
                        required
                    />

                    <AppInput
                        v-model="form.prepaid_amount"
                        label="Prepaid Amount"
                        :number-format="true"
                        :error="form.errors.prepaid_amount"
                        required
                    />

                    <AppInput
                        v-model="form.total_obligation"
                        label="Total Obligation"
                        :number-format="true"
                        :error="form.errors.total_obligation"
                        required
                    />

                    <AppInput
                        v-if="form.lease_type === 'finance'"
                        v-model="form.interest_rate"
                        label="Interest Rate (%)"
                        type="number"
                        step="0.01"
                        :error="form.errors.interest_rate"
                        required
                    />

                    <div class="col-span-2">
                        <label class="flex items-center">
                            <input
                                type="checkbox"
                                v-model="form.has_escalation_clause"
                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                            >
                            <span class="ml-2">Has Escalation Clause</span>
                        </label>
                    </div>

                    <div v-if="form.has_escalation_clause" class="col-span-2">
                        <AppTextarea
                            v-model="form.escalation_terms"
                            label="Escalation Terms"
                            :error="form.errors.escalation_terms"
                            required
                        />
                    </div>

                    <div class="col-span-2">
                        <AppTextarea
                            v-model="form.lease_terms"
                            label="Lease Terms"
                            :error="form.errors.lease_terms"
                        />
                    </div>
                </div>

                <div class="mt-4 flex items-center">
                    <AppPrimaryButton type="submit" class="mr-2">
                        {{ lease ? 'Update' : 'Create' }} Lease
                    </AppPrimaryButton>
                    <AppSecondaryButton
                        @click="$inertia.visit(route('assets.show', asset.id))"
                    >
                        Cancel
                    </AppSecondaryButton>
                </div>
            </div>

            <div class="w-1/3 bg-gray-100 p-4 rounded-lg text-sm">
                <h3 class="text-lg font-semibold mb-2">Lease Information</h3>
                <p class="mb-2">Please provide the following lease details:</p>
                <ul class="list-disc list-inside space-y-1">
                    <li>Choose between Operating or Finance lease</li>
                    <li>Set the lease duration and payment terms</li>
                    <li>Specify payment frequency and amounts</li>
                    <li>Include any prepaid amounts</li>
                    <li>For finance leases, provide the interest rate</li>
                    <li>Document any escalation clauses</li>
                    <li>Add any additional lease terms or conditions</li>
                </ul>
            </div>
        </div>
    </form>
</template> 