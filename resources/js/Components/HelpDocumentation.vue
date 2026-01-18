<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { Dialog, DialogPanel } from '@headlessui/vue';
import { 
    XMarkIcon,
    MagnifyingGlassIcon,
    RocketLaunchIcon,
    CurrencyDollarIcon,
    ShoppingCartIcon,
    CubeIcon,
    PuzzlePieceIcon,
    BanknotesIcon,
    CalendarDaysIcon,
    CalculatorIcon,
    Cog8ToothIcon,
    ChevronRightIcon,
    CheckCircleIcon,
    LightBulbIcon,
    InformationCircleIcon,
    ArrowRightIcon,
} from '@heroicons/vue/24/solid';
import { QuestionMarkCircleIcon } from '@heroicons/vue/24/outline';
import helpSections from '@/constants/helpContent.js';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close']);

const searchQuery = ref('');
const activeSection = ref('getting-started');

// Icon component mapping
const iconComponents = {
    RocketLaunchIcon,
    CurrencyDollarIcon,
    ShoppingCartIcon,
    CubeIcon,
    PuzzlePieceIcon,
    BanknotesIcon,
    CalendarDaysIcon,
    CalculatorIcon,
    Cog8ToothIcon,
    QuestionMarkCircleIcon,
};

const getIconComponent = (iconName) => {
    return iconComponents[iconName] || QuestionMarkCircleIcon;
};

// Filter sections based on search
const filteredSections = computed(() => {
    if (!searchQuery.value.trim()) {
        return helpSections;
    }
    const query = searchQuery.value.toLowerCase();
    return helpSections.filter(section => {
        // Check section title
        if (section.title.toLowerCase().includes(query)) return true;
        // Check content
        return section.content.some(item => {
            if (item.title?.toLowerCase().includes(query)) return true;
            if (item.description?.toLowerCase().includes(query)) return true;
            if (item.steps?.some(step => 
                step.title.toLowerCase().includes(query) || 
                step.description.toLowerCase().includes(query)
            )) return true;
            if (item.tips?.some(tip => tip.toLowerCase().includes(query))) return true;
            return false;
        });
    });
});

const currentSection = computed(() => {
    return helpSections.find(s => s.id === activeSection.value) || helpSections[0];
});

watch(() => props.show, (newVal) => {
    if (newVal) {
        document.body.style.overflow = 'hidden';
        activeSection.value = 'getting-started';
        searchQuery.value = '';
    } else {
        document.body.style.overflow = null;
    }
});

const close = () => {
    emit('close');
};

const closeOnEscape = (e) => {
    if (e.key === 'Escape' && props.show) {
        close();
    }
};

onMounted(() => document.addEventListener('keydown', closeOnEscape));
onUnmounted(() => {
    document.removeEventListener('keydown', closeOnEscape);
    document.body.style.overflow = null;
});
</script>

