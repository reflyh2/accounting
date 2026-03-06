<?php

use App\Enums\DebtStatus;
use App\Enums\Documents\InvoiceStatus;
use App\Enums\Documents\SalesOrderStatus;
use App\Exceptions\SalesInvoiceException;
use App\Models\Branch;
use App\Models\BranchGroup;
use App\Models\Company;
use App\Models\Currency;
use App\Models\ExternalDebt;
use App\Models\Partner;
use App\Models\PartnerRole;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SalesDelivery;
use App\Models\SalesDeliveryLine;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceLine;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\Uom;
use App\Services\Sales\SalesInvoiceService;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->company = Company::create([
        'name' => 'Test Co',
        'legal_name' => 'Test Co Ltd',
        'costing_policy' => 'fifo',
        'reservation_strictness' => 'soft',
        'default_backflush' => false,
    ]);

    $this->branchGroup = BranchGroup::create([
        'name' => 'HQ',
        'company_id' => $this->company->id,
    ]);

    $this->branch = Branch::create([
        'name' => 'Main Branch',
        'address' => 'Jl. Test',
        'branch_group_id' => $this->branchGroup->id,
    ]);

    $this->currency = Currency::create([
        'code' => 'IDR',
        'name' => 'Rupiah',
        'symbol' => 'Rp',
        'is_primary' => true,
    ]);

    $this->uom = Uom::create([
        'company_id' => $this->company->id,
        'code' => 'PCS',
        'name' => 'Pieces',
        'kind' => 'each',
    ]);

    $this->product = Product::create([
        'code' => 'PROD-001',
        'name' => 'Test Product',
        'kind' => 'goods',
        'default_uom_id' => $this->uom->id,
    ]);

    DB::table('company_product')->insert([
        'company_id' => $this->company->id,
        'product_id' => $this->product->id,
    ]);

    $this->variant = ProductVariant::create([
        'product_id' => $this->product->id,
        'sku' => 'PROD-001-STD',
        'uom_id' => $this->uom->id,
        'track_inventory' => true,
    ]);

    $this->customer = Partner::create([
        'name' => 'Test Customer',
        'code' => 'CUS-01',
    ]);

    PartnerRole::create([
        'partner_id' => $this->customer->id,
        'role' => 'customer',
    ]);

    $this->service = app(SalesInvoiceService::class);
});

function createPostedSalesInvoice(): SalesInvoice
{
    $t = test();

    $so = SalesOrder::create([
        'company_id' => $t->company->id,
        'branch_id' => $t->branch->id,
        'partner_id' => $t->customer->id,
        'currency_id' => $t->currency->id,
        'order_number' => 'SO-TEST-001',
        'order_date' => now(),
        'exchange_rate' => 1,
        'status' => SalesOrderStatus::DELIVERED->value,
    ]);

    $soLine = SalesOrderLine::create([
        'sales_order_id' => $so->id,
        'product_variant_id' => $t->variant->id,
        'product_id' => $t->product->id,
        'uom_id' => $t->uom->id,
        'line_number' => 1,
        'description' => 'Test Product',
        'quantity' => 10,
        'quantity_base' => 10,
        'unit_price' => 2000,
        'quantity_delivered' => 10,
        'quantity_invoiced' => 10,
        'quantity_invoiced_base' => 10,
        'amount_invoiced' => 20000,
        'quantity_returned' => 0,
    ]);

    $sd = SalesDelivery::create([
        'company_id' => $t->company->id,
        'branch_id' => $t->branch->id,
        'partner_id' => $t->customer->id,
        'delivery_number' => 'SD-TEST-001',
        'delivery_date' => now(),
        'status' => 'posted',
    ]);

    $sdLine = SalesDeliveryLine::create([
        'sales_delivery_id' => $sd->id,
        'sales_order_line_id' => $soLine->id,
        'product_variant_id' => $t->variant->id,
        'uom_id' => $t->uom->id,
        'quantity' => 10,
        'quantity_base' => 10,
        'unit_cost_base' => 1000,
        'quantity_invoiced' => 10,
        'quantity_invoiced_base' => 10,
        'amount_invoiced' => 20000,
        'quantity_returned' => 0,
    ]);

    $invoice = SalesInvoice::create([
        'company_id' => $t->company->id,
        'branch_id' => $t->branch->id,
        'partner_id' => $t->customer->id,
        'currency_id' => $t->currency->id,
        'invoice_number' => 'SINV-TEST-001',
        'invoice_date' => now(),
        'exchange_rate' => 1,
        'subtotal' => 20000,
        'tax_total' => 0,
        'total_amount' => 20000,
        'delivery_value_base' => 10000,
        'revenue_variance' => 10000,
        'status' => InvoiceStatus::POSTED->value,
        'posted_at' => now(),
    ]);

    $invoice->salesOrders()->attach($so->id);

    SalesInvoiceLine::create([
        'sales_invoice_id' => $invoice->id,
        'sales_order_line_id' => $soLine->id,
        'sales_delivery_line_id' => $sdLine->id,
        'line_number' => 1,
        'description' => 'Test Product',
        'uom_label' => 'Pieces',
        'quantity' => 10,
        'quantity_base' => 10,
        'unit_price' => 2000,
        'line_total' => 20000,
        'line_total_base' => 20000,
        'delivery_value_base' => 10000,
        'revenue_variance' => 10000,
        'tax_amount' => 0,
    ]);

    return $invoice;
}

