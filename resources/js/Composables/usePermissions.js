import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Composable for checking user permissions in Vue components.
 * 
 * Permission format: module.resource.action
 * e.g., 'purchase.purchase_order.view', 'sales.sales_order.create'
 * 
 * Usage:
 * const { can, canAny, hasModuleAccess, isModuleEnabled } = usePermissions();
 * 
 * if (can('purchase.purchase_order.view')) { ... }
 * if (hasModuleAccess('purchase')) { ... }
 * if (isModuleEnabled('manufacturing')) { ... }
 */
export function usePermissions() {
    const page = usePage();
    
    const permissions = computed(() => {
        return page.props.auth?.permissions || [];
    });

    const enabledModules = computed(() => {
        return page.props.enabledModules || [];
    });

    /**
     * Check if user has a specific permission.
     * @param {string} permission - Permission name to check (e.g., 'purchase.purchase_order.view')
     * @returns {boolean}
     */
    function can(permission) {
        return permissions.value.includes(permission);
    }

    /**
     * Check if user has any of the specified permissions.
     * @param {string[]} permissionList - Array of permission names
     * @returns {boolean}
     */
    function canAny(permissionList) {
        return permissionList.some(permission => permissions.value.includes(permission));
    }

    /**
     * Check if user has all of the specified permissions.
     * @param {string[]} permissionList - Array of permission names
     * @returns {boolean}
     */
    function canAll(permissionList) {
        return permissionList.every(permission => permissions.value.includes(permission));
    }

    /**
     * Check if a module is enabled for the current company.
     * @param {string} module - Module name
     * @returns {boolean}
     */
    function isModuleEnabled(module) {
        // If no enabledModules defined, all modules are enabled
        if (!enabledModules.value || enabledModules.value.length === 0) {
            return true;
        }
        return enabledModules.value.includes(module);
    }

    /**
     * Check if user has access to a module.
     * This combines role permissions AND company module access.
     * Module names: settings, purchase, sales, booking, inventory, accounting, manufacturing, catalog, assets, costing
     * @param {string} module - Module name
     * @returns {boolean}
     */
    function hasModuleAccess(module) {
        // First check if module is enabled for the company
        if (!isModuleEnabled(module)) {
            return false;
        }
        // Then check if user has any permission for this module
        return permissions.value.some(permission => permission.startsWith(`${module}.`));
    }

    /**
     * Check if user has view access for a specific resource.
     * @param {string} module - Module name (e.g., 'purchase')
     * @param {string} resource - Resource name (e.g., 'purchase_order')
     * @returns {boolean}
     */
    function canView(module, resource) {
        return can(`${module}.${resource}.view`);
    }

    /**
     * Check if user has create access for a specific resource.
     * @param {string} module - Module name
     * @param {string} resource - Resource name
     * @returns {boolean}
     */
    function canCreate(module, resource) {
        return can(`${module}.${resource}.create`);
    }

    /**
     * Check if user has update access for a specific resource.
     * @param {string} module - Module name
     * @param {string} resource - Resource name
     * @returns {boolean}
     */
    function canUpdate(module, resource) {
        return can(`${module}.${resource}.update`);
    }

    /**
     * Check if user has delete access for a specific resource.
     * @param {string} module - Module name
     * @param {string} resource - Resource name
     * @returns {boolean}
     */
    function canDelete(module, resource) {
        return can(`${module}.${resource}.delete`);
    }

    /**
     * Check if user has approve access for a specific resource.
     * @param {string} module - Module name
     * @param {string} resource - Resource name
     * @returns {boolean}
     */
    function canApprove(module, resource) {
        return can(`${module}.${resource}.approve`);
    }

    return {
        permissions,
        enabledModules,
        can,
        canAny,
        canAll,
        isModuleEnabled,
        hasModuleAccess,
        canView,
        canCreate,
        canUpdate,
        canDelete,
        canApprove,
    };
}
