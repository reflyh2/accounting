<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to check permissions based on route names.
 * 
 * Maps route patterns to required permissions automatically.
 * Permissions are in format: module.resource.action (e.g., purchase.purchase_order.view)
 */
class CheckPermission
{
    /**
     * Route name to permission resource mapping.
     * Format: route_name => [module, resource]
     */
    protected array $routePermissionMap = [
        // Settings
        'companies' => ['settings', 'companies'],
        'branches' => ['settings', 'branches'],
        'branch-groups' => ['settings', 'branch-groups'],
        'roles' => ['settings', 'roles'],
        'users' => ['settings', 'users'],
        'tax-jurisdictions' => ['settings', 'companies'],
        'tax-components' => ['settings', 'companies'],
        'tax-categories' => ['settings', 'companies'],
        'tax-rules' => ['settings', 'companies'],
        'company-bank-accounts' => ['settings', 'companies'],
        'gl-event-configurations' => ['settings', 'companies'],
        'document-templates' => ['settings', 'companies'],
        'partners' => ['settings', 'partners'],
        
        // Purchase
        'purchase-plans' => ['purchase', 'purchase_plan'],
        'purchase-orders' => ['purchase', 'purchase_order'],
        'goods-receipts' => ['purchase', 'goods_receipt'],
        'purchase-invoices' => ['purchase', 'purchase_invoice'],
        'purchase-returns' => ['purchase', 'purchase_return'],
        'purchasing-reports' => ['purchase', 'purchase_order'],
        
        // Sales
        'sales-orders' => ['sales', 'sales_order'],
        'sales-deliveries' => ['sales', 'delivery_order'],
        'sales-invoices' => ['sales', 'sales_invoice'],
        'sales-returns' => ['sales', 'sales_return'],
        'sales-reports' => ['sales', 'sales_order'],
        
        // Inventory
        'inventory.receipts' => ['inventory', 'stock'],
        'inventory.shipments' => ['inventory', 'stock'],
        'inventory.adjustments' => ['inventory', 'adjustment'],
        'inventory.transfers' => ['inventory', 'transfer'],
        
        // Accounting
        'accounts' => ['catalog', 'accounts'],
        'currencies' => ['catalog', 'currencies'],
        'journals' => ['accounting', 'journal'],
        'cash-receipt-journals' => ['accounting', 'receipt'],
        'cash-payment-journals' => ['accounting', 'payment'],
        'external-payables' => ['accounting', 'payment'],
        'external-receivables' => ['accounting', 'receipt'],
        'external-payable-payments' => ['accounting', 'payment'],
        'external-receivable-payments' => ['accounting', 'receipt'],
        'internal-debts' => ['accounting', 'payment'],
        'internal-debt-payments' => ['accounting', 'payment'],
        'general-ledger' => ['accounting', 'journal'],
        'cash-bank-book' => ['accounting', 'journal'],
        'income' => ['accounting', 'journal'],
        'balance-sheet' => ['accounting', 'journal'],
        
        // Manufacturing
        'bill-of-materials' => ['manufacturing', 'bom'],
        'work-orders' => ['manufacturing', 'work_order'],
        'component-issues' => ['manufacturing', 'production'],
        'component-scraps' => ['manufacturing', 'production'],
        'finished-goods-receipts' => ['manufacturing', 'production'],
        
        // Catalog
        'catalog.product-categories' => ['catalog', 'products'],
        'catalog.products' => ['catalog', 'products'],
        'catalog.price-lists' => ['catalog', 'products'],
        'catalog.price-list-items' => ['catalog', 'products'],
        'catalog.price-list-targets' => ['catalog', 'products'],
        'catalog.user-discount-limits' => ['catalog', 'products'],
        
        // Assets
        'asset-categories' => ['accounting', 'journal'],
        'assets' => ['accounting', 'journal'],
        'asset-purchases' => ['accounting', 'payment'],
        'asset-rentals' => ['accounting', 'payment'],
        'asset-sales' => ['accounting', 'receipt'],
        'asset-disposals' => ['accounting', 'journal'],
        'asset-transfers' => ['accounting', 'journal'],
        'asset-depreciations' => ['accounting', 'journal'],
        'asset-financing-agreements' => ['accounting', 'payment'],
        'asset-financing-payments' => ['accounting', 'payment'],
        'asset-invoice-payments' => ['accounting', 'payment'],
        
        // Costing
        'costing.cost-entries' => ['accounting', 'journal'],
        'costing.cost-pools' => ['accounting', 'journal'],
        'costing.cost-items' => ['accounting', 'journal'],
        'costing.cost-allocations' => ['accounting', 'journal'],
        
        // Booking
        'bookings' => ['sales', 'sales_order'],
        'resource-pools' => ['sales', 'sales_order'],
        'resource-instances' => ['sales', 'sales_order'],
    ];

