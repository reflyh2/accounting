<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppEditButton from '@/Components/AppEditButton.vue';
import AppDeleteButton from '@/Components/AppDeleteButton.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppPopoverSearch from '@/Components/AppPopoverSearch.vue';
import axios from 'axios';
import { addHours, format } from 'date-fns';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
    product: Object,
    currencies: { type: Array, default: () => [] },
    partnerSearchUrl: String,
});

const form = useForm({});
const showDeleteConfirmation = ref(false);
const selectedPoolId = ref(props.product?.resource_pools?.[0]?.id ?? null);
const bookingErrors = ref({});
const bookingSuccess = ref(null);
const isSubmittingBooking = ref(false);
const availableInstances = ref([]);
const loadingInstances = ref(false);

const bookingForm = reactive({
    partner_id: null,
    currency_id: props.currencies?.[0]?.id ?? null,
    start_datetime: format(new Date(), "yyyy-MM-dd'T'HH:00"),
    end_datetime: format(addHours(new Date(), 4), "yyyy-MM-dd'T'HH:00"),
    qty: 1,
    unit_price: '',
    deposit_amount: '',
    source_channel: '',
    notes: '',
    resource_instance_id: null,
});

const poolOptions = computed(() => props.product?.resource_pools?.map(pool => ({
    value: pool.id,
    label: `${pool.name} (${pool.branch?.name ?? 'No Branch'})`,
})) ?? []);

watch(
    () => [bookingForm.start_datetime, bookingForm.end_datetime, selectedPoolId.value],
    () => {
        loadInstances();
    }
);

loadInstances();

const deleteProduct = () => {
    form.delete(route('catalog.rental.destroy', props.product.id), {
        onSuccess: () => (showDeleteConfirmation.value = false),
        onError: () => (showDeleteConfirmation.value = false),
    });
};

async function loadInstances() {
    if (!selectedPoolId.value || !bookingForm.start_datetime || !bookingForm.end_datetime) {
        availableInstances.value = [];
        bookingForm.resource_instance_id = null;
        return;
    }

    loadingInstances.value = true;

    try {
        const response = await axios.get(route('api.availability.pool.free-instances', { pool: selectedPoolId.value }), {
            params: {
                start: bookingForm.start_datetime,
                end: bookingForm.end_datetime,
                qty: bookingForm.qty,
            },
        });
        availableInstances.value = response.data.data;

        if (!availableInstances.value.some(instance => instance.id === bookingForm.resource_instance_id)) {
            bookingForm.resource_instance_id = null;
        }
    } catch {
        availableInstances.value = [];
        bookingForm.resource_instance_id = null;
    } finally {
        loadingInstances.value = false;
    }
}

async function submitBooking() {
    bookingErrors.value = {};
    bookingSuccess.value = null;
    if (!selectedPoolId.value) {
        bookingErrors.value = { general: 'Resource pool belum dipilih.' };
        return;
    }
    isSubmittingBooking.value = true;

    try {
        const payload = {
            partner_id: bookingForm.partner_id,
            currency_id: bookingForm.currency_id,
            booking_type: 'rental',
            deposit_amount: bookingForm.deposit_amount || null,
            source_channel: bookingForm.source_channel || null,
            notes: bookingForm.notes || null,
            lines: [
                {
                    product_id: props.product.id,
                    resource_pool_id: selectedPoolId.value,
                    start_datetime: bookingForm.start_datetime,
                    end_datetime: bookingForm.end_datetime,
                    qty: bookingForm.qty,
                    unit_price: bookingForm.unit_price,
                    deposit_required: bookingForm.deposit_amount || 0,
                    resource_instance_id: bookingForm.resource_instance_id,
                },
            ],
        };

        const response = await axios.post(route('api.bookings.store'), payload);
        bookingSuccess.value = response.data;
        await loadInstances();
    } catch (error) {
        if (error.response?.status === 422) {
            bookingErrors.value = error.response.data.errors ?? { general: error.response.data.message };
        } else {
            bookingErrors.value = { general: 'Terjadi kesalahan saat menyimpan booking.' };
        }
    } finally {
        isSubmittingBooking.value = false;
    }
}
</script>

