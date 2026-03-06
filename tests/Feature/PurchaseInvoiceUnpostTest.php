<?php

use App\Enums\DebtStatus;
use App\Enums\Documents\InvoiceStatus;
use App\Enums\Documents\PurchaseOrderStatus;
use App\Exceptions\PurchaseInvoiceException;
use App\Models\Branch;
use App\Models\BranchGroup;
use App\Models\Company;
use App\Models\Currency;
use App\Models\ExternalDebt;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\Location;
use App\Models\Partner;
use App\Models\PartnerRole;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceLine;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Uom;
use App\Services\Purchasing\PurchaseInvoiceService;
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

    $this->location = Location::create([
        'name' => 'Main Warehouse',
        'branch_id' => $this->branch->id,
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

    $this->supplier = Partner::create([
        'name' => 'Test Supplier',
        'code' => 'SUP-01',
    ]);

    PartnerRole::create([
        'partner_id' => $this->supplier->id,
        'role' => 'supplier',
    ]);

    $this->service = app(PurchaseInvoiceService::class);
});

function createPostedPurchaseInvoice(): PurchaseInvoice
{
    $t = test();

    $po = PurchaseOrder::create([
        'company_id' => $t->company->id,
        'branch_id' => $t->branch->id,
        'partner_id' => $t->supplier->id,
        'currency_id' => $t->currency->id,
        'order_number' => 'PO-TEST-001',
        'order_date' => now(),
        'exchange_rate' => 1,
        'status' => PurchaseOrderStatus::RECEIVED->value,
    ]);

    $poLine = PurchaseOrderLine::create([
        'purchase_order_id' => $po->id,
        'product_variant_id' => $t->variant->id,
        'uom_id' => $t->uom->id,
        'line_number' => 1,
        'description' => 'Test Product',
        'quantity' => 10,
        'quantity_base' => 10,
        'unit_price' => 1000,
        'quantity_received' => 10,
        'quantity_invoiced' => 10,
        'quantity_invoiced_base' => 10,
        'amount_invoiced' => 10000,
        'quantity_returned' => 0,
    ]);

    $grn = GoodsReceipt::create([
        'company_id' => $t->company->id,
        'branch_id' => $t->branch->id,
        'partner_id' => $t->supplier->id,
        'receipt_number' => 'GRN-TEST-001',
        'receipt_date' => now(),
        'status' => 'posted',
    ]);

    $grn->purchaseOrders()->attach($po->id);

    $grnLine = GoodsReceiptLine::create([
        'goods_receipt_id' => $grn->id,
        'purchase_order_line_id' => $poLine->id,
        'product_variant_id' => $t->variant->id,
        'uom_id' => $t->uom->id,
        'quantity' => 10,
        'quantity_base' => 10,
        'unit_cost_base' => 1000,
        'quantity_invoiced' => 10,
        'quantity_invoiced_base' => 10,
        'amount_invoiced' => 10000,
        'quantity_returned' => 0,
    ]);

    $invoice = PurchaseInvoice::create([
        'company_id' => $t->company->id,
        'branch_id' => $t->branch->id,
        'partner_id' => $t->supplier->id,
        'currency_id' => $t->currency->id,
        'invoice_number' => 'PINV-TEST-001',
        'invoice_date' => now(),
        'exchange_rate' => 1,
        'subtotal' => 10000,
        'tax_total' => 0,
        'total_amount' => 10000,
        'grn_value_base' => 10000,
        'ppv_amount' => 0,
        'status' => InvoiceStatus::POSTED->value,
        'posted_at' => now(),
    ]);

    $invoice->purchaseOrders()->attach($po->id);

    PurchaseInvoiceLine::create([
        'purchase_invoice_id' => $invoice->id,
        'purchase_order_line_id' => $poLine->id,
        'goods_receipt_line_id' => $grnLine->id,
        'product_variant_id' => $t->variant->id,
        'uom_id' => $t->uom->id,
        'line_number' => 1,
        'description' => 'Test Product',
        'quantity' => 10,
        'quantity_base' => 10,
        'unit_price' => 1000,
        'line_total' => 10000,
        'line_total_base' => 10000,
        'grn_value_base' => 10000,
        'ppv_amount' => 0,
        'tax_amount' => 0,
    ]);

    return $invoice;
}