<template>
    <Teleport to="body">
        <Dialog :open="show" @close="close" class="relative z-[200]">
            <div class="fixed inset-0 overflow-hidden">
                <!-- Backdrop -->
                <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm" @click="close"></div>

                <!-- Modal Container -->
                <div class="fixed inset-4 md:inset-8 lg:inset-12 flex items-center justify-center">
                    <transition
                        enter-active-class="ease-out duration-300"
                        enter-from-class="opacity-0 scale-95"
                        enter-to-class="opacity-100 scale-100"
                        leave-active-class="ease-in duration-200"
                        leave-from-class="opacity-100 scale-100"
                        leave-to-class="opacity-0 scale-95"
                    >
                        <DialogPanel 
                            class="w-full h-full max-w-6xl bg-white rounded-2xl shadow-2xl overflow-hidden flex flex-col"
                        >
                            <!-- Header -->
                            <div class="flex-shrink-0 bg-gradient-to-r from-blue-600 to-indigo-700 px-6 py-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
                                            <QuestionMarkCircleIcon class="w-6 h-6 text-white" />
                                        </div>
                                        <div>
                                            <h2 class="text-xl font-bold text-white">Pusat Bantuan</h2>
                                            <p class="text-blue-100 text-sm">Panduan lengkap penggunaan aplikasi</p>
                                        </div>
                                    </div>
                                    <button 
                                        @click="close"
                                        class="text-white/80 hover:text-white hover:bg-white/10 rounded-lg p-2 transition-colors"
                                    >
                                        <XMarkIcon class="w-6 h-6" />
                                    </button>
                                </div>
                                
                                <!-- Search -->
                                <div class="mt-4 relative">
                                    <MagnifyingGlassIcon class="w-5 h-5 text-blue-200 absolute left-3 top-1/2 -translate-y-1/2" />
                                    <input
                                        v-model="searchQuery"
                                        type="text"
                                        placeholder="Cari panduan..."
                                        class="w-full pl-10 pr-4 py-2.5 bg-white/10 border border-white/20 rounded-lg text-white placeholder-blue-200 focus:outline-none focus:ring-2 focus:ring-white/30 focus:bg-white/20 transition-all"
                                    />
                                </div>
                            </div>

                            <!-- Content -->
                            <div class="flex-1 flex overflow-hidden">
                                <!-- Sidebar -->
                                <div class="w-64 flex-shrink-0 border-r border-gray-200 bg-gray-50 overflow-y-auto hidden md:block">
                                    <nav class="p-4 space-y-1">
                                        <button
                                            v-for="section in filteredSections"
                                            :key="section.id"
                                            @click="activeSection = section.id"
                                            :class="[
                                                'w-full flex items-center space-x-3 px-3 py-2.5 rounded-lg text-left transition-all',
                                                activeSection === section.id 
                                                    ? 'bg-white shadow-sm border border-gray-200 text-gray-900' 
                                                    : 'text-gray-600 hover:bg-white hover:shadow-sm'
                                            ]"
                                        >
                                            <div 
                                                :class="[
                                                    'w-8 h-8 rounded-lg flex items-center justify-center bg-gradient-to-br',
                                                    section.color
                                                ]"
                                            >
                                                <component :is="getIconComponent(section.icon)" class="w-4 h-4 text-white" />
                                            </div>
                                            <span class="text-sm font-medium truncate">{{ section.title }}</span>
                                        </button>
                                    </nav>
                                </div>

                                <!-- Mobile Section Selector -->
                                <div class="md:hidden border-b border-gray-200 p-4 flex-shrink-0 overflow-x-auto">
                                    <div class="flex space-x-2">
                                        <button
                                            v-for="section in filteredSections"
                                            :key="section.id"
                                            @click="activeSection = section.id"
                                            :class="[
                                                'flex-shrink-0 px-3 py-2 rounded-lg text-sm font-medium transition-all',
                                                activeSection === section.id 
                                                    ? 'bg-blue-600 text-white' 
                                                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                                            ]"
                                        >
                                            {{ section.title }}
                                        </button>
                                    </div>
                                </div>

                                <!-- Main Content -->
                                <div class="flex-1 overflow-y-auto p-6 md:p-8">
                                    <!-- Section Header -->
                                    <div class="mb-8">
                                        <div class="flex items-center space-x-4 mb-4">
                                            <div 
                                                :class="[
                                                    'w-14 h-14 rounded-xl flex items-center justify-center bg-gradient-to-br shadow-lg',
                                                    currentSection.color
                                                ]"
                                            >
                                                <component :is="getIconComponent(currentSection.icon)" class="w-7 h-7 text-white" />
                                            </div>
                                            <div>
                                                <h3 class="text-2xl font-bold text-gray-900">{{ currentSection.title }}</h3>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Content Items -->
                                    <div class="space-y-8">
                                        <div 
                                            v-for="(item, index) in currentSection.content" 
                                            :key="index"
                                            class="bg-gray-50 rounded-xl p-6"
                                        >
                                            <!-- Item Title -->
                                            <h4 class="text-lg font-semibold text-gray-900 mb-3">{{ item.title }}</h4>
                                            
                                            <!-- Description -->
                                            <p v-if="item.description" class="text-gray-600 mb-4">
                                                {{ item.description }}
                                            </p>

                                            <!-- Flow Diagram -->
                                            <div v-if="item.flowDiagram" class="mb-4 p-4 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg border border-blue-100">
                                                <div class="flex items-center space-x-2 text-blue-700 font-medium">
                                                    <ArrowRightIcon class="w-4 h-4" />
                                                    <span>Alur: </span>
                                                    <span class="font-mono text-sm">{{ item.flowDiagram }}</span>
                                                </div>
                                            </div>

                                            <!-- Steps -->
                                            <div v-if="item.steps" class="space-y-4">
                                                <div 
                                                    v-for="step in item.steps" 
                                                    :key="step.step"
                                                    class="flex space-x-4"
                                                >
                                                    <div class="flex-shrink-0 w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold text-sm">
                                                        {{ step.step }}
                                                    </div>
                                                    <div class="flex-1 pt-0.5">
                                                        <h5 class="font-medium text-gray-900">{{ step.title }}</h5>
                                                        <p class="text-gray-600 text-sm mt-1">{{ step.description }}</p>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Note -->
                                            <div v-if="item.note" class="mt-4 flex items-start space-x-3 p-3 bg-yellow-50 rounded-lg border border-yellow-200">
                                                <InformationCircleIcon class="w-5 h-5 text-yellow-600 flex-shrink-0 mt-0.5" />
                                                <p class="text-yellow-800 text-sm">{{ item.note }}</p>
                                            </div>

                                            <!-- Tips -->
                                            <div v-if="item.tips" class="mt-4 space-y-2">
                                                <div 
                                                    v-for="(tip, tipIndex) in item.tips" 
                                                    :key="tipIndex"
                                                    class="flex items-start space-x-3 p-3 bg-green-50 rounded-lg border border-green-200"
                                                >
                                                    <LightBulbIcon class="w-5 h-5 text-green-600 flex-shrink-0 mt-0.5" />
                                                    <p class="text-green-800 text-sm">{{ tip }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Footer -->
                                    <div class="mt-12 pt-8 border-t border-gray-200">
                                        <div class="text-center">
                                            <p class="text-gray-500 text-sm">
                                                Butuh bantuan lebih lanjut? Hubungi tim support kami.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </DialogPanel>
                    </transition>
                </div>
            </div>
        </Dialog>
    </Teleport>
</template>
