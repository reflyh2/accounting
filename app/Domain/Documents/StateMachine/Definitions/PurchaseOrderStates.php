<?php

namespace App\Domain\Documents\StateMachine\Definitions;

use App\Domain\Documents\StateMachine\DocumentStateMachineDefinition;
use App\Domain\Documents\StateMachine\DocumentStateTransition;
use App\Enums\Documents\PurchaseOrderStatus;

final class PurchaseOrderStates
{
    public static function definition(): DocumentStateMachineDefinition
    {
        return DocumentStateMachineDefinition::make(PurchaseOrderStatus::class)
            ->allow(DocumentStateTransition::make(PurchaseOrderStatus::DRAFT, PurchaseOrderStatus::APPROVED))
            ->allow(DocumentStateTransition::make(PurchaseOrderStatus::APPROVED, PurchaseOrderStatus::SENT))
            ->allow(DocumentStateTransition::make(PurchaseOrderStatus::SENT, PurchaseOrderStatus::PARTIALLY_RECEIVED))
            ->allow(DocumentStateTransition::make(PurchaseOrderStatus::SENT, PurchaseOrderStatus::RECEIVED))
            ->allow(DocumentStateTransition::make(PurchaseOrderStatus::PARTIALLY_RECEIVED, PurchaseOrderStatus::RECEIVED))
            ->allow(DocumentStateTransition::make(PurchaseOrderStatus::PARTIALLY_RECEIVED, PurchaseOrderStatus::CLOSED))
            ->allow(DocumentStateTransition::make(PurchaseOrderStatus::RECEIVED, PurchaseOrderStatus::CLOSED))
            ->allow(DocumentStateTransition::make(PurchaseOrderStatus::DRAFT, PurchaseOrderStatus::CANCELED))
            ->allow(DocumentStateTransition::make(PurchaseOrderStatus::APPROVED, PurchaseOrderStatus::CANCELED))
            ->allow(DocumentStateTransition::make(PurchaseOrderStatus::SENT, PurchaseOrderStatus::CANCELED));
    }
}


