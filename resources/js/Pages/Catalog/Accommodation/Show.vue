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
import { addDays, format, startOfDay } from 'date-fns';
import { computed, onMounted, reactive, ref, watch } from 'vue';

const props = defineProps({
    product: Object,
    currencies: { type: Array, default: () => [] },
    partnerSearchUrl: String,
});

const form = useForm({});
const showDeleteConfirmation = ref(false);
const selectedPoolId = ref(props.product?.resource_pools?.[0]?.id ?? null);
const availabilityResult = ref(null);
const availabilityError = ref('');
const calendarDays = ref([]);
const loadingCalendar = ref(false);
const bookingErrors = ref({});
const bookingSuccess = ref(null);
const isSubmittingBooking = ref(false);

const bookingForm = reactive({
    partner_id: null,
    currency_id: props.currencies?.[0]?.id ?? null,
    pool_id: selectedPoolId.value,
    start_datetime: defaultDateTime(0),
    end_datetime: defaultDateTime(1),
    qty: 1,
    unit_price: '',
    deposit_amount: '',
    source_channel: '',
    notes: '',
});

const poolOptions = computed(() => props.product?.resource_pools?.map(pool => ({
    value: pool.id,
    label: `${pool.name} (${pool.branch?.name ?? 'No Branch'})`,
})) ?? []);

const selectedPool = computed(() => props.product?.resource_pools?.find(pool => pool.id === selectedPoolId.value) ?? null);

watch(selectedPoolId, (next) => {
    bookingForm.pool_id = next;
    availabilityResult.value = null;
    availabilityError.value = '';
    loadCalendar();
});

onMounted(() => {
    loadCalendar();
});

const deleteProduct = () => {
    form.delete(route('catalog.accommodation.destroy', props.product.id), {
        onSuccess: () => (showDeleteConfirmation.value = false),
        onError: () => (showDeleteConfirmation.value = false),
    });
};

function defaultDateTime(daysAhead = 0) {
    return format(addDays(new Date(), daysAhead), "yyyy-MM-dd'T'HH:00");
}

async function loadCalendar() {
    if (!selectedPoolId.value) return;
    loadingCalendar.value = true;
    const start = startOfDay(new Date());
    const days = [];

    try {
        for (let i = 0; i < 7; i++) {
            const dayStart = addDays(start, i);
            const dayEnd = addDays(dayStart, 1);
            const response = await axios.get(route('api.availability.pool', { pool: selectedPoolId.value }), {
                params: {
                    start: dayStart.toISOString(),
                    end: dayEnd.toISOString(),
                    qty: 1,
                },
            });
            days.push({
                date: dayStart,
                available_qty: response.data.data.available_qty,
                capacity: response.data.data.capacity,
                blocked: response.data.data.blocked,
            });
        }

        calendarDays.value = days;
    } catch (error) {
        console.error(error);
    } finally {
        loadingCalendar.value = false;
    }
}

async function checkAvailability() {
    if (!selectedPoolId.value) return;
    availabilityError.value = '';
    availabilityResult.value = null;

    try {
        const response = await axios.get(route('api.availability.pool', { pool: selectedPoolId.value }), {
            params: {
                start: bookingForm.start_datetime,
                end: bookingForm.end_datetime,
                qty: bookingForm.qty,
            },
        });
        availabilityResult.value = response.data.data;
    } catch (error) {
        availabilityError.value = error.response?.data?.message ?? 'Failed to check availability.';
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
            booking_type: 'accommodation',
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
                },
            ],
        };

        const response = await axios.post(route('api.bookings.store'), payload);
        bookingSuccess.value = response.data;
        await loadCalendar();
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

function formatCalendarLabel(date) {
    return format(date, 'EEE, dd MMM');
}
</script>

