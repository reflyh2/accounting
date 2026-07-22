<?php

namespace App\Http\Middleware;

use App\Exceptions\DocumentStateException;
use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\Company;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceAccountingPeriod
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        $date = $this->resolveDate($request);

        if (! $date) {
            return $next($request);
        }

        $companyId = $this->resolveCompanyId($request);

        if (! $companyId) {
            return $next($request);
        }

        try {
            AccountingPeriod::validatePostingAllowed($date, $companyId);
        } catch (DocumentStateException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return redirect()->back()->with('error', $e->getMessage());
        }

        return $next($request);
    }

    private function resolveDate(Request $request): ?string
    {
        $dateKeys = [
            'date',
            'payment_date',
            'invoice_date',
            'transaction_date',
            'issue_date',
            'deposit_date',
            'transfer_date',
            'disposal_date',
            'maintenance_date',
            'return_date',
            'receipt_date',
            'delivery_date',
            'booking_date',
            'due_date',
        ];

        foreach ($dateKeys as $key) {
            if ($request->has($key) && ! empty($request->input($key))) {
                return (string) $request->input($key);
            }
        }

        // Try getting date from route model parameter (for update / delete / approve)
        foreach ($request->route()->parameters() as $parameter) {
            if ($parameter instanceof Model) {
                foreach (['date', 'payment_date', 'invoice_date', 'transaction_date', 'issue_date', 'deposit_date', 'transfer_date', 'disposal_date', 'maintenance_date', 'return_date', 'receipt_date', 'delivery_date', 'booking_date', 'created_at'] as $attr) {
                    if (! empty($parameter->{$attr})) {
                        return (string) $parameter->{$attr};
                    }
                }
            }
        }

        return null;
    }

    private function resolveCompanyId(Request $request): ?int
    {
        if ($request->has('company_id') && ! empty($request->input('company_id'))) {
            return (int) $request->input('company_id');
        }

        if ($request->has('branch_id') && ! empty($request->input('branch_id'))) {
            $branch = Branch::withoutGlobalScopes()
                ->with(['branchGroup' => fn ($q) => $q->withoutGlobalScopes()])
                ->find($request->input('branch_id'));

            if ($branch?->branchGroup?->company_id) {
                return (int) $branch->branchGroup->company_id;
            }
        }

        // Try getting company_id from route model parameter
        foreach ($request->route()->parameters() as $parameter) {
            if ($parameter instanceof Model) {
                if (! empty($parameter->company_id)) {
                    return (int) $parameter->company_id;
                }
                if (! empty($parameter->branch_id)) {
                    $branch = Branch::withoutGlobalScopes()
                        ->with(['branchGroup' => fn ($q) => $q->withoutGlobalScopes()])
                        ->find($parameter->branch_id);

                    if ($branch?->branchGroup?->company_id) {
                        return (int) $branch->branchGroup->company_id;
                    }
                }
            }
        }

        // Fallback to first company in database if single company or default context
        return Company::withoutGlobalScopes()->value('id');
    }
}
