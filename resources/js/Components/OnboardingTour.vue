<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import axios from 'axios';
import { onboardingSteps } from '@/constants/onboardingSteps';
import {
    SparklesIcon,
    ChartBarIcon,
    Bars3Icon,
    ShoppingCartIcon,
    ClipboardDocumentListIcon,
    CalculatorIcon,
    CubeIcon,
    ScaleIcon,
    ChartPieIcon,
    Cog8ToothIcon,
    RocketLaunchIcon,
    XMarkIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    CheckIcon,
    CurrencyDollarIcon,
    ArrowTrendingUpIcon,
    DocumentDuplicateIcon,
    DocumentTextIcon,
    TruckIcon,
    ReceiptRefundIcon,
    DocumentPlusIcon,
    InboxArrowDownIcon,
    BanknotesIcon,
    BookOpenIcon,
    TableCellsIcon,
    DocumentChartBarIcon,
    TagIcon,
    FolderIcon,
    CircleStackIcon,
    PlusCircleIcon,
    ArrowsPointingOutIcon,
    DocumentArrowDownIcon,
    FunnelIcon,
    EyeIcon,
    AdjustmentsHorizontalIcon,
    UsersIcon,
    BuildingOfficeIcon,
    PlusIcon,
    UserGroupIcon,
    QuestionMarkCircleIcon,
    ChevronDoubleLeftIcon,
    MagnifyingGlassIcon,
    UserCircleIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    initialStep: {
        type: Number,
        default: 0,
    },
});

const emit = defineEmits(['complete', 'skip', 'close']);

const currentStep = ref(props.initialStep);
const isAnimating = ref(false);
const showConfetti = ref(false);

// Icon mapping for dynamic rendering
const iconMap = {
    SparklesIcon,
    ChartBarIcon,
    Bars3Icon,
    ShoppingCartIcon,
    ClipboardDocumentListIcon,
    CalculatorIcon,
    CubeIcon,
    ScaleIcon,
    ChartPieIcon,
    Cog8ToothIcon,
    RocketLaunchIcon,
    CurrencyDollarIcon,
    ArrowTrendingUpIcon,
    DocumentDuplicateIcon,
    DocumentTextIcon,
    TruckIcon,
    ReceiptRefundIcon,
    DocumentPlusIcon,
    InboxArrowDownIcon,
    BanknotesIcon,
    BookOpenIcon,
    TableCellsIcon,
    DocumentChartBarIcon,
    TagIcon,
    FolderIcon,
    CircleStackIcon,
    PlusCircleIcon,
    ArrowsPointingOutIcon,
    DocumentArrowDownIcon,
    FunnelIcon,
    EyeIcon,
    AdjustmentsHorizontalIcon,
    UsersIcon,
    BuildingOfficeIcon,
    PlusIcon,
    UserGroupIcon,
    QuestionMarkCircleIcon,
    ChevronDoubleLeftIcon,
    MagnifyingGlassIcon,
    UserCircleIcon,
};

const steps = onboardingSteps;
const totalSteps = steps.length;

const currentStepData = computed(() => steps[currentStep.value]);
const isFirstStep = computed(() => currentStep.value === 0);
const isLastStep = computed(() => currentStep.value === totalSteps - 1);
const progressPercentage = computed(() => ((currentStep.value + 1) / totalSteps) * 100);

const getIcon = (iconName) => iconMap[iconName] || SparklesIcon;

const goToStep = async (stepIndex) => {
    if (isAnimating.value || stepIndex < 0 || stepIndex >= totalSteps) return;
    
    isAnimating.value = true;
    currentStep.value = stepIndex;
    
    // Save progress to server
    try {
        await axios.post(route('onboarding.progress'), { step: stepIndex });
    } catch (error) {
        console.error('Failed to save onboarding progress:', error);
    }
    
    setTimeout(() => {
        isAnimating.value = false;
    }, 300);
};

const nextStep = () => {
    if (!isLastStep.value) {
        goToStep(currentStep.value + 1);
    }
};

const prevStep = () => {
    if (!isFirstStep.value) {
        goToStep(currentStep.value - 1);
    }
};