    /**
     * Route action suffixes to permission action mapping.
     */
    protected array $actionMap = [
        'index' => 'view',
        'show' => 'view',
        'print' => 'view',
        'download' => 'view',
        'export-xlsx' => 'view',
        'export-csv' => 'view',
        'export-pdf' => 'view',
        'create' => 'create',
        'store' => 'create',
        'edit' => 'update',
        'update' => 'update',
        'destroy' => 'delete',
        'bulk-delete' => 'delete',
        'approve' => 'approve',
        'reject' => 'approve',
        'post' => 'post',
        'cancel' => 'cancel',
        'send' => 'update',
        'confirm' => 'update',
        'close' => 'update',
        'quote' => 'update',
        'reserve' => 'update',
        'release-reservation' => 'update',
        'check-in' => 'update',
        'check-out' => 'update',
        'transition' => 'update',
        'closeout' => 'update',
    ];

    /**
     * Routes that don't require permission checks.
     */
    protected array $excludedRoutes = [
        'dashboard',
        'profile.edit',
        'profile.update',
        'profile.destroy',
        'logout',
        'api.*',
        'verification.*',
        'password.*',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $routeName = $request->route()?->getName();
        
        if (!$routeName) {
            return $next($request);
        }

        // Check if route is excluded
        if ($this->isExcludedRoute($routeName)) {
            return $next($request);
        }

        // Get required permission
        $requiredPermission = $this->getRequiredPermission($routeName);
        
        if (!$requiredPermission) {
            // No permission mapping found - allow access (unmapped routes are public)
            return $next($request);
        }

        // Get tenant user
        $centralUser = Auth::user();
        if (!$centralUser) {
            abort(403, 'Unauthenticated.');
        }

        $tenantUser = User::where('global_id', $centralUser->global_id)->first();
        if (!$tenantUser) {
            abort(403, 'User not found in tenant.');
        }

        // Check if user has the permission (try/catch for non-existent permissions)
        try {
            if (!$tenantUser->hasPermissionTo($requiredPermission)) {
                abort(403, 'Anda tidak memiliki akses untuk melakukan tindakan ini.');
            }
        } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist $e) {
            // Permission doesn't exist in the system - allow access for now
            // This prevents errors for unmapped/new routes
            return $next($request);
        }

        return $next($request);
    }

    /**
     * Check if route should be excluded from permission checks.
     */
    protected function isExcludedRoute(string $routeName): bool
    {
        foreach ($this->excludedRoutes as $pattern) {
            if ($pattern === $routeName) {
                return true;
            }
            
            if (str_ends_with($pattern, '.*')) {
                $prefix = substr($pattern, 0, -2);
                if (str_starts_with($routeName, $prefix)) {
                    return true;
                }
            }
        }
        
        return false;
    }

    /**
     * Get required permission for a route.
     * Returns permission in format: module.resource.action
     */
    protected function getRequiredPermission(string $routeName): ?string
    {
        // Parse route name to get resource and action
        // e.g., "purchase-orders.index" -> resource: "purchase-orders", action: "index"
        $parts = explode('.', $routeName);
        
        if (count($parts) < 2) {
            return null;
        }

        $action = array_pop($parts);
        $resource = implode('.', $parts);

        // Get module and permission resource from mapping
        $mapping = $this->routePermissionMap[$resource] ?? null;
        
        if (!$mapping) {
            return null;
        }

        [$module, $permissionResource] = $mapping;

        // Get permission action
        $permissionAction = $this->actionMap[$action] ?? null;
        
        if (!$permissionAction) {
            return null;
        }

        // Return permission in format: module.resource.action
        return "{$module}.{$permissionResource}.{$permissionAction}";
    }
}