<template>
    <Head title="Detail Rental" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Rental</h2>
        </template>

        <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded border border-gray-200">
                <div class="p-6 text-gray-900">
                    <div class="mb-6">
                        <AppBackLink :href="route('catalog.rental.index')" text="Back to Rental List" />
                    </div>
                    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between mb-4">
                        <h3 class="text-lg font-bold">{{ product.code }} - {{ product.name }}</h3>
                        <div class="flex items-center">
                            <Link :href="route('catalog.rental.edit', product.id)" class="mr-1">
                                <AppEditButton title="Edit" />
                            </Link>
                            <AppDeleteButton @click="showDeleteConfirmation = true" title="Delete" />
                        </div>
                    </div>

                    <div class="grid md:grid-cols-3 gap-6">
                        <div class="md:col-span-2 space-y-6">
                            <section>
                                <h4 class="text-md font-semibold mb-2">Product Info</h4>
                                <div class="grid grid-cols-2 gap-x-6 gap-y-2 text-sm">
                                    <div>
                                        <p class="font-semibold">Category</p>
                                        <p>{{ product.category?.name ?? '-' }}</p>
                                    </div>
                                    <div>
                                        <p class="font-semibold">Tax Category</p>
                                        <p>{{ product.tax_category?.name ?? '-' }}</p>
                                    </div>
                                    <div>
                                        <p class="font-semibold">Active</p>
                                        <p>{{ product.is_active ? 'Yes' : 'No' }}</p>
                                    </div>
                                    <div>
                                        <p class="font-semibold">Companies</p>
                                        <p>{{ product.companies?.length ? product.companies.map(c => c.name).join(', ') : '-' }}</p>
                                    </div>
                                </div>
                            </section>

                            <section>
                                <h4 class="text-md font-semibold mb-2">Rental Policy</h4>
                                <div class="text-sm bg-gray-50 border border-gray-200 rounded p-3">
                                    <p>Granularity: {{ product.rental_policy?.billing_granularity ?? 'hour' }}</p>
                                    <p>Min Duration: {{ product.rental_policy?.min_duration_minutes ?? '-' }} minutes</p>
                                    <p>Max Duration: {{ product.rental_policy?.max_duration_minutes ?? '-' }} minutes</p>
                                    <p>Fuel Policy: {{ product.rental_policy?.fuel_policy ?? '-' }}</p>
                                    <p>Mileage Included: {{ product.rental_policy?.mileage_included ?? '-' }}</p>
                                </div>
                            </section>

                            <section>
                                <h4 class="text-md font-semibold mb-2">Attributes</h4>
                                <table class="w-full border-collapse border border-gray-300 text-sm">
                                    <thead>
                                        <tr class="bg-gray-50">
                                            <th class="border border-gray-300 px-1.5 py-1.5 text-left">Code</th>
                                            <th class="border border-gray-300 px-1.5 py-1.5 text-left">Value</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(value, key) in product.attrs_json || {}" :key="key">
                                            <td class="border border-gray-300 px-1.5 py-1.5">{{ key }}</td>
                                            <td class="border border-gray-300 px-1.5 py-1.5">{{ value }}</td>
                                        </tr>
                                        <tr v-if="!product.attrs_json || Object.keys(product.attrs_json).length === 0">
                                            <td colspan="2" class="border border-gray-300 px-1.5 py-1.5 text-center text-gray-500">No attributes.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </section>
                        </div>

                        <div class="space-y-4">
                            <h4 class="text-md font-semibold">Quick Rental Booking</h4>
                            <AppSelect
                                v-model="selectedPoolId"
                                :options="poolOptions"
                                label="Resource Pool"
                                placeholder="Select pool"
                            />

                            <div class="grid grid-cols-2 gap-2">
                                <AppInput v-model="bookingForm.start_datetime" type="datetime-local" label="Pickup" />
                                <AppInput v-model="bookingForm.end_datetime" type="datetime-local" label="Return" />
                            </div>

                            <div class="grid grid-cols-3 gap-2">
                                <AppInput v-model="bookingForm.qty" type="number" min="1" label="Qty" />
                                <AppInput v-model="bookingForm.unit_price" type="number" min="0" label="Rate" />
                                <AppInput v-model="bookingForm.deposit_amount" type="number" min="0" label="Deposit" />
                            </div>

                            <AppSelect
                                v-model="bookingForm.resource_instance_id"
                                :options="availableInstances.map(instance => ({ value: instance.id, label: instance.code }))"
                                label="Instance"
                                placeholder="Select instance"
                                :hint="loadingInstances ? 'Loading instances...' : ''"
                            />

                            <AppPopoverSearch
                                v-model="bookingForm.partner_id"
                                label="Customer"
                                :url="partnerSearchUrl"
                                :displayKeys="['code','name']"
                                :tableHeaders="[
                                    { key: 'code', label: 'Code' },
                                    { key: 'name', label: 'Name' },
                                ]"
                                :error="bookingErrors?.partner_id"
                            />

                            <AppSelect
                                v-model="bookingForm.currency_id"
                                label="Currency"
                                :options="currencies.map(currency => ({ value: currency.id, label: currency.code }))"
                                :error="bookingErrors?.currency_id"
                            />

                            <AppInput v-model="bookingForm.source_channel" label="Channel" />
                            <AppInput v-model="bookingForm.notes" label="Notes" />

                            <p v-if="bookingErrors?.general" class="text-sm text-red-600">{{ bookingErrors.general }}</p>
                            <div v-if="bookingSuccess" class="text-sm text-green-700 bg-green-50 border border-green-200 rounded p-2">
                                Booking {{ bookingSuccess.booking_number }} dibuat.
                            </div>

                            <AppPrimaryButton type="button" class="w-full" @click="submitBooking" :disabled="isSubmittingBooking">
                                {{ isSubmittingBooking ? 'Booking...' : 'Hold Rental' }}
                            </AppPrimaryButton>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <DeleteConfirmationModal
            :show="showDeleteConfirmation"
            title="Delete Product"
            message="Are you sure you want to delete this product?"
            @close="showDeleteConfirmation = false"
            @confirm="deleteProduct"
        />
    </AuthenticatedLayout>
</template>