<template>
    <Head title="Detail Akomodasi" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Akomodasi</h2>
        </template>

        <div class="mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded border border-gray-200">
                <div class="p-6 text-gray-900">
                    <div class="mb-6">
                        <AppBackLink :href="route('catalog.accommodation.index')" text="Back to Accommodation List" />
                    </div>
                    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between mb-4">
                        <h3 class="text-lg font-bold">{{ product.code }} - {{ product.name }}</h3>
                        <div class="flex items-center">
                            <Link :href="route('catalog.accommodation.edit', product.id)" class="mr-1">
                                <AppEditButton title="Edit" />
                            </Link>
                            <AppDeleteButton @click="showDeleteConfirmation = true" title="Delete" />
                        </div>
                    </div>

                    <div class="grid md:grid-cols-2 gap-6">
                        <div>
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

                            <div class="mt-6">
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
                            </div>
                        </div>

                        <div>
                            <h4 class="text-md font-semibold mb-2">Availability & Booking</h4>
                            <AppSelect
                                v-model="selectedPoolId"
                                :options="poolOptions"
                                label="Resource Pool"
                                placeholder="Select pool"
                            />

                            <div v-if="selectedPool" class="mt-3 text-sm text-gray-600">
                                <p>Branch: {{ selectedPool.branch?.name ?? '-' }}</p>
                                <p>Instances: {{ selectedPool.instances?.length ?? 0 }}</p>
                            </div>

                            <div class="mt-4 space-y-3">
                                <div class="grid grid-cols-2 gap-2">
                                    <AppInput
                                        v-model="bookingForm.start_datetime"
                                        type="datetime-local"
                                        label="Check-in"
                                        :error="bookingErrors?.['lines.start_datetime']"
                                    />
                                    <AppInput
                                        v-model="bookingForm.end_datetime"
                                        type="datetime-local"
                                        label="Check-out"
                                        :error="bookingErrors?.['lines.end_datetime']"
                                    />
                                </div>
                                <div class="grid grid-cols-3 gap-2">
                                    <AppInput v-model="bookingForm.qty" type="number" min="1" label="Qty" />
                                    <AppInput v-model="bookingForm.unit_price" type="number" min="0" label="Rate" />
                                    <AppInput v-model="bookingForm.deposit_amount" type="number" min="0" label="Deposit" />
                                </div>
                                <div class="flex gap-2">
                                    <AppPrimaryButton type="button" @click="checkAvailability">
                                        Cek Ketersediaan
                                    </AppPrimaryButton>
                                    <AppSecondaryButton type="button" @click="submitBooking" :disabled="isSubmittingBooking">
                                        {{ isSubmittingBooking ? 'Booking...' : 'Hold Booking' }}
                                    </AppSecondaryButton>
                                </div>
                                <p v-if="availabilityError" class="text-sm text-red-600">{{ availabilityError }}</p>
                                <div v-if="availabilityResult" class="text-sm text-gray-700 bg-green-50 border border-green-200 rounded p-2">
                                    <p>{{ availabilityResult.available_qty }} / {{ availabilityResult.capacity }} tersedia</p>
                                </div>
                                <p v-if="bookingErrors?.general" class="text-sm text-red-600">{{ bookingErrors.general }}</p>
                                <div v-if="bookingSuccess" class="text-sm text-green-700 bg-green-50 border border-green-200 rounded p-2">
                                    Booking {{ bookingSuccess.booking_number }} dibuat.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 grid gap-6 md:grid-cols-2">
                        <div>
                            <h4 class="text-md font-semibold mb-2">Upcoming Availability</h4>
                            <div v-if="loadingCalendar" class="text-sm text-gray-500">Loading calendar...</div>
                            <ul v-else class="divide-y divide-gray-200 border border-gray-200 rounded">
                                <li v-for="day in calendarDays" :key="day.date" class="p-3 flex items-center justify-between">
                                    <div>
                                        <p class="font-semibold">{{ formatCalendarLabel(day.date) }}</p>
                                        <p class="text-xs text-gray-500">{{ day.available_qty }} / {{ day.capacity }} free</p>
                                    </div>
                                    <span
                                        class="text-xs px-2 py-1 rounded"
                                        :class="day.blocked ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700'"
                                    >
                                        {{ day.blocked ? 'Blocked' : 'Open' }}
                                    </span>
                                </li>
                                <li v-if="!calendarDays.length" class="p-3 text-sm text-gray-500">Belum ada data ketersediaan.</li>
                            </ul>
                        </div>
                        <div>
                            <h4 class="text-md font-semibold mb-2">Booking Form</h4>
                            <div class="space-y-3">
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
                            </div>
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

