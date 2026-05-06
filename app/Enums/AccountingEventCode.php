<?php

namespace App\Enums;

enum AccountingEventCode: string
{
    case PURCHASE_GRN_POSTED = 'purchase.grn_posted';
    case PURCHASE_GRN_REVERSED = 'purchase.grn_reversed';
    case PURCHASE_AP_POSTED = 'purchase.ap_posted';
    case PURCHASE_AP_REVERSED = 'purchase.ap_reversed';
    case PURCHASE_RETURN_POSTED = 'purchase.return_posted';
    case PURCHASE_RETURN_REVERSED = 'purchase.return_reversed';
    case SALES_DELIVERY_POSTED = 'sales.delivery_posted';
    case SALES_DELIVERY_REVERSED = 'sales.delivery_reversed';
    case SALES_RETURN_POSTED = 'sales.return_posted';
    case SALES_AR_POSTED = 'sales.ar_posted';
    case SALES_AR_REVERSED = 'sales.ar_reversed';
    case SALES_INVOICE_COGS_POSTED = 'sales.invoice_cogs_posted';
    case SALES_INVOICE_COGS_REVERSED = 'sales.invoice_cogs_reversed';
    case MFG_ISSUE_POSTED = 'mfg.issue_posted';
    case MFG_RECEIPT_POSTED = 'mfg.receipt_posted';
    case MFG_VARIANCE_POSTED = 'mfg.variance_posted';
    case INVENTORY_ADJUSTMENT_POSTED = 'inventory.adjustment_posted';
    case INVENTORY_ADJUSTMENT_REVERSED = 'inventory.adjustment_reversed';
    case COGS_RECOGNIZED = 'cogs.recognized';
    case COST_ALLOCATED = 'cost.allocated';
    case BOOKING_DEPOSIT_RECEIVED = 'booking.deposit_received';
    case BOOKING_DEPOSIT_REVERSED = 'booking.deposit_reversed';
    case BOOKING_DEPOSIT_APPLIED = 'booking.deposit_applied';
    case BOOKING_DEPOSIT_APPLIED_REVERSED = 'booking.deposit_applied_reversed';
    case BOOKING_PRINCIPAL_COGS_POSTED = 'booking.principal_cogs_posted';
    case BOOKING_PRINCIPAL_COGS_REVERSED = 'booking.principal_cogs_reversed';
    case BOOKING_AGENT_PASSTHROUGH_POSTED = 'booking.agent_passthrough_posted';
    case BOOKING_AGENT_PASSTHROUGH_REVERSED = 'booking.agent_passthrough_reversed';
    case BOOKING_POOL_COGS_POSTED = 'booking.pool_cogs_posted';
    case BOOKING_POOL_COGS_REVERSED = 'booking.pool_cogs_reversed';

    public function label(): string
    {
        return match ($this) {
            self::PURCHASE_GRN_POSTED => 'Purchase GRN Posted',
            self::PURCHASE_GRN_REVERSED => 'Purchase GRN Reversed',
            self::PURCHASE_AP_POSTED => 'Purchase AP Posted',
            self::PURCHASE_AP_REVERSED => 'Purchase AP Reversed',
            self::PURCHASE_RETURN_POSTED => 'Purchase Return Posted',
            self::PURCHASE_RETURN_REVERSED => 'Purchase Return Reversed',
            self::SALES_DELIVERY_POSTED => 'Sales Delivery Posted',
            self::SALES_DELIVERY_REVERSED => 'Sales Delivery Reversed',
            self::SALES_RETURN_POSTED => 'Sales Return Posted',
            self::SALES_AR_POSTED => 'Sales AR Posted',
            self::SALES_AR_REVERSED => 'Sales AR Reversed',
            self::SALES_INVOICE_COGS_POSTED => 'Sales Invoice COGS Posted',
            self::SALES_INVOICE_COGS_REVERSED => 'Sales Invoice COGS Reversed',
            self::MFG_ISSUE_POSTED => 'Manufacturing Issue Posted',
            self::MFG_RECEIPT_POSTED => 'Manufacturing Receipt Posted',
            self::MFG_VARIANCE_POSTED => 'Manufacturing Variance Posted',
            self::INVENTORY_ADJUSTMENT_POSTED => 'Inventory Adjustment Posted',
            self::INVENTORY_ADJUSTMENT_REVERSED => 'Inventory Adjustment Reversed',
            self::COGS_RECOGNIZED => 'COGS Recognized',
            self::COST_ALLOCATED => 'Cost Allocated',
            self::BOOKING_DEPOSIT_RECEIVED => 'Booking Deposit Received',
            self::BOOKING_DEPOSIT_REVERSED => 'Booking Deposit Reversed',
            self::BOOKING_DEPOSIT_APPLIED => 'Booking Deposit Applied to Invoice',
            self::BOOKING_DEPOSIT_APPLIED_REVERSED => 'Booking Deposit Application Reversed',
            self::BOOKING_PRINCIPAL_COGS_POSTED => 'Booking Principal COGS Posted',
            self::BOOKING_PRINCIPAL_COGS_REVERSED => 'Booking Principal COGS Reversed',
            self::BOOKING_AGENT_PASSTHROUGH_POSTED => 'Booking Agent Passthrough Posted',
            self::BOOKING_AGENT_PASSTHROUGH_REVERSED => 'Booking Agent Passthrough Reversed',
            self::BOOKING_POOL_COGS_POSTED => 'Booking Pool COGS Posted',
            self::BOOKING_POOL_COGS_REVERSED => 'Booking Pool COGS Reversed',
        };
    }
}
