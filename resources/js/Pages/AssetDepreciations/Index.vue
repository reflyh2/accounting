<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
  schedules: Object,
  depreciationMethods: Object,
});

function processAll() {
  router.post(route('asset-depreciations.process-all'));
}
</script>

<template>
  <Head title="Penyusutan/Amortisasi - Belum Diproses" />
  <AuthenticatedLayout>
    <template #header>
      <h2>Penyusutan/Amortisasi - Belum Diproses (s.d. hari ini)</h2>
    </template>

    <div class="bg-white shadow-sm sm:rounded border border-gray-200 p-4">
      <div class="flex justify-between mb-3">
        <div />
        <AppPrimaryButton @click="processAll">Proses Semua</AppPrimaryButton>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="bg-main-100">
              <th class="text-left p-2">Tanggal</th>
              <th class="text-left p-2">Asset</th>
              <th class="text-left p-2">Cabang</th>
              <th class="text-right p-2">Jumlah</th>
              <th class="text-left p-2">Metode</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="s in schedules.data" :key="s.id" class="border-b">
              <td class="p-2">{{ new Date(s.schedule_date).toLocaleDateString('id-ID', {day: '2-digit', month: '2-digit', year: 'numeric'}) }}</td>
              <td class="p-2"><Link :href="route('assets.show', s.asset.id)">{{ s.asset.code }} - {{ s.asset.name }}</Link></td>
              <td class="p-2">{{ s.asset.branch.name }}</td>
              <td class="p-2 text-right">{{ formatNumber(s.amount) }}</td>
              <td class="p-2">{{ depreciationMethods[s.method] }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="mt-4">
        <div class="flex gap-2" v-if="schedules.links">
          <Link v-for="link in schedules.links" :key="link.url + link.label" :href="link.url || '#'" v-html="link.label" :class="[{ 'text-main-600 font-semibold': link.active }, 'px-2 py-1 border rounded']" />
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
  
</template>

