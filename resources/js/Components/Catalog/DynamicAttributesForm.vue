<script setup>
import { computed } from 'vue';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppCheckbox from '@/Components/AppCheckbox.vue';
import { PlusCircleIcon, TrashIcon } from '@heroicons/vue/24/solid';

const props = defineProps({
    defs: { type: Array, default: () => [] },
    modelValue: { type: Object, default: () => ({}) },
    errors: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['update:modelValue']);

function updateValue(code, value) {
    const next = { ...props.modelValue, [code]: value };
    emit('update:modelValue', next);
}

function toArray(value) {
    if (Array.isArray(value)) return value;
    if (value === null || value === undefined || value === '') return [];
    return [value];
}

function addVariantValue(def) {
    const arr = toArray(props.modelValue[def.code]);
    arr.push(def.data_type === 'number' ? 0 : '');
    updateValue(def.code, arr);
}

function updateVariantValue(def, index, value) {
    const arr = toArray(props.modelValue[def.code]);
    arr[index] = value;
    updateValue(def.code, arr);
}

function removeVariantValue(def, index) {
    const arr = toArray(props.modelValue[def.code]);
    arr.splice(index, 1);
    updateValue(def.code, arr);
}

const normalizedDefs = computed(() => props.defs || []);
</script>

<template>
    <div class="space-y-3">
        <div v-for="def in normalizedDefs" :key="def.id">
            <component :is="'div'">
                <div class="flex items-center mb-1">
                    <label class="text-sm font-medium">
                        {{ def.label }}:
                    </label>
                    <span v-if="def.is_variant_axis" class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200">
                        Variant
                    </span>
                </div>
                <template v-if="def.data_type === 'string'">
                    <template v-if="def.is_variant_axis">
                        <div class="space-y-2">
                            <div v-for="(val, idx) in toArray(modelValue[def.code])" :key="idx" class="flex items-center gap-2">
                                <AppInput
                                    :modelValue="val"
                                    @update:modelValue="v => updateVariantValue(def, idx, v)"
                                    :error="errors?.[def.code]"
                                    :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                                />
                                <button type="button" class="text-red-600 hover:text-red-800" @click="removeVariantValue(def, idx)" title="Remove">
                                    <TrashIcon class="w-5 h-5" />
                                </button>
                            </div>
                            <button type="button" class="text-main-600 hover:text-main-800 inline-flex items-center" @click="addVariantValue(def)">
                                <PlusCircleIcon class="w-5 h-5 mr-1" /> Tambah Variant
                            </button>
                        </div>
                    </template>
                    <template v-else>
                    <AppInput
                        :modelValue="modelValue[def.code] ?? ''"
                        @update:modelValue="v => updateValue(def.code, v)"
                        :error="errors?.[def.code]"
                        placeholder=""
                    />
                    </template>
                </template>
                <template v-else-if="def.data_type === 'number'">
                    <template v-if="def.is_variant_axis">
                        <div class="space-y-2">
                            <div v-for="(val, idx) in toArray(modelValue[def.code])" :key="idx" class="flex items-center gap-2">
                                <AppInput
                                    :modelValue="val"
                                    @update:modelValue="v => updateVariantValue(def, idx, v)"
                                    :error="errors?.[def.code]"
                                    :numberFormat="true"
                                    :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                                />
                                <button type="button" class="text-red-600 hover:text-red-800" @click="removeVariantValue(def, idx)" title="Remove">
                                    <TrashIcon class="w-5 h-5" />
                                </button>
                            </div>
                            <button type="button" class="text-main-600 hover:text-main-800 inline-flex items-center" @click="addVariantValue(def)">
                                <PlusCircleIcon class="w-5 h-5 mr-1" /> Add Value
                            </button>
                        </div>
                    </template>
                    <template v-else>
                        <AppInput
                            :modelValue="modelValue[def.code] ?? null"
                            @update:modelValue="v => updateValue(def.code, v)"
                            :error="errors?.[def.code]"
                            :numberFormat="true"
                        />
                    </template>
                </template>
                <template v-else-if="def.data_type === 'boolean'">
                    <AppCheckbox
                        :modelValue="!!modelValue[def.code]"
                        @update:modelValue="v => updateValue(def.code, v)"
                        :error="errors?.[def.code]"
                    />
                    <p v-if="def.is_variant_axis" class="text-xs text-gray-500 mt-1">Boolean attributes are not typically used as variant axes.</p>
                </template>
                <template v-else-if="def.data_type === 'select'">
                    <AppSelect
                        :modelValue="def.is_variant_axis ? (modelValue[def.code] ?? []) : (modelValue[def.code] ?? null)"
                        @update:modelValue="v => updateValue(def.code, v)"
                        :options="(def.options_json || []).map(o => ({ value: o, label: o }))"
                        :error="errors?.[def.code]"
                        placeholder="Pilih"
                        :multiple="!!def.is_variant_axis"
                    />
                </template>
                <template v-else-if="def.data_type === 'multiselect'">
                    <AppSelect
                        :modelValue="modelValue[def.code] ?? []"
                        @update:modelValue="v => updateValue(def.code, v)"
                        :options="(def.options_json || []).map(o => ({ value: o, label: o }))"
                        :error="errors?.[def.code]"
                        placeholder="Pilih"
                        multiple
                    />
                </template>
                <template v-else-if="def.data_type === 'date'">
                    <template v-if="def.is_variant_axis">
                        <div class="space-y-2">
                            <div v-for="(val, idx) in toArray(modelValue[def.code])" :key="idx" class="flex items-center gap-2">
                                <AppInput
                                    :modelValue="val"
                                    @update:modelValue="v => updateVariantValue(def, idx, v)"
                                    :error="errors?.[def.code]"
                                    type="date"
                                    :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                                />
                                <button type="button" class="text-red-600 hover:text-red-800" @click="removeVariantValue(def, idx)" title="Remove">
                                    <TrashIcon class="w-5 h-5" />
                                </button>
                            </div>
                            <button type="button" class="text-main-600 hover:text-main-800 inline-flex items-center" @click="addVariantValue(def)">
                                <PlusCircleIcon class="w-5 h-5 mr-1" /> Add Value
                            </button>
                        </div>
                    </template>
                    <template v-else>
                        <AppInput
                            :modelValue="modelValue[def.code] ?? ''"
                            @update:modelValue="v => updateValue(def.code, v)"
                            :error="errors?.[def.code]"
                            type="date"
                        />
                    </template>
                </template>
                <template v-else-if="def.data_type === 'datetime'">
                    <template v-if="def.is_variant_axis">
                        <div class="space-y-2">
                            <div v-for="(val, idx) in toArray(modelValue[def.code])" :key="idx" class="flex items-center gap-2">
                                <AppInput
                                    :modelValue="val"
                                    @update:modelValue="v => updateVariantValue(def, idx, v)"
                                    :error="errors?.[def.code]"
                                    type="datetime-local"
                                    :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                                />
                                <button type="button" class="text-red-600 hover:text-red-800" @click="removeVariantValue(def, idx)" title="Remove">
                                    <TrashIcon class="w-5 h-5" />
                                </button>
                            </div>
                            <button type="button" class="text-main-600 hover:text-main-800 inline-flex items-center" @click="addVariantValue(def)">
                                <PlusCircleIcon class="w-5 h-5 mr-1" /> Add Value
                            </button>
                        </div>
                    </template>
                    <template v-else>
                        <AppInput
                            :modelValue="modelValue[def.code] ?? ''"
                            @update:modelValue="v => updateValue(def.code, v)"
                            :error="errors?.[def.code]"
                            type="datetime-local"
                        />
                    </template>
                </template>
                <template v-else>
                    <AppInput
                        :modelValue="modelValue[def.code] ?? ''"
                        @update:modelValue="v => updateValue(def.code, v)"
                        :error="errors?.[def.code]"
                    />
                </template>
            </component>
        </div>
    </div>
</template>


