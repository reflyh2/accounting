<?php

namespace App\Domain\Documents\StateMachine\Definitions;

use App\Domain\Documents\StateMachine\DocumentStateMachineDefinition;
use App\Domain\Documents\StateMachine\DocumentStateTransition;
use App\Enums\Documents\InvoiceStatus;

final class InvoiceStates
{
    public static function definition(): DocumentStateMachineDefinition
    {
        return DocumentStateMachineDefinition::make(InvoiceStatus::class)
            ->allow(DocumentStateTransition::make(InvoiceStatus::DRAFT, InvoiceStatus::POSTED))
            ->allow(DocumentStateTransition::make(InvoiceStatus::POSTED, InvoiceStatus::PARTIALLY_PAID))
            ->allow(DocumentStateTransition::make(InvoiceStatus::POSTED, InvoiceStatus::PAID))
            ->allow(DocumentStateTransition::make(InvoiceStatus::PARTIALLY_PAID, InvoiceStatus::PAID))
            ->allow(DocumentStateTransition::make(InvoiceStatus::DRAFT, InvoiceStatus::CANCELED));
    }
}