const completeOnboarding = async () => {
    showConfetti.value = true;
    
    try {
        await axios.post(route('onboarding.complete'));
    } catch (error) {
        console.error('Failed to complete onboarding:', error);
    }
    
    setTimeout(() => {
        showConfetti.value = false;
        emit('complete');
        router.reload({ only: ['onboarding'] });
    }, 2000);
};

const skipOnboarding = async () => {
    try {
        await axios.post(route('onboarding.skip'));
    } catch (error) {
        console.error('Failed to skip onboarding:', error);
    }
    
    emit('skip');
    router.reload({ only: ['onboarding'] });
};

// Watch for prop changes
watch(() => props.initialStep, (newStep) => {
    currentStep.value = newStep;
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-300"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-300"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="show" class="fixed inset-0 z-[9999] flex items-center justify-center">
                <!-- Backdrop with blur -->
                <div class="absolute inset-0 bg-gray-900/80 backdrop-blur-sm"></div>
                
                <!-- Confetti Animation -->
                <div v-if="showConfetti" class="absolute inset-0 pointer-events-none overflow-hidden">
                    <div v-for="i in 50" :key="i" 
                         class="confetti-piece"
                         :style="{
                             left: Math.random() * 100 + '%',
                             animationDelay: Math.random() * 3 + 's',
                             backgroundColor: ['#f59e0b', '#10b981', '#3b82f6', '#8b5cf6', '#ec4899'][Math.floor(Math.random() * 5)]
                         }">
                    </div>
                </div>
                
                <!-- Modal Container -->
                <div class="relative w-full max-w-2xl mx-4 animate-slide-up">
                    <!-- Progress Bar -->
                    <div class="mb-4 flex items-center justify-between px-2">
                        <div class="flex-1 h-2 bg-white/20 rounded-full overflow-hidden mr-4">
                            <div 
                                class="h-full bg-gradient-to-r from-blue-400 to-purple-500 transition-all duration-500 ease-out rounded-full"
                                :style="{ width: progressPercentage + '%' }"
                            ></div>
                        </div>
                        <span class="text-white/80 text-sm font-medium whitespace-nowrap">
                            {{ currentStep + 1 }} / {{ totalSteps }}
                        </span>
                    </div>
                    
                    <!-- Card -->
                    <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
                        <!-- Header with gradient -->
                        <div 
                            class="px-8 py-10 text-center text-white relative overflow-hidden"
                            :class="'bg-gradient-to-br ' + currentStepData.color"
                        >
                            <!-- Decorative circles -->
                            <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -translate-y-1/2 translate-x-1/2"></div>
                            <div class="absolute bottom-0 left-0 w-24 h-24 bg-white/10 rounded-full translate-y-1/2 -translate-x-1/2"></div>
                            
                            <!-- Close button -->
                            <button 
                                @click="skipOnboarding"
                                class="absolute top-4 right-4 p-2 rounded-full hover:bg-white/20 transition-colors"
                                title="Lewati"
                            >
                                <XMarkIcon class="w-5 h-5" />
                            </button>
                            
                            <!-- Icon -->
                            <div class="relative z-10 mb-4 inline-flex items-center justify-center w-20 h-20 bg-white/20 backdrop-blur rounded-2xl">
                                <component :is="getIcon(currentStepData.icon)" class="w-10 h-10 text-white" />
                            </div>
                            
                            <!-- Title -->
                            <Transition
                                mode="out-in"
                                enter-active-class="transition-all duration-300"
                                enter-from-class="opacity-0 translate-y-4"
                                enter-to-class="opacity-100 translate-y-0"
                                leave-active-class="transition-all duration-200"
                                leave-from-class="opacity-100"
                                leave-to-class="opacity-0 -translate-y-4"
                            >
                                <div :key="currentStep">
                                    <h2 class="text-3xl font-bold mb-2">{{ currentStepData.title }}</h2>
                                    <p class="text-white/80 text-lg">{{ currentStepData.subtitle }}</p>
                                </div>
                            </Transition>
                        </div>
                        
                        <!-- Content -->
                        <div class="px-8 py-8">
                            <Transition
                                mode="out-in"
                                enter-active-class="transition-all duration-300"
                                enter-from-class="opacity-0 translate-x-8"
                                enter-to-class="opacity-100 translate-x-0"
                                leave-active-class="transition-all duration-200"
                                leave-from-class="opacity-100"
                                leave-to-class="opacity-0 -translate-x-8"
                            >
                                <div :key="currentStep">
                                    <p class="text-gray-600 text-center mb-8 text-lg leading-relaxed">
                                        {{ currentStepData.description }}
                                    </p>
                                    
                                    <!-- Features -->
                                    <div class="space-y-4">
                                        <div 
                                            v-for="(feature, index) in currentStepData.features" 
                                            :key="index"
                                            class="flex items-center p-4 bg-gray-50 rounded-xl hover:bg-gray-100 transition-colors"
                                        >
                                            <div class="flex-shrink-0 w-10 h-10 bg-gradient-to-br rounded-lg flex items-center justify-center mr-4"
                                                 :class="currentStepData.color">
                                                <component :is="getIcon(feature.icon)" class="w-5 h-5 text-white" />
                                            </div>
                                            <span class="text-gray-700 font-medium">{{ feature.text }}</span>
                                        </div>
                                    </div>
                                </div>
                            </Transition>
                        </div>
                        
                        <!-- Footer with navigation -->
                        <div class="px-8 py-6 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
                            <!-- Skip button -->
                            <button 
                                v-if="!isLastStep"
                                @click="skipOnboarding"
                                class="text-gray-500 hover:text-gray-700 font-medium transition-colors"
                            >
                                Lewati
                            </button>
                            <div v-else></div>
                            
                            <!-- Navigation buttons -->
                            <div class="flex items-center space-x-3">
                                <button 
                                    v-if="!isFirstStep"
                                    @click="prevStep"
                                    class="flex items-center px-4 py-2 text-gray-600 hover:text-gray-800 font-medium transition-colors"
                                >
                                    <ChevronLeftIcon class="w-5 h-5 mr-1" />
                                    Kembali
                                </button>
                                
                                <button 
                                    v-if="!isLastStep"
                                    @click="nextStep"
                                    class="flex items-center px-6 py-3 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-xl hover:from-blue-600 hover:to-indigo-700 transition-all shadow-lg shadow-blue-500/25"
                                >
                                    Lanjut
                                    <ChevronRightIcon class="w-5 h-5 ml-1" />
                                </button>
                                
                                <button 
                                    v-else
                                    @click="completeOnboarding"
                                    class="flex items-center px-6 py-3 bg-gradient-to-r from-green-500 to-emerald-600 text-white font-semibold rounded-xl hover:from-green-600 hover:to-emerald-700 transition-all shadow-lg shadow-green-500/25"
                                >
                                    <CheckIcon class="w-5 h-5 mr-2" />
                                    Mulai Sekarang
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Step indicators -->
                    <div class="flex justify-center mt-6 space-x-2">
                        <button 
                            v-for="(step, index) in steps" 
                            :key="step.id"
                            @click="goToStep(index)"
                            class="w-2.5 h-2.5 rounded-full transition-all duration-300"
                            :class="[
                                index === currentStep 
                                    ? 'bg-white w-8' 
                                    : index < currentStep 
                                        ? 'bg-white/60 hover:bg-white/80' 
                                        : 'bg-white/30 hover:bg-white/50'
                            ]"
                            :title="step.title"
                        ></button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.animate-slide-up {
    animation: slideUp 0.4s ease-out;
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.confetti-piece {
    position: absolute;
    width: 10px;
    height: 10px;
    top: -10px;
    animation: confetti-fall 3s ease-in-out infinite;
}

@keyframes confetti-fall {
    0% {
        transform: translateY(-10px) rotate(0deg);
        opacity: 1;
    }
    100% {
        transform: translateY(100vh) rotate(720deg);
        opacity: 0;
    }
}

.from-gradient-start {
    --tw-gradient-from: #3b82f6;
}

.to-gradient-end {
    --tw-gradient-to: #8b5cf6;
}
</style>