it('can unpost a posted PO-based purchase invoice', function () {
    $invoice = createPostedPurchaseInvoice();

    $result = $this->service->unpost($invoice);

    expect($result->status)->toBe(InvoiceStatus::DRAFT->value);
    expect($result->posted_at)->toBeNull();
    expect($result->posted_by)->toBeNull();

    $poLine = PurchaseOrderLine::first();
    expect((float) $poLine->quantity_invoiced)->toBe(0.0);
    expect((float) $poLine->quantity_invoiced_base)->toBe(0.0);
    expect((float) $poLine->amount_invoiced)->toBe(0.0);

    $grnLine = GoodsReceiptLine::first();
    expect((float) $grnLine->quantity_invoiced)->toBe(0.0);
    expect((float) $grnLine->quantity_invoiced_base)->toBe(0.0);
    expect((float) $grnLine->amount_invoiced)->toBe(0.0);
});

it('throws exception when trying to unpost a draft invoice', function () {
    $invoice = PurchaseInvoice::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'partner_id' => $this->supplier->id,
        'currency_id' => $this->currency->id,
        'invoice_number' => 'PINV-DRAFT-001',
        'invoice_date' => now(),
        'exchange_rate' => 1,
        'status' => InvoiceStatus::DRAFT->value,
    ]);

    $this->service->unpost($invoice);
})->throws(PurchaseInvoiceException::class, 'Hanya faktur yang sudah diposting yang dapat di-unpost.');

it('throws exception when trying to unpost an invoice with payments', function () {
    $invoice = createPostedPurchaseInvoice();

    $debt = ExternalDebt::create([
        'type' => 'payable',
        'branch_id' => $this->branch->id,
        'partner_id' => $this->supplier->id,
        'currency_id' => $this->currency->id,
        'exchange_rate' => 1,
        'issue_date' => now(),
        'amount' => 10000,
        'primary_currency_amount' => 10000,
        'debt_account_id' => 1,
        'offset_account_id' => 1,
        'status' => DebtStatus::PARTIALLY_PAID->value,
        'source_type' => PurchaseInvoice::class,
        'source_id' => $invoice->id,
    ]);

    $invoice->update(['external_debt_id' => $debt->id]);

    $this->service->unpost($invoice->fresh());
})->throws(PurchaseInvoiceException::class, 'Faktur tidak dapat di-unpost karena sudah memiliki pembayaran.');

it('deletes external debt when unposting', function () {
    $invoice = createPostedPurchaseInvoice();

    $debt = ExternalDebt::create([
        'type' => 'payable',
        'branch_id' => $this->branch->id,
        'partner_id' => $this->supplier->id,
        'currency_id' => $this->currency->id,
        'exchange_rate' => 1,
        'issue_date' => now(),
        'amount' => 10000,
        'primary_currency_amount' => 10000,
        'debt_account_id' => 1,
        'offset_account_id' => 1,
        'status' => DebtStatus::OPEN->value,
        'source_type' => PurchaseInvoice::class,
        'source_id' => $invoice->id,
    ]);

    $invoice->update(['external_debt_id' => $debt->id]);

    $this->service->unpost($invoice->fresh());

    expect(ExternalDebt::withTrashed()->find($debt->id))->toBeNull();
    expect($invoice->fresh()->external_debt_id)->toBeNull();
});

it('reopens closed PO when unposting', function () {
    $invoice = createPostedPurchaseInvoice();

    $po = PurchaseOrder::first();
    $po->update(['status' => PurchaseOrderStatus::CLOSED->value]);

    $this->service->unpost($invoice);

    expect($po->fresh()->status)->toBe(PurchaseOrderStatus::RECEIVED->value);
});
