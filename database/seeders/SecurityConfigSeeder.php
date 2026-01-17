<?php

namespace Database\Seeders;

use App\Models\AccountingPeriod;
use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStep;
use App\Models\Company;
use App\Models\FieldPermission;
use App\Models\Role;
use App\Models\RoleFieldPermission;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class SecurityConfigSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedApprovalWorkflows();
        $this->seedFieldPermissions();
        $this->seedAccountingPeriods();
    }

    /**
     * Seed approval workflows for document types.
     */
    private function seedApprovalWorkflows(): void
    {
        // Get roles for workflow steps
        $managerRole = Role::where('name', 'like', '%Manager%')->first();
        $financeRole = Role::where('name', 'like', '%Finance%')->first();
        $superAdminRole = Role::where('name', 'Super Administrator')->first();

        // Use Super Administrator as fallback if specific roles don't exist
        $approverRole = $managerRole ?? $superAdminRole;
        $financeApproverRole = $financeRole ?? $superAdminRole;

        if (!$approverRole) {
            return; // No roles to assign, skip workflow creation
        }

        $workflows = [
            [
                'name' => 'Purchase Order Approval',
                'document_type' => 'purchase_order',
                'description' => 'Standard approval for purchase orders',
                'steps' => [
                    ['step_order' => 1, 'role' => $approverRole, 'min_approvers' => 1],
                ],
            ],
            [
                'name' => 'Purchase Invoice Approval',
                'document_type' => 'purchase_invoice',
                'description' => 'Approval for purchase invoices',
                'steps' => [
                    ['step_order' => 1, 'role' => $financeApproverRole, 'min_approvers' => 1],
                ],
            ],
            [
                'name' => 'Sales Invoice Approval',
                'document_type' => 'sales_invoice',
                'description' => 'Approval for sales invoices',
                'steps' => [
                    ['step_order' => 1, 'role' => $financeApproverRole, 'min_approvers' => 1],
                ],
            ],
            [
                'name' => 'Payment Approval',
                'document_type' => 'payment',
                'description' => 'Multi-level approval for payments',
                'steps' => [
                    ['step_order' => 1, 'role' => $financeApproverRole, 'min_approvers' => 1],
                    ['step_order' => 2, 'role' => $approverRole, 'min_approvers' => 1],
                ],
            ],
            [
                'name' => 'Journal Approval',
                'document_type' => 'journal',
                'description' => 'Approval for journal entries',
                'steps' => [
                    ['step_order' => 1, 'role' => $financeApproverRole, 'min_approvers' => 1],
                ],
            ],
        ];

        foreach ($workflows as $workflowData) {
            $workflow = ApprovalWorkflow::firstOrCreate(
                ['document_type' => $workflowData['document_type'], 'company_id' => null],
                [
                    'name' => $workflowData['name'],
                    'description' => $workflowData['description'],
                    'is_active' => true,
                ]
            );

            foreach ($workflowData['steps'] as $stepData) {
                ApprovalWorkflowStep::firstOrCreate(
                    [
                        'approval_workflow_id' => $workflow->id,
                        'step_order' => $stepData['step_order'],
                    ],
                    [
                        'required_role_id' => $stepData['role']->id,
                        'min_approvers' => $stepData['min_approvers'],
                    ]
                );
            }
        }
    }

    /**
     * Seed field permissions for sensitive fields.
     */
    private function seedFieldPermissions(): void
    {
        $sensitiveFields = [
            // Cost-related fields
            ['model_type' => 'App\\Models\\GoodsReceiptLine', 'field_name' => 'unit_cost_base', 'description' => 'Harga pokok per unit'],
            ['model_type' => 'App\\Models\\SalesInvoiceLine', 'field_name' => 'unit_cost', 'description' => 'Harga pokok per unit'],
            ['model_type' => 'App\\Models\\SalesDeliveryLine', 'field_name' => 'unit_cost_base', 'description' => 'Harga pokok per unit'],
            ['model_type' => 'App\\Models\\CostLayer', 'field_name' => 'unit_cost', 'description' => 'Harga pokok per unit'],
            ['model_type' => 'App\\Models\\InventoryTransactionLine', 'field_name' => 'unit_cost', 'description' => 'Harga pokok per unit'],
            
            // Margin and pricing
            ['model_type' => 'App\\Models\\Product', 'field_name' => 'margin', 'description' => 'Margin produk'],
            ['model_type' => 'App\\Models\\Product', 'field_name' => 'supplier_price', 'description' => 'Harga supplier'],
            
            // Partner financial data
            ['model_type' => 'App\\Models\\Partner', 'field_name' => 'credit_limit', 'description' => 'Limit kredit partner'],
        ];

        // Get finance role for granting access
        $financeRole = Role::where('name', 'like', '%Finance%')->first();
        $superAdminRole = Role::where('name', 'Super Administrator')->first();

        foreach ($sensitiveFields as $fieldData) {
            $fieldPermission = FieldPermission::firstOrCreate(
                [
                    'model_type' => $fieldData['model_type'],
                    'field_name' => $fieldData['field_name'],
                ],
                [
                    'description' => $fieldData['description'],
                ]
            );

            // Grant view and edit access to Super Administrator
            if ($superAdminRole) {
                RoleFieldPermission::firstOrCreate(
                    [
                        'role_id' => $superAdminRole->id,
                        'field_permission_id' => $fieldPermission->id,
                    ],
                    [
                        'can_view' => true,
                        'can_edit' => true,
                    ]
                );
            }

            // Grant view access to Finance role (if exists)
            if ($financeRole && $financeRole->id !== $superAdminRole?->id) {
                RoleFieldPermission::firstOrCreate(
                    [
                        'role_id' => $financeRole->id,
                        'field_permission_id' => $fieldPermission->id,
                    ],
                    [
                        'can_view' => true,
                        'can_edit' => false,
                    ]
                );
            }
        }
    }

    /**
     * Seed accounting periods for each company.
     */
    private function seedAccountingPeriods(): void
    {
        $companies = Company::withoutGlobalScope('userCompanies')->get();
        
        // Generate periods for 2025 and 2026
        $years = [2025, 2026];
        $currentDate = Carbon::now();

        foreach ($companies as $company) {
            foreach ($years as $year) {
                for ($month = 1; $month <= 12; $month++) {
                    $startDate = Carbon::createFromDate($year, $month, 1);
                    $endDate = $startDate->copy()->endOfMonth();

                    // Determine status based on current date
                    $status = AccountingPeriod::STATUS_OPEN;
                    if ($endDate->lt($currentDate->copy()->startOfMonth())) {
                        // Past periods - leave open for now, can be closed manually
                        $status = AccountingPeriod::STATUS_OPEN;
                    }

                    AccountingPeriod::firstOrCreate(
                        [
                            'company_id' => $company->id,
                            'start_date' => $startDate->toDateString(),
                            'end_date' => $endDate->toDateString(),
                        ],
                        [
                            'name' => $startDate->format('M Y'),
                            'status' => $status,
                        ]
                    );
                }
            }
        }
    }
}
