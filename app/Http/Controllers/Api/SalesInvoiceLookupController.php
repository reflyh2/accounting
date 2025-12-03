<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SalesInvoiceLookupController extends Controller
{
    private const QTY_TOLERANCE = 0.0005;

    public function salesOrders(Request $request)
    {
        $query = SalesOrder::with(['partner', 'branch'])
            ->whereIn('status', ['partially_delivered', 'delivered', 'closed'])
            ->whereHas('lines', function ($q) {
                $q->whereRaw('(COALESCE(sales_order_lines.quantity_delivered,0) - COALESCE(sales_order_lines.quantity_invoiced,0)) > ?', [self::QTY_TOLERANCE]);
            });

        if ($request->filled('company_id')) {
            $companyIds = (array) $request->input('company_id');
            $query->whereHas('branch.branchGroup', function ($q) use ($companyIds) {
                $q->whereIn('company_id', $companyIds);
            });
        }

        if ($request->filled('branch_id')) {
            $query->whereIn('branch_id', (array) $request->input('branch_id'));
        }

        if ($request->filled('partner_id')) {
            $query->whereIn('partner_id', (array) $request->input('partner_id'));
        }

        if ($request->filled('search')) {
            $search = strtolower($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->whereRaw('lower(order_number) like ?', ["%{$search}%"])
                    ->orWhereHas('partner', function ($partner) use ($search) {
                        $partner->whereRaw('lower(name) like ?', ["%{$search}%"]);
                    });
            });
        }

        $sort = $request->input('sort', 'order_date');
        $order = $request->input('order', 'desc');

        if (!in_array($sort, ['order_date', 'order_number'], true)) {
            $sort = 'order_date';
        }

        $query->orderBy($sort, $order);

        $perPage = $request->integer('per_page', 10);

        return $query->paginate($perPage)->withQueryString();
    }
}