it('can unpost a posted SO-based sales invoice', function () {
    $invoice = createPostedSalesInvoice();

    $result = $this->service->unpost($invoice);

    expect($result->status)->toBe(InvoiceStatus::DRAFT->value);
    expect($result->posted_at)->toBeNull();
    expect($result->posted_by)->toBeNull();

    $soLine = SalesOrderLine::first();
    expect((float) $soLine->quantity_invoiced)->toBe(0.0);
    expect((float) $soLine->quantity_invoiced_base)->toBe(0.0);
    expect((float) $soLine->amount_invoiced)->toBe(0.0);

    $sdLine = SalesDeliveryLine::first();
    expect((float) $sdLine->quantity_invoiced)->toBe(0.0);
    expect((float) $sdLine->quantity_invoiced_base)->toBe(0.0);
    expect((float) $sdLine->amount_invoiced)->toBe(0.0);
});

it('throws exception when trying to unpost a draft sales invoice', function () {
    $invoice = SalesInvoice::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'partner_id' => $this->customer->id,
        'currency_id' => $this->currency->id,
        'invoice_number' => 'SINV-DRAFT-001',
        'invoice_date' => now(),
        'exchange_rate' => 1,
        'status' => InvoiceStatus::DRAFT->value,
    ]);

    $this->service->unpost($invoice);
})->throws(SalesInvoiceException::class, 'Hanya faktur yang sudah diposting yang dapat di-unpost.');

it('throws exception when trying to unpost a sales invoice with payments', function () {
    $invoice = createPostedSalesInvoice();

    $debt = ExternalDebt::create([
        'type' => 'receivable',
        'branch_id' => $this->branch->id,
        'partner_id' => $this->customer->id,
        'currency_id' => $this->currency->id,
        'exchange_rate' => 1,
        'issue_date' => now(),
        'amount' => 20000,
        'primary_currency_amount' => 20000,
        'debt_account_id' => 1,
        'offset_account_id' => 1,
        'status' => DebtStatus::PAID->value,
        'source_type' => SalesInvoice::class,
        'source_id' => $invoice->id,
    ]);

    $invoice->update(['external_debt_id' => $debt->id]);

    $this->service->unpost($invoice->fresh());
})->throws(SalesInvoiceException::class, 'Faktur tidak dapat di-unpost karena sudah memiliki pembayaran.');

it('deletes external debt when unposting sales invoice', function () {
    $invoice = createPostedSalesInvoice();

    $debt = ExternalDebt::create([
        'type' => 'receivable',
        'branch_id' => $this->branch->id,
        'partner_id' => $this->customer->id,
        'currency_id' => $this->currency->id,
        'exchange_rate' => 1,
        'issue_date' => now(),
        'amount' => 20000,
        'primary_currency_amount' => 20000,
        'debt_account_id' => 1,
        'offset_account_id' => 1,
        'status' => DebtStatus::OPEN->value,
        'source_type' => SalesInvoice::class,
        'source_id' => $invoice->id,
    ]);

    $invoice->update(['external_debt_id' => $debt->id]);

    $this->service->unpost($invoice->fresh());

    expect(ExternalDebt::withTrashed()->find($debt->id))->toBeNull();
    expect($invoice->fresh()->external_debt_id)->toBeNull();
});

it('reopens closed SO when unposting', function () {
    $invoice = createPostedSalesInvoice();

    $so = SalesOrder::first();
    $so->update(['status' => SalesOrderStatus::CLOSED->value]);

    $this->service->unpost($invoice);

    expect($so->fresh()->status)->toBe(SalesOrderStatus::DELIVERED->value);
});
