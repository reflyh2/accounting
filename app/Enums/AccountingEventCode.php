<?php

namespace App\Enums;

enum AccountingEventCode: string
{
    case PURCHASE_GRN_POSTED = 'purchase.grn_posted';
    case PURCHASE_AP_POSTED = 'purchase.ap_posted';
    case SALES_DELIVERY_POSTED = 'sales.delivery_posted';
    case SALES_AR_POSTED = 'sales.ar_posted';
    case MFG_ISSUE_POSTED = 'mfg.issue_posted';
    case MFG_RECEIPT_POSTED = 'mfg.receipt_posted';

    public function label(): string
    {
        return match ($this) {
            self::PURCHASE_GRN_POSTED => 'Purchase GRN Posted',
            self::PURCHASE_AP_POSTED => 'Purchase AP Posted',
            self::SALES_DELIVERY_POSTED => 'Sales Delivery Posted',
            self::SALES_AR_POSTED => 'Sales AR Posted',
            self::MFG_ISSUE_POSTED => 'Manufacturing Issue Posted',
            self::MFG_RECEIPT_POSTED => 'Manufacturing Receipt Posted',
        };
    }
}


