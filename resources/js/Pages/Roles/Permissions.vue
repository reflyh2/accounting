<script setup>
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import AppBackLink from '@/Components/AppBackLink.vue';

const props = defineProps({
    role: Object,
    rolePermissions: Array,
    permissions: Object,
});

const form = useForm({
    permissions: props.rolePermissions,
});

const updatePermissions = () => {
    form.put(route('roles.update-permissions', props.role.id), {
        preserveScroll: true,
    });
};

const togglePermission = (permission) => {
    const index = form.permissions.indexOf(permission);
    if (index === -1) {
        form.permissions.push(permission);
    } else {
        form.permissions.splice(index, 1);
    }
};

const getPageName = (pageName) => {
    return pageName.replace(/-/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
};

const hasPermission = (permissionName) => {
    return form.permissions.includes(permissionName);
};

const pageGroups = computed(() => {
    return Object.keys(props.permissions || {});
});

const togglePagePermissions = (pageGroup, page) => {
    const actions = ['view', 'create', 'update', 'delete'];
    const allChecked = actions.every(action => hasPermission(`${pageGroup}.${page}.${action}`));
    
    actions.forEach(action => {
        const permission = `${pageGroup}.${page}.${action}`;
        if (allChecked) {
            const index = form.permissions.indexOf(permission);
            if (index !== -1) {
                form.permissions.splice(index, 1);
            }
        } else {
            if (!form.permissions.includes(permission)) {
                form.permissions.push(permission);
            }
        }
    });
};

const togglePageGroupPermissions = (pageGroup) => {
    const pages = Object.keys(props.permissions[pageGroup]);
    const actions = ['view', 'create', 'update', 'delete'];
    const allChecked = pages.every(page => 
        actions.every(action => hasPermission(`${pageGroup}.${page}.${action}`))
    );
    
    pages.forEach(page => {
        actions.forEach(action => {
            const permission = `${pageGroup}.${page}.${action}`;
            if (allChecked) {
                const index = form.permissions.indexOf(permission);
                if (index !== -1) {
                    form.permissions.splice(index, 1);
                }
            } else {
                if (!form.permissions.includes(permission)) {
                    form.permissions.push(permission);
                }
            }
        });
    });
};

const translationMap = {
    settings: {
        name: 'Pengaturan',
        pages: {
            companies: 'Perusahaan',
            'branch-groups': 'Grup Cabang',
            branches: 'Cabang',
            roles: 'Hak Akses',
            users: 'Pengguna',
        }
    },
    // Add more page groups and their translations here
};

const translatePageGroup = (pageGroup) => {
    return translationMap[pageGroup]?.name || pageGroup;
};

const translatePage = (pageGroup, page) => {
    return translationMap[pageGroup]?.pages[page] || getPageName(page);
};
</script>

<template>
   <Head :title="`Set Hak Akses untuk ${role.name}`" />

   <AuthenticatedLayout>
      <template #header>
         <h2>Set Hak Akses untuk {{ role.name }}</h2>
      </template>

      <div>
         <div class="mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
               <div class="p-6 text-gray-900">
                  <div class="mb-6">
                     <AppBackLink :href="route('roles.index')" text="Kembali ke Daftar Hak Akses" />
                  </div>

                  <form @submit.prevent="updatePermissions">
                     <div v-for="pageGroup in pageGroups" :key="pageGroup" class="mb-8">
                           <div class="flex justify-between items-center mb-4">
                              <h3 class="text-lg font-semibold capitalize">{{ translatePageGroup(pageGroup) }}</h3>
                              <button type="button" @click="togglePageGroupPermissions(pageGroup)" class="px-3 py-1 bg-gray-200 text-gray-700 rounded text-sm hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-opacity-50">
                                 Semua {{ translatePageGroup(pageGroup) }}
                              </button>
                           </div>
                           <table class="min-w-full divide-y divide-gray-200">
                              <thead class="bg-gray-50">
                                 <tr>
                                       <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                          Halaman
                                       </th>
                                       <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                          Lihat
                                       </th>
                                       <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                          Tambah
                                       </th>
                                       <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                          Ubah
                                       </th>
                                       <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                          Hapus
                                       </th>
                                       <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                       </th>
                                 </tr>
                              </thead>
                              <tbody class="bg-white divide-y divide-gray-200">
                                 <tr v-for="(pagePermissions, page) in permissions[pageGroup]" :key="page">
                                       <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                          {{ translatePage(pageGroup, page) }}
                                       </td>
                                       <td v-for="action in ['view', 'create', 'update', 'delete']" :key="action" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                          <input
                                             type="checkbox"
                                             :id="`${pageGroup}.${page}.${action}`"
                                             :value="`${pageGroup}.${page}.${action}`"
                                             :checked="hasPermission(`${pageGroup}.${page}.${action}`)"
                                             @change="togglePermission(`${pageGroup}.${page}.${action}`)"
                                             class="rounded border-gray-300 text-main-600 shadow-sm focus:border-main-300 focus:ring focus:ring-main-200 focus:ring-opacity-50"
                                          >
                                       </td>
                                       <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                          <button type="button" @click="togglePagePermissions(pageGroup, page)" class="px-3 py-1 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-opacity-50">
                                             Semua {{ translatePage(pageGroup, page) }}
                                          </button>
                                       </td>
                                 </tr>
                              </tbody>
                           </table>
                     </div>
                     <div class="flex justify-end mt-4">
                           <button type="submit" class="px-4 py-2 text-sm bg-main-600 text-white rounded hover:bg-main-700 focus:outline-none focus:ring-2 focus:ring-main-500 focus:ring-offset-2">
                              Simpan Hak Akses
                           </button>
                     </div>
                  </form>
               </div>
            </div>
         </div>
      </div>
   </AuthenticatedLayout>
</template>