<?php

namespace App\Services\Security;

use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStep;
use App\Models\DocumentApproval;
use App\Models\User;
use App\Exceptions\DocumentStateException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ApprovalWorkflowService
{
    /**
     * Initialize approval workflow for a document.
     */
    public function initializeWorkflow(Model $document, string $documentType, ?float $amount = null): ?ApprovalWorkflow
    {
        $companyId = $document->company_id ?? null;
        
        $workflow = ApprovalWorkflow::findApplicable($documentType, $amount, $companyId);

        if (!$workflow) {
            return null;
        }

        // Create pending approval entries for the first step
        $firstStep = $workflow->steps()->first();
        
        if ($firstStep) {
            $this->createPendingApprovalsForStep($document, $documentType, $workflow, $firstStep);
        }

        return $workflow;
    }

    /**
     * Create pending approval entries for a workflow step.
     */
    protected function createPendingApprovalsForStep(
        Model $document,
        string $documentType,
        ApprovalWorkflow $workflow,
        ApprovalWorkflowStep $step
    ): void {
        // Get users with the required role who can approve
        $potentialApprovers = User::whereHas('roles', function($q) use ($step) {
            $q->where('roles.id', $step->required_role_id);
        })->get();

        foreach ($potentialApprovers as $approver) {
            // Check segregation of duties - creator cannot be approver
            if ($this->violatesSegregationOfDuties($document, $approver)) {
                continue;
            }

            DocumentApproval::firstOrCreate([
                'document_type' => $documentType,
                'document_id' => $document->getKey(),
                'approval_workflow_id' => $workflow->id,
                'approval_workflow_step_id' => $step->id,
                'approver_id' => $approver->global_id,
            ], [
                'status' => DocumentApproval::STATUS_PENDING,
            ]);
        }
    }

    /**
     * Submit an approval decision.
     */
    public function submitApproval(
        Model $document,
        string $documentType,
        User $approver,
        bool $approved,
        ?string $notes = null
    ): DocumentApproval {
        // Find the pending approval for this user
        $approval = DocumentApproval::forDocument($documentType, $document->getKey())
            ->where('approver_id', $approver->global_id)
            ->pending()
            ->firstOrFail();

        // Validate segregation of duties
        if ($this->violatesSegregationOfDuties($document, $approver)) {
            throw new DocumentStateException('Segregation of duties violation: you cannot approve your own document.');
        }

        // Record the decision
        if ($approved) {
            $approval->approve($notes);
        } else {
            $approval->reject($notes);
        }

        // Check if we need to advance to the next step
        if ($approved) {
            $this->checkAndAdvanceWorkflow($document, $documentType, $approval);
        }

        return $approval;
    }

    /**
     * Check if the current step is complete and advance to the next step.
     */
    protected function checkAndAdvanceWorkflow(
        Model $document,
        string $documentType,
        DocumentApproval $currentApproval
    ): void {
        $step = $currentApproval->step;
        $workflow = $currentApproval->workflow;

        // Check if this step is now complete
        if ($step->isCompleted($documentType, $document->getKey())) {
            // Find the next step
            $nextStep = $workflow->steps()
                ->where('step_order', '>', $step->step_order)
                ->orderBy('step_order')
                ->first();

            if ($nextStep) {
                // Create pending approvals for the next step
                $this->createPendingApprovalsForStep($document, $documentType, $workflow, $nextStep);
            }
        }
    }

    /**
     * Check if all workflow steps are complete.
     */
    public function isWorkflowComplete(Model $document, string $documentType): bool
    {
        $workflow = $this->getActiveWorkflow($document, $documentType);

        if (!$workflow) {
            return true; // No workflow means no approval needed
        }

        foreach ($workflow->steps as $step) {
            if (!$step->isCompleted($documentType, $document->getKey())) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the active workflow for a document.
     */
    public function getActiveWorkflow(Model $document, string $documentType): ?ApprovalWorkflow
    {
        $approval = DocumentApproval::forDocument($documentType, $document->getKey())->first();
        
        return $approval?->workflow;
    }

    /**
     * Get pending approvals for a user.
     */
    public function getPendingApprovalsForUser(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return DocumentApproval::where('approver_id', $user->global_id)
            ->pending()
            ->with(['workflow', 'step', 'step.requiredRole'])
            ->get();
    }

    /**
     * Check if the approval request violates segregation of duties.
     */
    public function violatesSegregationOfDuties(Model $document, User $approver): bool
    {
        // Check if the document has a created_by field
        if (isset($document->created_by)) {
            return $document->created_by === $approver->global_id;
        }

        // Check for owner_user_id field
        if (isset($document->owner_user_id)) {
            return $document->owner_user_id === $approver->global_id;
        }

        return false;
    }

    /**
     * Check if a user can approve a specific document.
     */
    public function canUserApprove(Model $document, string $documentType, User $user): bool
    {
        // Check segregation of duties
        if ($this->violatesSegregationOfDuties($document, $user)) {
            return false;
        }

        // Check if user has a pending approval
        return DocumentApproval::forDocument($documentType, $document->getKey())
            ->where('approver_id', $user->global_id)
            ->pending()
            ->exists();
    }

    /**
     * Get the current approval status for a document.
     */
    public function getApprovalStatus(Model $document, string $documentType): array
    {
        $approvals = DocumentApproval::forDocument($documentType, $document->getKey())
            ->with(['approver', 'step'])
            ->get();

        $workflow = $this->getActiveWorkflow($document, $documentType);

        return [
            'workflow' => $workflow,
            'is_complete' => $this->isWorkflowComplete($document, $documentType),
            'approvals' => $approvals,
            'current_step' => $this->getCurrentStep($document, $documentType),
        ];
    }

    /**
     * Get the current step in the workflow.
     */
    public function getCurrentStep(Model $document, string $documentType): ?ApprovalWorkflowStep
    {
        $workflow = $this->getActiveWorkflow($document, $documentType);

        if (!$workflow) {
            return null;
        }

        foreach ($workflow->steps as $step) {
            if (!$step->isCompleted($documentType, $document->getKey())) {
                return $step;
            }
        }

        return null;
    }
}
